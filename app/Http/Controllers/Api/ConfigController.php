<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Shifts;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $now = CarbonImmutable::now();

        return response()->json([
            'app_name' => config('app.name'),
            'shifts' => Shifts::all(),
            'day_start_hour' => (int) config('pointeuse.day_start_hour'),
            'now' => $now->toIso8601String(),
            'service_date' => Shifts::serviceDate($now)->toDateString(),
            'timezone' => config('app.timezone'),
        ]);
    }
}
