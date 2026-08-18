<?php

use App\Http\Controllers\Api\BookingApiController;
use App\Http\Controllers\Api\ResourcePoolApiController;
use Illuminate\Support\Facades\Route;

// Internal API for the app's own UI (and future integrations). Shares the
// standard web session — never a public/unauthenticated surface, per the
// brief's privacy requirements.
Route::middleware(['web', 'auth', 'throttle:60,1'])->group(function () {
    Route::get('/resource-pools', [ResourcePoolApiController::class, 'index']);
    Route::get('/resource-pools/{resourcePool:slug}/availability', [ResourcePoolApiController::class, 'availability']);
    Route::get('/bookings', [BookingApiController::class, 'index']);
    Route::post('/bookings', [BookingApiController::class, 'store']);
    Route::get('/bookings/{booking:reference}', [BookingApiController::class, 'show']);
});
