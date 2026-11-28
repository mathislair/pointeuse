<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Restaurant;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Guards around clocking: time window, restaurant, idempotence, signature. */
class ClockGuardsTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Restaurant $restaurant;

    private Worker $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::create(['name' => 'Villejuif', 'slug' => 'villejuif']);
        $this->worker = Worker::create(['first_name' => 'Léa', 'last_name' => 'Martin']);
    }

    private function plan(string $start, string $end, string $date, ?Restaurant $restaurant = null): Assignment
    {
        $a = new Assignment(['restaurant_id' => ($restaurant ?? $this->restaurant)->id, 'worker_id' => $this->worker->id, 'date' => $date]);
        $a->applyTimes($start, $end)->save();

        return $a;
    }

    public function test_clock_in_only_around_the_planned_slot(): void
    {
        $a = $this->plan('17:30', '23:30', '2026-11-15');

        CarbonImmutable::setTestNow('2026-11-15 10:00:00');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-in")->assertUnprocessable()
            ->assertJsonPath('errors.clock_in_at.0', 'Ce créneau commence à 17:30 : le pointage est possible 3 h avant.');

        CarbonImmutable::setTestNow('2026-11-16 02:00:00');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-in")->assertUnprocessable()
            ->assertJsonPath('errors.clock_in_at.0', 'Ce créneau est terminé : pointez une arrivée non planifiée.');

        // 3 h before is fine, and so is a late arrival within 2 h after the end
        CarbonImmutable::setTestNow('2026-11-15 14:45:00');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-in")->assertOk();

        $b = $this->plan('09:00', '12:00', '2026-11-17');
        CarbonImmutable::setTestNow('2026-11-17 13:30:00');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$b->id}/clock-in")->assertOk();
    }

    public function test_a_slot_can_only_be_clocked_from_its_own_restaurant(): void
    {
        Restaurant::create(['name' => 'Kremlin', 'slug' => 'kremlin']);
        CarbonImmutable::setTestNow('2026-11-15 17:25:00');
        $a = $this->plan('17:30', '23:30', '2026-11-15');

        $this->postJson("/api/pointeuse/kremlin/assignments/{$a->id}/clock-in")->assertUnprocessable()
            ->assertJsonPath('errors.assignment.0', "Ce créneau n'est pas planifié dans ce restaurant.");
        $this->assertNull($a->fresh()->clock_in_at);
    }

    public function test_clocking_is_idempotent_and_signing_happens_once(): void
    {
        CarbonImmutable::setTestNow('2026-11-15 17:25:00');
        $a = $this->plan('17:30', '23:30', '2026-11-15');
        $url = "/api/pointeuse/villejuif/assignments/{$a->id}";

        $this->postJson("$url/clock-in")->assertOk();
        $this->postJson("$url/clock-in")->assertUnprocessable();
        $this->postJson("$url/sign", ['signature' => self::SIGNATURE])->assertUnprocessable();

        CarbonImmutable::setTestNow('2026-11-15 23:35:00');
        $this->postJson("$url/clock-out")->assertOk();
        $this->postJson("$url/clock-out")->assertUnprocessable();

        // not a PNG → refused, even with the right prefix
        $this->postJson("$url/sign", ['signature' => 'data:image/png;base64,'.base64_encode('<script>alert(1)</script>')])
            ->assertUnprocessable()->assertJsonPath('errors.signature.0', 'La signature doit être une image PNG.');
        $this->postJson("$url/sign", ['signature' => 'data:image/png;base64,%%%not-base64'])->assertUnprocessable();

        $this->postJson("$url/sign", ['signature' => self::SIGNATURE])->assertOk()->assertJsonPath('status', 'validated');
        $signedAt = $a->fresh()->signed_at;

        CarbonImmutable::setTestNow('2026-11-15 23:50:00');
        $this->postJson("$url/sign", ['signature' => self::SIGNATURE])->assertUnprocessable()
            ->assertJsonPath('errors.signature.0', 'Ces heures sont déjà signées. Demandez une correction à votre responsable.');
        $this->assertTrue($signedAt->equalTo($a->fresh()->signed_at));
    }

    public function test_walk_in_refuses_a_worker_still_in_post_since_a_previous_day(): void
    {
        CarbonImmutable::setTestNow('2026-11-15 23:40:00');
        $a = $this->plan('23:30', '06:00', '2026-11-15');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-in")->assertOk();

        // next service day, departure never punched (within the grace period)
        CarbonImmutable::setTestNow('2026-11-16 08:00:00');
        Restaurant::create(['name' => 'Kremlin', 'slug' => 'kremlin']);
        $this->postJson('/api/pointeuse/kremlin/walk-in', ['worker_id' => $this->worker->id])->assertUnprocessable()
            ->assertJsonPath('errors.worker_id.0', 'Cet intérimaire est déjà en poste.');
        // and the tablet of the restaurant still shows them, to punch the departure
        $this->getJson('/api/pointeuse/villejuif/day')->assertJsonPath('date', '2026-11-16')->assertJsonCount(1, 'hanging');
    }

    public function test_forgotten_departures_are_closed_at_the_end_of_the_slot(): void
    {
        CarbonImmutable::setTestNow('2026-11-15 17:25:00');
        $a = $this->plan('17:30', '23:30', '2026-11-15');
        $this->postJson("/api/pointeuse/villejuif/assignments/{$a->id}/clock-in")->assertOk();

        // 2 h after the end: still open (grace period)
        CarbonImmutable::setTestNow('2026-11-16 01:30:00');
        $this->getJson('/api/pointeuse/villejuif/day')->assertOk();
        $this->assertNull($a->fresh()->clock_out_at);

        CarbonImmutable::setTestNow('2026-11-16 03:31:00');
        $this->getJson('/api/pointeuse/villejuif/day')->assertOk()->assertJsonCount(0, 'hanging');
        $a->refresh();
        $this->assertSame('2026-11-15 23:30', $a->clock_out_at->format('Y-m-d H:i'));
        $this->assertSame('to_sign', $a->status);
        $this->assertStringContainsString('clôturé automatiquement à 23:30', $a->admin_note);
        $this->assertNotNull($a->edited_at);

        // open-ended walk-in: closed at the end of its shift window
        CarbonImmutable::setTestNow('2026-11-17 18:00:00');
        $w = Worker::create(['first_name' => 'Karim', 'last_name' => 'Benali']);
        $this->postJson('/api/pointeuse/villejuif/walk-in', ['worker_id' => $w->id])->assertCreated();
        CarbonImmutable::setTestNow('2026-11-18 04:00:00');
        $this->assertSame(1, Assignment::closeForgotten());
        $this->assertSame('2026-11-17 23:30', Assignment::where('worker_id', $w->id)->first()->clock_out_at->format('Y-m-d H:i'));

        $this->getJson('/api/recap?month=2026-11')->assertJsonPath('totals.minutes', 365 + 330)->assertJsonPath('totals.unsigned', 2);
    }

    public function test_admin_note_does_not_erase_the_signature_nor_mark_a_correction(): void
    {
        CarbonImmutable::setTestNow('2026-11-15 17:32:17');
        $a = $this->plan('17:30', '23:30', '2026-11-15');
        $url = "/api/pointeuse/villejuif/assignments/{$a->id}";
        $this->postJson("$url/clock-in")->assertOk();
        CarbonImmutable::setTestNow('2026-11-15 23:41:49');
        $this->postJson("$url/clock-out")->assertOk();
        $this->postJson("$url/sign", ['signature' => self::SIGNATURE])->assertOk();

        // what the admin form sends when only the note changed: same times, to the minute
        $this->putJson("/api/assignments/{$a->id}", [
            'clock_in_at' => '2026-11-15 17:32', 'clock_out_at' => '2026-11-15 23:41', 'absent' => false, 'admin_note' => 'RAS',
        ])->assertOk()->assertJsonPath('has_signature', true)->assertJsonPath('status', 'validated')->assertJsonPath('edited_at', null);
        $this->assertSame('17:32:17', $a->fresh()->clock_in_at->format('H:i:s'), 'seconds are kept when nothing changed');

        // a real change still resets it
        $this->putJson("/api/assignments/{$a->id}", ['clock_out_at' => '2026-11-15 23:45'])
            ->assertOk()->assertJsonPath('has_signature', false)->assertJsonPath('status', 'to_sign');
        $this->assertNotNull($a->fresh()->edited_at);

        // inconsistent corrections are refused
        $this->putJson("/api/assignments/{$a->id}", ['clock_out_at' => '2026-11-15 17:00'])->assertUnprocessable()
            ->assertJsonPath('errors.clock_out_at.0', "Le départ doit être après l'arrivée.");
        $this->putJson("/api/assignments/{$a->id}", ['clock_in_at' => null])->assertUnprocessable()
            ->assertJsonPath('errors.clock_in_at.0', "Renseignez l'arrivée avant le départ.");
    }

    public function test_day_rejects_an_invalid_date(): void
    {
        $this->getJson('/api/pointeuse/villejuif/day?date=foo')->assertUnprocessable();
    }
}
