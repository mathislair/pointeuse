<?php

return [
    /*
     * The three daily shifts. "end" earlier than "start" means the shift
     * finishes on the following calendar day (the close shift).
     */
    'shifts' => [
        'open' => ['label' => 'Open', 'start' => '09:00', 'end' => '17:30', 'color' => '#f59e0b'],
        'swing' => ['label' => 'Swing', 'start' => '17:30', 'end' => '23:30', 'color' => '#0ea5e9'],
        'close' => ['label' => 'Close', 'start' => '23:30', 'end' => '06:00', 'color' => '#8b5cf6'],
    ],

    /*
     * Hour at which a "service day" starts. Anything happening before this
     * hour belongs to the previous day's service (the close shift ends at 6h).
     */
    'day_start_hour' => 7,
];
