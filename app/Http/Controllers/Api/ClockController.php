<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Restaurant;
use App\Support\Shifts;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Everything the "pointeuse" screen needs: today's planning for one restaurant,
 * arrival/departure clocking, and the worker's signature.
 */
class ClockController extends Controller
{
    public function day(Request $request, Restaurant $restaurant): JsonResponse
    {
        $now = CarbonImmutable::now();
        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->string('date'))->startOfDay()
            : Shifts::serviceDate($now);

        $assignments = Assignment::with('worker')
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('date', $date->toDateString())
            ->orderBy('planned_start')
            ->get();

        // Also surface yesterday's close-shift workers who are still clocked in
        // (they finish at 6h, the screen may already be on the new service day).
        $hanging = Assignment::with('worker')
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('date', $date->subDay()->toDateString())
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
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
     * A worker who was not planned shows up: plan them from now to the end of
     * the shift in progress (deduced from the clock) and clock in at once.
     */
    public function walkIn(Request $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
        ]);

        $now = CarbonImmutable::now();
        $date = Shifts::serviceDate($now);
        $shift = Shifts::current($now);

        $today = Assignment::where('worker_id', $data['worker_id'])->whereDate('date', $date->toDateString());
        if ((clone $today)->whereNotNull('clock_in_at')->whereNull('clock_out_at')->exists()) {
            throw ValidationException::withMessages(['worker_id' => 'Cet intérimaire est déjà en poste.']);
        }

        // An existing planned (not yet clocked) shift in this restaurant is simply clocked in.
        $assignment = (clone $today)->where('restaurant_id', $restaurant->id)->whereNull('clock_in_at')->where('absent', false)
            ->where(fn ($q) => $q->where('planned_end', '>', $now->subHours(2))->orWhereNull('planned_end'))
            ->orderBy('planned_start')->first();

        // Otherwise an open-ended slot from now: no planned end, the departure will be the end.
        if (! $assignment) {
            $assignment = new Assignment([
                'restaurant_id' => $restaurant->id,
                'worker_id' => $data['worker_id'],
                'date' => $date->toDateString(),
            ]);
            $assignment->planned_start = $now->startOfMinute();
            $assignment->planned_end = null;
            $assignment->shift = $shift;
        }
        $assignment->absent = false;
        $assignment->clock_in_at = $now;
        $assignment->save();

        return response()->json($assignment->load(['worker', 'restaurant']), 201);
    }

    public function clockIn(Assignment $assignment): JsonResponse
    {
        if ($assignment->clock_in_at) {
            throw ValidationException::withMessages(['clock_in_at' => 'Arrivée déjà pointée.']);
        }
        $assignment->forceFill(['clock_in_at' => now(), 'absent' => false])->save();

        return response()->json($assignment->load('worker'));
    }

    public function clockOut(Assignment $assignment): JsonResponse
    {
        if (! $assignment->clock_in_at) {
            throw ValidationException::withMessages(['clock_in_at' => "L'arrivée n'a pas été pointée."]);
        }
        if ($assignment->clock_out_at) {
            throw ValidationException::withMessages(['clock_out_at' => 'Départ déjà pointé.']);
        }
        $assignment->forceFill(['clock_out_at' => now()])->save();

        return response()->json($assignment->load('worker'));
    }

    /** The worker validates their hours and signs (PNG data URL from the canvas). */
    public function sign(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:400000'],
        ]);
        if (! $assignment->clock_out_at) {
            throw ValidationException::withMessages(['signature' => 'Le départ doit être pointé avant de signer.']);
        }

        $assignment->forceFill([
            'signature' => $data['signature'],
            'signed_at' => now(),
        ])->save();

        return response()->json($assignment->load('worker'));
    }

    public function signature(Assignment $assignment): JsonResponse
    {
        return response()->json([
            'signature' => $assignment->signature,
            'signed_at' => $assignment->signed_at?->toIso8601String(),
        ]);
    }
}
