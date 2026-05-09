<?php

use App\Http\Controllers\Api\AuthController;
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
