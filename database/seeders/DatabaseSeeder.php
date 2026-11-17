<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Restaurant;
use App\Models\Worker;
use App\Support\Shifts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Minimal data so the app is usable right after the first boot:
     * a few restaurants, a handful of workers, and a demo planning around today.
     */
    public function run(): void
    {
        if (Restaurant::count() > 0) {
            return;
        }

        $restaurants = collect([
            ['name' => 'Villejuif', 'color' => '#dc2626'],
            ['name' => 'Kremlin', 'color' => '#0284c7'],
            ['name' => 'Châtillon', 'color' => '#16a34a'],
        ])->map(fn ($r) => Restaurant::create($r + ['slug' => Str::slug($r['name'])]));

        $workers = collect([
            ['first_name' => 'Léa', 'last_name' => 'Martin'],
            ['first_name' => 'Karim', 'last_name' => 'Benali'],
            ['first_name' => 'Sofia', 'last_name' => 'Rossi'],
            ['first_name' => 'Thomas', 'last_name' => 'Nguyen'],
            ['first_name' => 'Awa', 'last_name' => 'Diallo'],
            ['first_name' => 'Hugo', 'last_name' => 'Lefebvre'],
        ])->map(fn ($w) => Worker::create($w));

        if (! app()->environment('production')) {
            $this->demoPlanning($restaurants, $workers);
        }
    }

    private function demoPlanning($restaurants, $workers): void
    {
        $today = Shifts::serviceDate(CarbonImmutable::now());
        $shifts = Shifts::keys();

        foreach (range(-10, 6) as $offset) {
            $date = $today->addDays($offset);
            $i = 0;
            foreach ($restaurants as $restaurant) {
                foreach ($shifts as $shift) {
                    // 1 or 2 workers per shift, rotating through the list
                    $count = ($offset + $i) % 3 === 0 ? 2 : 1;
                    for ($k = 0; $k < $count; $k++) {
                        $worker = $workers[($i + $k + $offset * 2 + 100) % $workers->count()];
                        $i++;
                        $a = new Assignment([
                            'restaurant_id' => $restaurant->id,
                            'worker_id' => $worker->id,
                            'date' => $date->toDateString(),
                        ]);
                        // planned ranges around each shift: whole shift, shorter, or spilling over
                        $ranges = [
                            'open' => [['09:00', '17:30'], ['11:00', '17:30'], ['09:00', '15:00'], ['08:30', '16:00']],
                            'swing' => [['17:30', '23:30'], ['19:00', '23:30'], ['17:30', '22:00'], ['16:00', '22:00']],
                            'close' => [['23:30', '06:00'], ['23:30', '04:00'], ['00:00', '06:00'], ['22:30', '05:00']],
                        ];
                        [$start, $end] = $ranges[$shift][($i + $offset + 100) % 4];
                        $a->applyTimes($start, $end);
                        if ($a->overlapping()) {
                            continue;
                        }

                        if ($offset < 0) {
                            // past days: realistic clockings, mostly signed
                            if (($offset + $i) % 9 === 0) {
                                $a->absent = true;
                            } else {
                                $a->clock_in_at = $a->planned_start->addMinutes(random_int(-10, 12));
                                $a->clock_out_at = $a->planned_end->addMinutes(random_int(-5, 25));
                                if (($offset + $i) % 5 !== 0) {
                                    $a->signature = $this->fakeSignature();
                                    $a->signed_at = $a->clock_out_at->addMinutes(2);
                                }
                            }
                        }

                        $a->save();
                    }
                }
            }
        }
    }

    private function fakeSignature(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="160" viewBox="0 0 400 160">'
            .'<path d="M20 110 C 60 20, 90 150, 130 80 S 190 30, 220 100 S 300 140, 380 60" fill="none" stroke="#111" stroke-width="3"/></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
