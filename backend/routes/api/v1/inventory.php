<?php

use App\Http\Controllers\Api\V1\InventoryDocumentController;
use App\Http\Controllers\Api\V1\KardexController;
use Illuminate\Support\Facades\Route;

// Separo lectura y escritura en grupos porque cada endpoint exige su propio
// permiso del §7.8: las lecturas piden `inventory.view` y las escrituras de
// borrador `inventory.create`. La confirmación exige `inventory.confirm` y la
// reversión `inventory.reverse`, cada una con alcance sobre todos los
// almacenes implicados (el controlador lo verifica). La edición del borrador
// (PUT, RF-053) y su descarte (cancel) usan `inventory.create` porque operan
// sobre borradores sin efectos en existencias, coherente con el permiso que
// los crea.
Route::middleware(['auth:sanctum', 'account.active', 'permission:inventory.view'])
    ->group(function (): void {
        Route::get('/inventory-documents', [InventoryDocumentController::class, 'index']);
        Route::get('/inventory-documents/{documento}', [InventoryDocumentController::class, 'show']);
        // El kardex es la lectura del libro por par producto-almacén (§7.9):
        // vive en el grupo de lectura porque exige `inventory.view` y el
        // alcance se verifica contra el almacén pedido en el controlador.
        Route::get('/inventory/kardex', [KardexController::class, 'index']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:inventory.create'])
    ->group(function (): void {
        Route::post('/inventory-documents', [InventoryDocumentController::class, 'store']);
        Route::put('/inventory-documents/{documento}', [InventoryDocumentController::class, 'update']);
        Route::post('/inventory-documents/{documento}/cancel', [InventoryDocumentController::class, 'cancel']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:inventory.confirm'])
    ->group(function (): void {
        Route::post('/inventory-documents/{documento}/confirm', [InventoryDocumentController::class, 'confirm']);
    });

Route::middleware(['auth:sanctum', 'account.active', 'permission:inventory.reverse'])
    ->group(function (): void {
        Route::post('/inventory-documents/{documento}/reverse', [InventoryDocumentController::class, 'reverse']);
    });
