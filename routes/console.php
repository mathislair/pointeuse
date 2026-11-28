<?php

use Illuminate\Support\Facades\Schedule;

// Forgotten departures are also closed lazily when the tablets load their day,
// the schedule only covers quiet periods. Run `php artisan schedule:work` in dev.
Schedule::command('pointeuse:close-forgotten')->hourly();
Schedule::command('pointeuse:backup')->dailyAt('05:30');
