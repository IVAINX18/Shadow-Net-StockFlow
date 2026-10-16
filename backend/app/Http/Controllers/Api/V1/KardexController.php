<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OutsideWarehouseScopeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\KardexRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryQueries;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Mantengo el controlador fino: el Form Request valida la forma, aquí solo
// verifico existencia (404) y alcance (403) y delego la consulta al servicio
// de lectura, que no escribe nada. El orden cronológico con desempate por id
// y los saldos de rango los calcula el servicio (AC-039).
class KardexController extends Controller
{
    public function __construct(
        private readonly InventoryQueries $consultas,
    ) {}

    public function index(KardexRequest $request): AnonymousResourceCollection
    {
        $datos = $request->validated();

        // Busco con findOrFail para que el par inexistente responda 404
        // NO_ENCONTRADO por el mapeo de bootstrap/app.php (el §7.9 lo exige
        // antes que el alcance: lo que no existe no concede ni niega nada).
        $producto = Product::query()->findOrFail((int) $datos['product_id']);
        $almacen = Warehouse::query()->findOrFail((int) $datos['warehouse_id']);

        // El alcance se verifica contra el almacén pedido, no contra lo que la
        // consulta devuelva: sin pertenencia en warehouse_user rebota con 403
        // FUERA_DE_ALCANCE aunque el kardex exista.
        if (! $request->user()->canAccessWarehouse((int) $almacen->id)) {
            throw new OutsideWarehouseScopeException;
        }

        $resultado = $this->consultas->kardex(
            (int) $producto->id,
            (int) $almacen->id,
            $request->rangeBound($datos['from'] ?? null, false),
            $request->rangeBound($datos['to'] ?? null, true),
            (int) ($datos['per_page'] ?? 15),
        );

        // Adjunto los saldos de rango como clave propia para no romper la
        // forma paginada del §4 (data, meta y links quedan intactos).
        return InventoryMovementResource::collection($resultado['paginator'])
            ->additional(['balances' => [
                'initial_balance' => $resultado['initial_balance'],
                'final_balance' => $resultado['final_balance'],
            ]]);
    }
}
