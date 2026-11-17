<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    /** List assignments for a date range (admin planning & clockings tables). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'worker_id' => ['nullable', 'integer', 'exists:workers,id'],
            'status' => ['nullable', 'string'],
        ]);

        $query = Assignment::with(['worker', 'restaurant'])
            ->between($data['from'], $data['to'])
            ->orderBy('planned_start')
            ->orderBy('restaurant_id');

        if (! empty($data['restaurant_id'])) {
            $query->where('restaurant_id', $data['restaurant_id']);
        }
        if (! empty($data['worker_id'])) {
            $query->where('worker_id', $data['worker_id']);
        }

        $items = $query->get();
        if (! empty($data['status'])) {
            $items = $items->filter(fn ($a) => $a->status === $data['status'])->values();
        }

        return response()->json($items);
    }

    /** Plan a worker from one time to another; the shift is deduced from the range. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'date' => ['required', 'date'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $assignment = new Assignment(collect($data)->except(['start', 'end'])->all());
        $assignment->applyTimes($data['start'], $data['end']);
        $this->assertNoOverlap($assignment);
        $assignment->save();

        return response()->json($assignment->load(['worker', 'restaurant']), 201);
    }

    public function show(Assignment $assignment): JsonResponse
    {
        return response()->json($assignment->load(['worker', 'restaurant']));
    }

    /** Admin correction: move the shift, fix arrival/departure, mark absent, add a note. */
    public function update(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'restaurant_id' => ['sometimes', 'integer', 'exists:restaurants,id'],
            'worker_id' => ['sometimes', 'integer', 'exists:workers,id'],
            'date' => ['sometimes', 'date'],
            'start' => ['sometimes', 'date_format:H:i'],
            'end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at'],
            'absent' => ['sometimes', 'boolean'],
            'admin_note' => ['nullable', 'string', 'max:500'],
            'reset_signature' => ['sometimes', 'boolean'],
        ]);

        $clockingChanged = false;
        foreach (['clock_in_at', 'clock_out_at'] as $field) {
            if ($request->has($field)) {
                // The browser sends UTC; Eloquent stores the wall-clock time as is, so convert to Paris first.
                $new = $data[$field] ? CarbonImmutable::parse($data[$field])->setTimezone(config('app.timezone')) : null;
                $old = $assignment->{$field};
                if (($new?->toIso8601String()) !== ($old?->toIso8601String())) {
                    $assignment->{$field} = $new;
                    $clockingChanged = true;
                }
            }
        }

        if (array_key_exists('absent', $data)) {
            $assignment->absent = $data['absent'];
            if ($data['absent']) {
                $assignment->clock_in_at = null;
                $assignment->clock_out_at = null;
                $assignment->signature = null;
                $assignment->signed_at = null;
            }
        }

        foreach (['restaurant_id', 'worker_id', 'date', 'admin_note'] as $field) {
            if (array_key_exists($field, $data)) {
                $assignment->{$field} = $data[$field];
            }
        }
        if (isset($data['date']) || isset($data['start']) || array_key_exists('end', $data) || isset($data['worker_id'])) {
            [$curStart, $curEnd] = $assignment->plannedTimes();
            $assignment->applyTimes($data['start'] ?? $curStart, array_key_exists('end', $data) ? $data['end'] : $curEnd);
            if (! $assignment->absent) {
                $this->assertNoOverlap($assignment);
            }
        }

        // A corrected clocking invalidates the worker's signature: it must be signed again.
        if ($clockingChanged || ! empty($data['reset_signature'])) {
            $assignment->signature = null;
            $assignment->signed_at = null;
        }

        $assignment->edited_at = now();

        $assignment->save();

        return response()->json($assignment->load(['worker', 'restaurant']));
    }

    /** A worker cannot be planned twice at the same time, whatever the restaurant. */
    private function assertNoOverlap(Assignment $assignment): void
    {
        if ($other = $assignment->overlapping()) {
            throw ValidationException::withMessages(['worker_id' => $other->planned_end
                ? sprintf('Cet intérimaire est déjà planifié de %s à %s chez %s.', $other->planned_start->format('H:i'), $other->planned_end->format('H:i'), $other->restaurant->name)
                : sprintf('Cet intérimaire est déjà en poste depuis %s chez %s.', $other->planned_start->format('H:i'), $other->restaurant->name),
            ]);
        }
    }

    public function destroy(Assignment $assignment): Response
    {
        $assignment->delete();

        return response()->noContent();
    }
}
