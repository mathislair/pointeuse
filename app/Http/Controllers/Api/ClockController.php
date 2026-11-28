<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Restaurant;
use App\Support\Shifts;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything the "pointeuse" screens need: today's planning for one restaurant,
 * arrival/departure clocking, and the worker's signature.
 *
 * Clocking routes are scoped by restaurant: a tablet or a phone that scanned
 * the QR code of restaurant A cannot clock a slot planned in restaurant B.
 */
class ClockController extends Controller
{
    /** How early before the planned start a worker may clock in. */
    private const CLOCK_IN_BEFORE_HOURS = 3;

    /** How long after the end of the slot a late arrival may still be clocked on it. */
    private const CLOCK_IN_AFTER_HOURS = 2;

    public function day(Request $request, Restaurant $restaurant): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $now = CarbonImmutable::now();
        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->string('date'))->startOfDay()
            : Shifts::serviceDate($now);

        Assignment::closeForgotten($now);

        $assignments = Assignment::with('worker')
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('date', $date->toDateString())
            ->orderBy('planned_start')
            ->get();

        // Also surface workers of previous service days who are still clocked in
        // (the close shift finishes at 6h, the screen may already be on the new day).
        $hanging = Assignment::with('worker')
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('date', '<', $date->toDateString())
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->orderBy('planned_start')
            ->get();

        return response()->json([
            'restaurant' => $restaurant,
            'date' => $date->toDateString(),
            'now' => $now->toIso8601String(),
            'day_start' => $date->setTime((int) config('pointeuse.day_start_hour'), 0)->toIso8601String(),
            'assignments' => $assignments,
            'hanging' => $hanging,
        ]);
    }

    /**
     * A worker who was not planned shows up: plan them from now (open-ended, on
     * the shift in progress) and clock in at once. A slot already planned here
     * around now is simply clocked in instead.
     */
    public function walkIn(Request $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
        ]);

        $now = CarbonImmutable::now();
        $date = Shifts::serviceDate($now);

        // The transaction is IMMEDIATE on SQLite (config/database.php): two taps
        // at the same time are serialized, the second one sees the first clock-in.
        $assignment = DB::transaction(function () use ($data, $restaurant, $now, $date) {
            if (Assignment::where('worker_id', $data['worker_id'])->whereNotNull('clock_in_at')->whereNull('clock_out_at')->exists()) {
                throw ValidationException::withMessages(['worker_id' => 'Cet intérimaire est déjà en poste.']);
            }

            // An existing planned (not yet clocked) slot in this restaurant around now is simply clocked in.
            $assignment = Assignment::where('worker_id', $data['worker_id'])
                ->whereDate('date', $date->toDateString())
                ->where('restaurant_id', $restaurant->id)
                ->whereNull('clock_in_at')
                ->where('absent', false)
                ->where('planned_start', '<=', $now->addHours(self::CLOCK_IN_BEFORE_HOURS))
                ->where(fn ($q) => $q->where('planned_end', '>', $now->subHours(self::CLOCK_IN_AFTER_HOURS))->orWhereNull('planned_end'))
                ->orderBy('planned_start')
                ->first();

            // Otherwise an open-ended slot from now: no planned end, the departure will be the end.
            if (! $assignment) {
                $assignment = new Assignment([
                    'restaurant_id' => $restaurant->id,
                    'worker_id' => $data['worker_id'],
                    'date' => $date->toDateString(),
                ]);
                $assignment->planned_start = $now->startOfMinute();
                $assignment->planned_end = null;
                $assignment->shift = Shifts::current($now);
                if ($other = $assignment->overlapping()) {
                    throw ValidationException::withMessages(['worker_id' => sprintf(
                        'Cet intérimaire est déjà planifié de %s à %s chez %s.',
                        $other->planned_start->format('H:i'),
                        $other->effectiveEnd()->format('H:i'),
                        $other->restaurant->name,
                    )]);
                }
            }
            $assignment->absent = false;
            $assignment->clock_in_at = $now;
            $assignment->save();

            return $assignment;
        });

        return response()->json($assignment->load(['worker', 'restaurant']), 201);
    }

    public function clockIn(Restaurant $restaurant, Assignment $assignment): JsonResponse
    {
        $this->assertBelongs($restaurant, $assignment);
        if ($assignment->clock_in_at) {
            throw ValidationException::withMessages(['clock_in_at' => 'Arrivée déjà pointée.']);
        }

        $now = CarbonImmutable::now();
        $start = CarbonImmutable::instance($assignment->planned_start);
        if ($now->lessThan($start->subHours(self::CLOCK_IN_BEFORE_HOURS))) {
            throw ValidationException::withMessages(['clock_in_at' => sprintf(
                'Ce créneau commence à %s : le pointage est possible %d h avant.', $start->format('H:i'), self::CLOCK_IN_BEFORE_HOURS
            )]);
        }
        if ($now->greaterThan($assignment->effectiveEnd()->addHours(self::CLOCK_IN_AFTER_HOURS))) {
            throw ValidationException::withMessages(['clock_in_at' => 'Ce créneau est terminé : pointez une arrivée non planifiée.']);
        }

        // Conditional update: a second tap (or a second device) finds no row to update.
        $updated = Assignment::whereKey($assignment->id)->whereNull('clock_in_at')
            ->update(['clock_in_at' => $now, 'absent' => false, 'updated_at' => $now]);
        if (! $updated) {
            throw ValidationException::withMessages(['clock_in_at' => 'Arrivée déjà pointée.']);
        }

        return response()->json($assignment->fresh()->load('worker'));
    }

    public function clockOut(Restaurant $restaurant, Assignment $assignment): JsonResponse
    {
        $this->assertBelongs($restaurant, $assignment);
        if (! $assignment->clock_in_at) {
            throw ValidationException::withMessages(['clock_in_at' => "L'arrivée n'a pas été pointée."]);
        }

        $now = CarbonImmutable::now();
        $updated = Assignment::whereKey($assignment->id)->whereNotNull('clock_in_at')->whereNull('clock_out_at')
            ->update(['clock_out_at' => $now, 'updated_at' => $now]);
        if (! $updated) {
            throw ValidationException::withMessages(['clock_out_at' => 'Départ déjà pointé.']);
        }

        return response()->json($assignment->fresh()->load('worker'));
    }

    /** The worker validates their hours and signs (PNG data URL from the canvas), once. */
    public function sign(Request $request, Restaurant $restaurant, Assignment $assignment): JsonResponse
    {
        $this->assertBelongs($restaurant, $assignment);
        $data = $request->validate([
            'signature' => ['required', 'string', 'max:400000', function (string $attribute, mixed $value, \Closure $fail) {
                if (! self::isPngDataUrl($value)) {
                    $fail('La signature doit être une image PNG.');
                }
            }],
        ]);
        if (! $assignment->clock_out_at) {
            throw ValidationException::withMessages(['signature' => 'Le départ doit être pointé avant de signer.']);
        }
        if ($assignment->signed_at) {
            throw ValidationException::withMessages(['signature' => 'Ces heures sont déjà signées. Demandez une correction à votre responsable.']);
        }

        $now = CarbonImmutable::now();
        $updated = Assignment::whereKey($assignment->id)->whereNotNull('clock_out_at')->whereNull('signed_at')
            ->update(['signature' => $data['signature'], 'signed_at' => $now, 'updated_at' => $now]);
        if (! $updated) {
            throw ValidationException::withMessages(['signature' => 'Ces heures sont déjà signées.']);
        }

        return response()->json($assignment->fresh()->load('worker'));
    }

    public function signature(Assignment $assignment): JsonResponse
    {
        return response()->json([
            'signature' => $assignment->signature,
            'signed_at' => $assignment->signed_at?->toIso8601String(),
        ]);
    }

    /** A "data:image/png;base64,…" URL whose payload really is a PNG file. */
    public static function isPngDataUrl(string $value): bool
    {
        $prefix = 'data:image/png;base64,';
        if (! str_starts_with($value, $prefix)) {
            return false;
        }
        $binary = base64_decode(substr($value, strlen($prefix)), true);

        return $binary !== false && str_starts_with($binary, "\x89PNG\r\n\x1a\n");
    }

    private function assertBelongs(Restaurant $restaurant, Assignment $assignment): void
    {
        if ($assignment->restaurant_id !== $restaurant->id) {
            throw ValidationException::withMessages(['assignment' => "Ce créneau n'est pas planifié dans ce restaurant."]);
        }
    }
}
