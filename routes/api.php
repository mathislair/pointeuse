<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\ClockController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\RecapController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\WorkerController;
use Illuminate\Support\Facades\Route;

Route::get('config', ConfigController::class);

Route::apiResource('restaurants', RestaurantController::class);
Route::apiResource('workers', WorkerController::class);
Route::apiResource('assignments', AssignmentController::class);

// Pointeuse (time clock) – per restaurant
Route::get('pointeuse/{restaurant:slug}/day', [ClockController::class, 'day']);
Route::post('pointeuse/{restaurant:slug}/walk-in', [ClockController::class, 'walkIn']);
Route::post('pointeuse/{restaurant:slug}/assignments/{assignment}/clock-in', [ClockController::class, 'clockIn']);
Route::post('pointeuse/{restaurant:slug}/assignments/{assignment}/clock-out', [ClockController::class, 'clockOut']);
Route::post('pointeuse/{restaurant:slug}/assignments/{assignment}/sign', [ClockController::class, 'sign']);
Route::get('assignments/{assignment}/signature', [ClockController::class, 'signature']);

// Admin recap
Route::get('recap', [RecapController::class, 'month']);
Route::get('recap/export', [RecapController::class, 'export']);
