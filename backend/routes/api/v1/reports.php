<?php

use App\Http\Controllers\Api\V1\StockReportController;
use Illuminate\Support\Facades\Route;

// Separo los reportes en su grupo porque el §7.11 les exige el permiso
// `reports.view` (no el `inventory.view` del kardex): el Auditor lee
// reportes sin confirmar documentos, y el Operador confirma sin ver
// reportes, así que mezclarlos rompería la matriz de security-design.md.
Route::middleware(['auth:sanctum', 'account.active', 'permission:reports.view'])
    ->group(function (): void {
        Route::get('/reports/stock', [StockReportController::class, 'index']);
    });
