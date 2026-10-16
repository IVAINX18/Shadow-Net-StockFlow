<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

// Mantengo la v1 en archivos por módulo para que cada unidad añada el suyo sin
// reabrir este registro, como ordena backend-architecture.md §3 y §4.
Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);

    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/warehouses.php';
    require __DIR__.'/api/v1/users.php';
    require __DIR__.'/api/v1/roles.php';
    require __DIR__.'/api/v1/catalog.php';
    require __DIR__.'/api/v1/imports.php';
    require __DIR__.'/api/v1/inventory.php';
    require __DIR__.'/api/v1/reports.php';
});
