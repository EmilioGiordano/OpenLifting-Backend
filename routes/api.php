<?php

use App\Http\Controllers\Api\AthleteProfileController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Throttle limits are dev-friendly. Phase 7 (hardening) will move these
// to config/named rate limiters with stricter prod values.
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:60,60');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'role:athlete'])->group(function () {
    Route::prefix('athlete')->group(function () {
        Route::get('profile', [AthleteProfileController::class, 'show']);
        Route::post('profile', [AthleteProfileController::class, 'store']);
        Route::patch('profile', [AthleteProfileController::class, 'update']);
        Route::post('mvc', [AthleteProfileController::class, 'storeMvc']);
    });

    Route::get('sessions', [SessionController::class, 'index']);
    Route::post('sessions', [SessionController::class, 'store']);
    Route::patch('sessions/{session}', [SessionController::class, 'patch']);
    Route::put('sessions/{session}/end', [SessionController::class, 'end']);

    Route::post('sessions/{session}/sets', [SetController::class, 'store']);
});
