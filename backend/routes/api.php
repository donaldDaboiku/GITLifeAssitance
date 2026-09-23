<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OccurrenceController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShoppingController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/token-login', [AuthController::class, 'tokenLogin']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/devices', [DeviceController::class, 'index']);
    Route::post('/devices', [DeviceController::class, 'store']);
    Route::put('/devices/{device}', [DeviceController::class, 'update']);
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy']);

    Route::post('/sync/push', [SyncController::class, 'push']);
    Route::post('/sync/pull', [SyncController::class, 'pull']);

    Route::middleware('throttle:ai')->group(function () {
        Route::post('/assistant/parse', [AssistantController::class, 'parse']);
        Route::post('/assistant/confirm', [AssistantController::class, 'confirm']);
        Route::post('/assistant/ask', [AssistantController::class, 'ask']);
        Route::post('/assistant/transcribe', [AssistantController::class, 'transcribe']);
    });

    Route::get('/preferences', [PreferenceController::class, 'show']);
    Route::put('/preferences', [PreferenceController::class, 'update']);
    Route::get('/suggestions', [SuggestionController::class, 'index']);
    Route::post('/suggestions/confirm', [SuggestionController::class, 'confirm']);

    Route::get('/privacy/notice', [PrivacyController::class, 'notice']);
    Route::post('/privacy/accept', [PrivacyController::class, 'accept']);
    Route::get('/privacy/export', [PrivacyController::class, 'export']);
    Route::delete('/privacy/account', [PrivacyController::class, 'destroy']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/search', SearchController::class);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{reminderNotification}/read', [NotificationController::class, 'read']);

    Route::apiResource('contacts', ContactController::class);
    Route::get('/shopping-lists', [ShoppingController::class, 'index']);
    Route::post('/shopping-lists', [ShoppingController::class, 'store']);
    Route::get('/shopping-lists/{shoppingList}', [ShoppingController::class, 'show']);
    Route::delete('/shopping-lists/{shoppingList}', [ShoppingController::class, 'destroy']);
    Route::post('/shopping-lists/{shoppingList}/items', [ShoppingController::class, 'addItem']);
    Route::put('/shopping-items/{shoppingItem}', [ShoppingController::class, 'updateItem']);

    Route::apiResource('activities', ActivityController::class);

    Route::post('/occurrences/{occurrence}/pay', [OccurrenceController::class, 'pay']);
    Route::post('/occurrences/{occurrence}/complete', [OccurrenceController::class, 'complete']);
    Route::post('/occurrences/{occurrence}/skip', [OccurrenceController::class, 'skip']);
    Route::post('/occurrences/{occurrence}/snooze', [OccurrenceController::class, 'snooze']);
    Route::post('/activities/{activity}/visit-follow-ups', [OccurrenceController::class, 'visitFollowUps']);
});
