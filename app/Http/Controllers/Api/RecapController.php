<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Month-end summary: who worked, where, and how many hours.
 */
class RecapController extends Controller
{
    public function month(Request $request): JsonResponse
    {
        [$from, $to, $assignments] = $this->load($request);

        $byWorker = $assignments->groupBy('worker_id')->map(function (Collection $items) {
            $worked = $items->filter(fn ($a) => $a->worked_minutes !== null);
            $worker = $items->first()->worker;

            return [
                'worker_id' => $worker->id,
                'worker' => $worker,
                'planned_shifts' => $items->count(),
                'worked_shifts' => $worked->count(),
                'absent' => $items->where('absent', true)->count(),
                'unsigned' => $worked->filter(fn ($a) => ! $a->signed_at)->count(),
                'incomplete' => $items->filter(fn ($a) => $a->status === 'present')->count(),
                'minutes' => (int) $worked->sum('worked_minutes'),
                'by_restaurant' => $worked->groupBy('restaurant_id')->map(fn (Collection $r) => [
                    'restaurant' => $r->first()->restaurant,
                    'shifts' => $r->count(),
                    'minutes' => (int) $r->sum('worked_minutes'),
                ])->values(),
                'lines' => $worked->sortBy('planned_start')->values(),
            ];
        })->sortBy(fn ($row) => mb_strtolower($row['worker']->last_name.' '.$row['worker']->first_name))->values();

        $byRestaurant = $assignments->filter(fn ($a) => $a->worked_minutes !== null)
            ->groupBy('restaurant_id')->map(fn (Collection $r) => [
                'restaurant' => $r->first()->restaurant,
                'shifts' => $r->count(),
                'minutes' => (int) $r->sum('worked_minutes'),
            ])->sortBy(fn ($row) => $row['restaurant']->name)->values();

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'shifts' => $byWorker->sum('worked_shifts'),
                'minutes' => $byWorker->sum('minutes'),
                'unsigned' => $byWorker->sum('unsigned'),
                'absent' => $byWorker->sum('absent'),
                'incomplete' => $byWorker->sum('incomplete'),
            ],
            'workers' => $byWorker,
            'restaurants' => $byRestaurant,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, , $assignments] = $this->load($request);
        $filename = 'recap-'.$from->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($assignments) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
            fputcsv($out, ['Date', 'Restaurant', 'Nom', 'Prénom', 'Shift', 'Arrivée', 'Départ', 'Heures', 'Signé', 'Statut', 'Note'], ';');
            foreach ($assignments->sortBy('planned_start') as $a) {
                fputcsv($out, [
                    $a->date->format('d/m/Y'),
                    $a->restaurant->name,
                    $a->worker->last_name,
                    $a->worker->first_name,
                    config("pointeuse.shifts.{$a->shift}.label", $a->shift),
                    $a->clock_in_at?->format('d/m/Y H:i') ?? '',
                    $a->clock_out_at?->format('d/m/Y H:i') ?? '',
                    $a->worked_minutes !== null ? number_format($a->worked_minutes / 60, 2, ',', '') : '',
                    $a->signed_at ? 'oui' : 'non',
                    $a->status,
                    $a->admin_note ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: Collection<int, Assignment>} */
    private function load(Request $request): array
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'worker_id' => ['nullable', 'integer', 'exists:workers,id'],
        ]);

        if (! empty($data['from']) && ! empty($data['to'])) {
            $from = CarbonImmutable::parse($data['from'])->startOfDay();
            $to = CarbonImmutable::parse($data['to'])->startOfDay();
        } else {
            $month = CarbonImmutable::parse(($data['month'] ?? now()->format('Y-m')).'-01');
            $from = $month->startOfMonth();
            $to = $month->endOfMonth()->startOfDay();
        }

        Assignment::closeForgotten();

        $query = Assignment::with(['worker', 'restaurant'])->between($from->toDateString(), $to->toDateString());
        if (! empty($data['restaurant_id'])) {
            $query->where('restaurant_id', $data['restaurant_id']);
        }
        if (! empty($data['worker_id'])) {
            $query->where('worker_id', $data['worker_id']);
        }

        return [$from, $to, $query->get()];
    }
}
