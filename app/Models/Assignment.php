<?php

namespace App\Models;

use App\Support\Shifts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    use SerializesLocalDates;

    protected $fillable = [
        'restaurant_id', 'worker_id', 'date', 'shift', 'planned_start', 'planned_end',
        'clock_in_at', 'clock_out_at', 'signature', 'signed_at', 'absent',
        'admin_note', 'edited_at',
    ];

    protected $hidden = ['signature'];

    protected $appends = ['status', 'worked_minutes', 'has_signature'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'planned_start' => 'datetime',
            'planned_end' => 'datetime',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'signed_at' => 'datetime',
            'edited_at' => 'datetime',
            'absent' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /**
     * Plan the worker from "HH:MM" to "HH:MM" on the service day; the shift
     * (open / swing / close) is deduced from the range, never chosen by hand.
     * Without an end the slot is open-ended (manual clocking): the shift is
     * the one in progress at the start, and the departure will be the end.
     */
    public function applyTimes(string $start, ?string $end = null): static
    {
        $date = CarbonImmutable::parse($this->date);
        [$from, $to] = Shifts::range($date, $start, $end);
        $this->planned_start = $from;
        $this->planned_end = $to;
        $this->shift = $to ? Shifts::detect($date, $from, $to) : Shifts::current($from);

        return $this;
    }

    /**
     * Where the slot ends for overlap checks: the planned end, else the actual
     * departure, else (still in post, open-ended) the end of its shift window.
     */
    public function effectiveEnd(): CarbonImmutable
    {
        if ($this->planned_end) {
            return CarbonImmutable::instance($this->planned_end);
        }
        if ($this->clock_out_at) {
            return CarbonImmutable::instance($this->clock_out_at);
        }
        [, $end] = Shifts::window(CarbonImmutable::parse($this->date), $this->shift);
        $start = CarbonImmutable::instance($this->planned_start);

        return $end->greaterThan($start) ? $end : $start->addHour();
    }

    /** Hours after the end of the slot before a forgotten departure is closed automatically. */
    public const FORGOTTEN_GRACE_HOURS = 4;

    /**
     * Close the clockings whose departure was never punched: once the slot has
     * been over for FORGOTTEN_GRACE_HOURS, the departure is set to the end of
     * the slot with an admin note, so the hours show up (unsigned) in the recap
     * instead of staying "present" forever. The admin can still correct them.
     *
     * @return int number of clockings closed
     */
    public static function closeForgotten(?CarbonImmutable $now = null): int
    {
        $now = $now ?? CarbonImmutable::now();
        $closed = 0;
        $open = static::whereNotNull('clock_in_at')->whereNull('clock_out_at')
            ->where('clock_in_at', '<', $now->subHours(self::FORGOTTEN_GRACE_HOURS))
            ->get();
        foreach ($open as $a) {
            $end = $a->effectiveEnd();
            if ($end->addHours(self::FORGOTTEN_GRACE_HOURS)->greaterThan($now)) {
                continue;
            }
            if ($end->lessThanOrEqualTo($a->clock_in_at)) {
                $end = CarbonImmutable::instance($a->clock_in_at)->addHour();
            }
            $note = sprintf('Départ non pointé : clôturé automatiquement à %s (fin du créneau), à vérifier.', $end->format('H:i'));
            $a->forceFill([
                'clock_out_at' => $end,
                'admin_note' => trim(($a->admin_note ? $a->admin_note.' — ' : '').$note),
                'edited_at' => $now,
            ])->save();
            $closed++;
        }

        return $closed;
    }

    /** Another planned range for the same worker that overlaps this one (any restaurant). */
    public function overlapping(): ?Assignment
    {
        $end = $this->effectiveEnd();

        return static::with('restaurant')
            ->where('worker_id', $this->worker_id)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
            ->where('absent', false)
            ->where('planned_start', '<', $end)
            ->where(fn ($q) => $q->where('planned_end', '>', $this->planned_start)->orWhereNull('planned_end'))
            ->orderBy('planned_start')
            ->get()
            ->first(fn (Assignment $other) => $other->effectiveEnd()->greaterThan($this->planned_start));
    }

    /** Planned "HH:MM" times, handy to re-apply them when only the date moves. */
    public function plannedTimes(): array
    {
        return [$this->planned_start?->format('H:i'), $this->planned_end?->format('H:i')];
    }

    /**
     * planned → present (clocked in) → to_sign (clocked out) → validated (signed).
     * "absent" is set by the admin.
     */
    public function getStatusAttribute(): string
    {
        if ($this->absent) {
            return 'absent';
        }
        if (! $this->clock_in_at) {
            return 'planned';
        }
        if (! $this->clock_out_at) {
            return 'present';
        }

        return $this->signed_at ? 'validated' : 'to_sign';
    }

    public function getWorkedMinutesAttribute(): ?int
    {
        if (! $this->clock_in_at || ! $this->clock_out_at) {
            return null;
        }

        return max(0, (int) $this->clock_in_at->diffInMinutes($this->clock_out_at));
    }

    public function getHasSignatureAttribute(): bool
    {
        return ! empty($this->signature);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
