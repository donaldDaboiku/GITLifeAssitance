<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OccurrenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/devices', [DeviceController::class, 'store']);
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{reminderNotification}/read', [NotificationController::class, 'read']);

    Route::apiResource('activities', ActivityController::class);

    Route::post('/occurrences/{occurrence}/pay', [OccurrenceController::class, 'pay']);
    Route::post('/occurrences/{occurrence}/complete', [OccurrenceController::class, 'complete']);
    Route::post('/occurrences/{occurrence}/skip', [OccurrenceController::class, 'skip']);
    Route::post('/occurrences/{occurrence}/snooze', [OccurrenceController::class, 'snooze']);
});
