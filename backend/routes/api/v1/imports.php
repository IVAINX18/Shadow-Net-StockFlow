<?php

use App\Http\Controllers\Api\V1\CatalogImportController;
use Illuminate\Support\Facades\Route;

// Agrupo la importación de catálogo (EP-012) bajo su propio archivo porque el
// §7.13 le exige el permiso `catalog.create` en los tres endpoints: no creo
// slugs nuevos y el alcance por almacén no aplica aquí (el template no trae
// mínimos por almacén; eso lo posee la carga inicial de una fase posterior).
Route::middleware(['auth:sanctum', 'account.active', 'permission:catalog.create'])
    ->group(function (): void {
        Route::get('/imports/catalog/template', [CatalogImportController::class, 'template']);
        Route::post('/imports/catalog/validate', [CatalogImportController::class, 'validate']);
        Route::post('/imports/catalog', [CatalogImportController::class, 'store']);
    });
