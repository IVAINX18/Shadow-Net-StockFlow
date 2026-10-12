<?php

use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

// Exijo sesión, cuenta activa y el permiso de lectura de almacenes en el grupo
// entero porque el §7.4 marca `warehouses.view` como acceso de ambos endpoints;
// el alcance por almacén lo afina después la Policy en cada acción.
Route::middleware(['auth:sanctum', 'account.active', 'permission:warehouses.view'])
    ->group(function (): void {
        Route::get('/warehouses', [WarehouseController::class, 'index']);
        Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show']);
    });
