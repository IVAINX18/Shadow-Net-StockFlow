<?php

use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use Illuminate\Support\Facades\Route;

// Separo lectura, creación y actualización en grupos porque cada endpoint
// exige su propio permiso del §7.3; el catálogo de permisos viaja con
// `roles.view` porque solo existe para construir el formulario de roles.
Route::middleware(['auth:sanctum', 'account.active', 'permission:roles.view'])
    ->group(function (): void {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
        Route::get('/permissions', [PermissionController::class, 'index']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:roles.create'])
    ->group(function (): void {
        Route::post('/roles', [RoleController::class, 'store']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:roles.update'])
    ->group(function (): void {
        Route::put('/roles/{role}', [RoleController::class, 'update']);
    });
