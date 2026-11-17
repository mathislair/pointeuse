<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class Shifts
{
    /** @return array<string, array{label:string,start:string,end:string,color:string}> */
    public static function all(): array
    {
        return config('pointeuse.shifts');
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Start/end datetimes of a shift for a given service day.
     * The close shift ends on the next calendar day.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function window(CarbonInterface $date, string $shift, ?string $start = null, ?string $end = null): array
    {
        $def = self::all()[$shift] ?? throw new \InvalidArgumentException("Unknown shift [$shift]");

        return self::range($date, $start ?? $def['start'], $end ?? $def['end']);
    }

    /**
     * Start/end datetimes for arbitrary "HH:MM" times on a service day.
     * Times before day_start_hour belong to the early morning of the next
     * calendar day (end of the close shift), and an end at or before the
     * start also rolls over to the next day.
     *
     * A missing end gives an open-ended range (walk-ins from the tablet).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable|null}
     */
    public static function range(CarbonInterface $date, string $start, ?string $end = null): array
    {
        $day = CarbonImmutable::parse($date->toDateString());
        $dayStart = (int) config('pointeuse.day_start_hour', 7);

        $from = $day->setTimeFromTimeString($start);
        if ($from->hour < $dayStart) {
            $from = $from->addDay();
        }
        if ($end === null) {
            return [$from, null];
        }
        $to = $from->setTimeFromTimeString($end);
        if ($to->lessThanOrEqualTo($from)) {
            $to = $to->addDay();
        }

        return [$from, $to];
    }

    /**
     * The shift in progress at a given instant (or the next one to start when
     * nothing is running, e.g. a worker showing up at 8h for the open).
     */
    public static function current(CarbonInterface $at): string
    {
        $at = CarbonImmutable::instance($at);
        $date = self::serviceDate($at);
        $next = null;
        foreach (self::keys() as $key) {
            [$s, $e] = self::window($date, $key);
            if ($at->greaterThanOrEqualTo($s) && $at->lessThan($e)) {
                return $key;
            }
            if ($at->lessThan($s) && $next === null) {
                $next = $key;
            }
        }

        return $next ?? array_key_last(self::all());
    }

    /**
     * The shift a planned range belongs to: the one it overlaps the most
     * (a range may well spill over the shift boundaries).
     */
    public static function detect(CarbonInterface $date, CarbonInterface $start, CarbonInterface $end): string
    {
        $best = self::keys()[0];
        $bestOverlap = PHP_INT_MIN;
        foreach (self::keys() as $key) {
            [$s, $e] = self::window($date, $key);
            $overlap = min($end->getTimestamp(), $e->getTimestamp()) - max($start->getTimestamp(), $s->getTimestamp());
            if ($overlap > $bestOverlap) {
                $bestOverlap = $overlap;
                $best = $key;
            }
        }

        return $best;
    }

    /**
     * The "service day" a given instant belongs to: before day_start_hour we
     * are still finishing the previous day's close shift.
     */
    public static function serviceDate(CarbonInterface $at): CarbonImmutable
    {
        $at = CarbonImmutable::instance($at);
        $dayStart = (int) config('pointeuse.day_start_hour', 7);

        return $at->hour < $dayStart ? $at->subDay()->startOfDay() : $at->startOfDay();
    }
}
