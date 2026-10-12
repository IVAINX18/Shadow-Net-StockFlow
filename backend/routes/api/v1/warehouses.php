<?php

use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

// Separo lectura y escritura en grupos porque cada endpoint exige su propio
// permiso del §7.4; el alcance por almacén no va en middleware, lo verifica la
// Policy en cada acción para distinguir SIN_PERMISO de FUERA_DE_ALCANCE.
Route::middleware(['auth:sanctum', 'account.active', 'permission:warehouses.view'])
    ->group(function (): void {
        Route::get('/warehouses', [WarehouseController::class, 'index']);
        Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show']);
        Route::get('/warehouses/{warehouse}/users', [WarehouseController::class, 'users']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:warehouses.create'])
    ->group(function (): void {
        Route::post('/warehouses', [WarehouseController::class, 'store']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:warehouses.update'])
    ->group(function (): void {
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update']);
        Route::post('/warehouses/{warehouse}/deactivate', [WarehouseController::class, 'deactivate']);
        Route::post('/warehouses/{warehouse}/users', [WarehouseController::class, 'attachUsers']);
        Route::delete('/warehouses/{warehouse}/users/{user}', [WarehouseController::class, 'detachUser']);
    });
