<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// Agrupo el login tras el limitador para frenar la fuerza bruta (C-03) y el
// resto tras sesión más cuenta activa, como exige el §7.1 de api-design.md.
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/auth/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

Route::get('/auth/me', [AuthController::class, 'me'])
    ->middleware(['auth:sanctum', 'account.active']);
