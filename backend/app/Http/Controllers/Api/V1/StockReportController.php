<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OutsideWarehouseScopeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StockReportRequest;
use App\Http\Resources\StockResource;
use App\Services\Inventory\InventoryQueries;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Expongo el reporte de existencias del §7.11 con sus tres filtros
// (almacén, categoría y bajo mínimo): sin `below_min` es el listado general
// de existencias (RF-051/RF-091) y con `below_min=true` es el reporte de
// faltantes que el tablero anuncia (RF-080/RF-104). Solo lectura, con el
// permiso `reports.view` y el alcance resuelto en la consulta.
class StockReportController extends Controller
{
    public function __construct(
        private readonly InventoryQueries $consultas,
    ) {}

    public function index(StockReportRequest $request): AnonymousResourceCollection
    {
        $datos = $request->validated();
        $alcance = $request->user()->accessibleWarehouseIds();

        // Si pide un almacén concreto fuera de su alcance, rebota con 403
        // FUERA_DE_ALCANCE: la regla exists ya garantizó que el almacén
        // existe, así que aquí solo decido visibilidad (RF-097).
        if (isset($datos['warehouse_id']) && ! in_array((int) $datos['warehouse_id'], $alcance, true)) {
            throw new OutsideWarehouseScopeException;
        }

        $resultado = $this->consultas->stock(
            $alcance,
            [
                'warehouse_id' => isset($datos['warehouse_id']) ? (int) $datos['warehouse_id'] : null,
                'category_id' => isset($datos['category_id']) ? (int) $datos['category_id'] : null,
                // Leo el booleano con `boolean()` porque la cadena "false" es
                // truthy en PHP: sin esto, `?below_min=false` activaría el
                // filtro de faltantes justo al revés de lo pedido.
                'below_min' => $request->boolean('below_min'),
            ],
            (int) ($datos['per_page'] ?? 15),
        );

        // Adjunto los totales del conjunto filtrado completo (no de la
        // página) para que el detalle cuadre con el total general (RF-102,
        // AC-047) sin romper la forma paginada del §4.
        return StockResource::collection($resultado['paginator'])
            ->additional(['totals' => $resultado['totals']]);
    }
}
