<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\UnitOfMeasureController;
use Illuminate\Support\Facades\Route;

// Separo lectura y escritura en grupos porque cada endpoint exige su propio
// permiso del §7.5, §7.6 y §7.7: las lecturas piden solo sesión activa y las
// escrituras el permiso `catalog.create` o `catalog.update`. No creo slugs
// nuevos: reutilizo el catálogo existente de PermissionSlug.
Route::middleware(['auth:sanctum', 'account.active'])
    ->group(function (): void {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);
        Route::get('/units', [UnitOfMeasureController::class, 'index']);
        Route::get('/units/{unit}', [UnitOfMeasureController::class, 'show']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::get('/products/{product}/warehouses', [ProductController::class, 'warehouses']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:catalog.create'])
    ->group(function (): void {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::post('/units', [UnitOfMeasureController::class, 'store']);
        Route::post('/products', [ProductController::class, 'store']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:catalog.update'])
    ->group(function (): void {
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::post('/categories/{category}/deactivate', [CategoryController::class, 'deactivate']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::post('/products/{product}/deactivate', [ProductController::class, 'deactivate']);
        // La escritura de la configuración exige además el alcance por almacén,
        // que el controlador verifica contra warehouse_user (403
        // FUERA_DE_ALCANCE fuera de alcance): el middleware solo cubre el
        // permiso porque el alcance depende del almacén de la URL.
        Route::put('/products/{product}/warehouses/{warehouse}', [ProductController::class, 'updateWarehouse']);
    });
