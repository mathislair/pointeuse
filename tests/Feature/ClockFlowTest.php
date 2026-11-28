<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Restaurant;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function plan(string $start = '17:30', string $end = '23:30', ?string $date = null): Assignment
    {
        $restaurant = Restaurant::create(['name' => 'Villejuif', 'slug' => 'villejuif']);
        $worker = Worker::create(['first_name' => 'Léa', 'last_name' => 'Martin']);

        $res = $this->postJson('/api/assignments', [
            'restaurant_id' => $restaurant->id,
            'worker_id' => $worker->id,
            'date' => $date ?? CarbonImmutable::now()->toDateString(),
            'start' => $start,
            'end' => $end,
        ])->assertCreated();

        return Assignment::find($res->json('id'));
    }

    public function test_shift_is_deduced_from_the_planned_range(): void
    {
        $a = $this->plan('23:30', '06:00', '2026-11-15');
        $this->assertSame('close', $a->shift);
        $this->assertSame('2026-11-15 23:30', $a->planned_start->format('Y-m-d H:i'));
        $this->assertSame('2026-11-16 06:00', $a->planned_end->format('Y-m-d H:i'));

        $cases = [
            ['09:00', '12:00', 'open'],
            ['16:00', '21:00', 'swing'],   // spills over open, mostly swing
            ['22:00', '03:00', 'close'],
            ['00:30', '05:00', 'close'],   // early morning = end of the service day
            ['11:00', '19:00', 'open'],
        ];
        foreach ($cases as [$start, $end, $expected]) {
            $this->putJson("/api/assignments/{$a->id}", ['start' => $start, 'end' => $end])->assertOk()->assertJsonPath('shift', $expected);
        }
        $this->putJson("/api/assignments/{$a->id}", ['start' => '00:30', 'end' => '05:00'])->assertOk();
        $this->assertSame('2026-11-16 00:30', $a->fresh()->planned_start->format('Y-m-d H:i'));

        $this->postJson('/api/assignments', ['restaurant_id' => 1, 'worker_id' => 1, 'date' => '2026-11-21', 'start' => '9h', 'end' => '12:00'])->assertUnprocessable();
        $this->postJson('/api/assignments', ['restaurant_id' => 1, 'worker_id' => 1, 'date' => '2026-11-21', 'start' => '09:00'])->assertUnprocessable();
    }

    public function test_a_worker_cannot_be_planned_twice_at_the_same_time(): void
    {
        $a = $this->plan('09:00', '15:00', '2026-11-15');
        $other = Restaurant::create(['name' => 'Kremlin', 'slug' => 'kremlin']);

        $this->postJson('/api/assignments', ['restaurant_id' => $other->id, 'worker_id' => $a->worker_id, 'date' => '2026-11-15', 'start' => '14:00', 'end' => '20:00'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.worker_id.0', 'Cet intérimaire est déjà planifié de 09:00 à 15:00 chez Villejuif.');

        // back to back is fine, and so is a second range later the same day
        $this->postJson('/api/assignments', ['restaurant_id' => $other->id, 'worker_id' => $a->worker_id, 'date' => '2026-11-15', 'start' => '15:00', 'end' => '20:00'])->assertCreated();
    }

    public function test_worker_clocks_in_out_and_signs(): void
    {
        CarbonImmutable::setTestNow('2026-11-15 17:25:00');
        $a = $this->plan('17:30', '23:30', '2026-11-15');

        $this->getJson('/api/pointeuse/villejuif/day')
            ->assertOk()
            ->assertJsonPath('date', '2026-11-15')
            ->assertJsonPath('assignments.0.status', 'planned');

        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-in")->assertOk()->assertJsonPath('status', 'present');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-in")->assertUnprocessable();

        // cannot sign before clocking out
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/sign", ['signature' => self::SIGNATURE])->assertUnprocessable();

        CarbonImmutable::setTestNow('2026-11-15 23:40:00');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-out")
            ->assertOk()
            ->assertJsonPath('status', 'to_sign')
            ->assertJsonPath('worked_minutes', 375);

        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/sign", ['signature' => self::SIGNATURE])
            ->assertOk()
            ->assertJsonPath('status', 'validated')
            ->assertJsonPath('has_signature', true);

        $this->getJson("/api/assignments/{$a->id}/signature")->assertJsonPath('signature', self::SIGNATURE);
    }

    public function test_service_day_before_7am_is_the_previous_day(): void
    {
        CarbonImmutable::setTestNow('2026-11-16 02:00:00');
        $this->plan('23:30', '06:00', '2026-11-15');

        $this->getJson('/api/pointeuse/villejuif/day')->assertJsonPath('date', '2026-11-15')->assertJsonCount(1, 'assignments');
    }

    public function test_admin_correction_resets_the_signature_and_recap_sums_hours(): void
    {
        CarbonImmutable::setTestNow('2026-11-15 09:00:00');
        $a = $this->plan('09:00', '17:30', '2026-11-15');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-in");
        CarbonImmutable::setTestNow('2026-11-15 17:30:00');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-out");
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/sign", ['signature' => self::SIGNATURE]);

        $this->putJson("/api/assignments/{$a->id}", ['clock_out_at' => '2026-11-15T18:00:00+01:00'])
            ->assertOk()
            ->assertJsonPath('status', 'to_sign')
            ->assertJsonPath('has_signature', false)
            ->assertJsonPath('worked_minutes', 540);

        $this->getJson('/api/recap?month=2026-11')
            ->assertOk()
            ->assertJsonPath('totals.minutes', 540)
            ->assertJsonPath('totals.shifts', 1)
            ->assertJsonPath('totals.unsigned', 1)
            ->assertJsonPath('workers.0.worker.full_name', 'Léa Martin');

        $this->get('/api/recap/export?month=2026-11')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_walk_in_plans_from_now_on_the_current_shift_and_clocks_in(): void
    {
        CarbonImmutable::setTestNow('2026-11-15 18:00:00');
        $restaurant = Restaurant::create(['name' => 'Kremlin', 'slug' => 'kremlin']);
        $worker = Worker::create(['first_name' => 'Karim', 'last_name' => 'Benali']);

        $res = $this->postJson('/api/pointeuse/kremlin/walk-in', ['worker_id' => $worker->id])
            ->assertCreated()
            ->assertJsonPath('status', 'present')
            ->assertJsonPath('shift', 'swing')
            ->assertJsonPath('date', '2026-11-15');
        $a = Assignment::find($res->json('id'));
        $this->assertSame('18:00', $a->planned_start->format('H:i'));
        $this->assertNull($a->planned_end, 'a manual clocking has no planned end by default');

        // still in post → refused
        $this->postJson('/api/pointeuse/kremlin/walk-in', ['worker_id' => $worker->id])->assertUnprocessable();

        // while in post the open slot counts as the rest of its shift: planning them elsewhere now is refused…
        $other = Restaurant::create(['name' => 'Villejuif', 'slug' => 'villejuif']);
        $this->postJson('/api/assignments', ['restaurant_id' => $other->id, 'worker_id' => $worker->id, 'date' => '2026-11-15', 'start' => '20:00', 'end' => '23:00'])
            ->assertUnprocessable()->assertJsonPath('errors.worker_id.0', 'Cet intérimaire est déjà en poste depuis 18:00 chez Kremlin.');
        // …but tomorrow is fine
        $this->postJson('/api/assignments', ['restaurant_id' => $other->id, 'worker_id' => $worker->id, 'date' => '2026-11-16', 'start' => '09:00', 'end' => '15:00'])->assertCreated();

        // the admin can fix the departure without an end being invented
        $this->putJson("/api/assignments/{$a->id}", ['clock_out_at' => '2026-11-15T21:10:00Z'])->assertOk()
            ->assertJsonPath('planned_end', null)->assertJsonPath('worked_minutes', 250)->assertJsonPath('clock_out_at', '2026-11-15T22:10:00+01:00');
        $this->assertSame('22:10', $a->fresh()->clock_out_at->format('H:i'), 'UTC input is stored as Paris wall-clock time');
        // and once clocked out, the slot ends at the departure: the same evening is free again
        $this->postJson('/api/assignments', ['restaurant_id' => $other->id, 'worker_id' => $worker->id, 'date' => '2026-11-15', 'start' => '22:30', 'end' => '23:30'])->assertCreated();

        // early morning belongs to the previous service day and the close shift
        CarbonImmutable::setTestNow('2026-11-16 01:00:00');
        $other = Worker::create(['first_name' => 'Awa', 'last_name' => 'Diallo']);
        $this->postJson('/api/pointeuse/kremlin/walk-in', ['worker_id' => $other->id])
            ->assertCreated()->assertJsonPath('shift', 'close')->assertJsonPath('date', '2026-11-15');
    }

    public function test_walk_in_clocks_an_existing_planned_shift(): void
    {
        CarbonImmutable::setTestNow('2026-11-15 09:05:00');
        $a = $this->plan('09:00', '15:00', '2026-11-15');

        $this->postJson('/api/pointeuse/villejuif/walk-in', ['worker_id' => $a->worker_id])
            ->assertCreated()->assertJsonPath('id', $a->id)->assertJsonPath('status', 'present');
        $this->assertSame(1, Assignment::count());
    }
}
