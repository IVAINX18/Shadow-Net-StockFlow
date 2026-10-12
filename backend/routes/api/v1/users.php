<?php

use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Separo lectura, creación y actualización en grupos porque cada endpoint
// exige su propio permiso del §7.2; las cuentas son globales y la Policy no
// verifica alcance por almacén, solo el permiso RBAC.
Route::middleware(['auth:sanctum', 'account.active', 'permission:users.view'])
    ->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:users.create'])
    ->group(function (): void {
        Route::post('/users', [UserController::class, 'store']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:users.update'])
    ->group(function (): void {
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/activate', [UserController::class, 'activate']);
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate']);
    });
