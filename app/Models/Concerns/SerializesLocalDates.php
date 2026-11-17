<?php

namespace App\Models\Concerns;

use DateTimeInterface;

/** Serialize dates as ISO 8601 with the app timezone offset instead of UTC "Z". */
trait SerializesLocalDates
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DateTimeInterface::ATOM);
    }
}
