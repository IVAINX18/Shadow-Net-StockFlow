<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

// Reúno aquí las consultas de lectura del inventario porque el kardex y el
// reporte de existencias leen las mismas dos tablas y no quiero que cada
// controlador reinvente el alcance, el orden o los saldos. Esta clase es de
// SOLO LECTURA: ningún método escribe, bloquea ni abre transacciones; el
// libro append-only se deriva de `inventory_movements` sin duplicar su
// lógica (el saldo por movimiento ya viaja en `balance_after`, RF-076).
final class InventoryQueries
{
    /**
     * Devuelvo el kardex paginado de un par producto-almacén con sus saldos
     * de rango. Ordeno por (created_at, id) ascendente porque `created_at`
     * solo tiene precisión de segundo y dos movimientos del mismo documento
     * (las dos patas de una transferencia, o varias líneas confirmadas en el
     * mismo segundo) compartirían marca: el `id` autoincremental desempata
     * de forma estable y reproduce el orden real de escritura (AC-039).
     * El rango es inclusivo en ambos extremos (>= desde, <= hasta).
     *
     * @return array{paginator: LengthAwarePaginator, initial_balance: string, final_balance: string}
     */
    public function kardex(
        int $productId,
        int $warehouseId,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        int $perPage,
    ): array {
        // El saldo inicial es la suma de todo lo anterior al rango: así el
        // paginado puede empezar en cualquier página sin perder el arrastre.
        $initial = $from === null
            ? '0.000'
            : (string) DB::table('inventory_movements')
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('created_at', '<', $from->toDateTimeString())
                ->sum('quantity_delta');

        $query = DB::table('inventory_movements')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        if ($from !== null) {
            $query->where('created_at', '>=', $from->toDateTimeString());
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to->toDateTimeString());
        }

        // Sumo el rango en una agregación indexada en lugar de acumular la
        // página: el saldo final debe cuadrar con el rango completo aunque el
        // cliente pida solo la primera página.
        $rangeSum = (string) (clone $query)->sum('quantity_delta');

        $paginator = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        // Normalizo a tres decimales con bcmath porque SUM devuelve la cadena
        // sin escala fija y el contrato exige decimales como cadena exacta,
        // nunca flotante (§3 de api-design.md).
        $initialBalance = bcadd($initial, '0', 3);

        return [
            'paginator' => $paginator,
            'initial_balance' => $initialBalance,
            'final_balance' => bcadd($initialBalance, bcadd($rangeSum, '0', 3), 3),
        ];
    }

    /**
     * Devuelvo las existencias materializadas visibles para el solicitante con
     * los filtros del §7.11 (almacén, categoría y bajo mínimo) y sus totales.
     * El alcance se resuelve EN la consulta con whereIn sobre los ids
     * autorizados, nunca filtrando en PHP. Con `below_min` comparo contra el
     * mínimo configurado en `product_warehouse` (RF-080/RF-104); si el par no
     * tiene configuración, el mínimo es 0 y nunca cae bajo mínimo porque la
     * existencia jamás es negativa (I-01).
     *
     * @param  array<int>  $scopeIds
     * @param  array{warehouse_id?: int, category_id?: int, below_min?: bool}  $filters
     * @return array{paginator: LengthAwarePaginator, totals: array{total_quantity: string, total_value: string}}
     */
    public function stock(array $scopeIds, array $filters, int $perPage): array
    {
        $paginator = $this->stockBaseQuery($scopeIds, $filters)
            ->select([
                'inventories.product_id',
                'inventories.warehouse_id',
                'inventories.quantity',
                'inventories.average_cost',
                'inventories.last_movement_at',
                'products.sku',
                'products.name AS product_name',
                'products.category_id',
                'warehouses.code AS warehouse_code',
                'product_warehouse.min_stock',
                'product_warehouse.max_stock',
                'product_warehouse.reorder_point',
            ])
            ->selectRaw('ROUND(inventories.quantity * inventories.average_cost, 2) AS stock_value')
            ->selectRaw('(inventories.quantity < COALESCE(product_warehouse.min_stock, 0)) AS below_min')
            // Ordeno por (product_id, warehouse_id) porque es el orden de la
            // única `uq_inventories_product_warehouse`: así MySQL puede
            // recorrer el índice ya ordenado en lugar de un filesort, y la
            // paginación queda estable. El §7.11 no fija un orden para este
            // reporte, así que elijo el que aprovecha el índice existente.
            ->orderBy('inventories.product_id')
            ->orderBy('inventories.warehouse_id')
            ->paginate($perPage)
            ->withQueryString();

        // Calculo los totales sobre el conjunto filtrado completo, no sobre la
        // página, para que el detalle cuadre con el total general (RF-102,
        // AC-047). Construyo la consulta de totales desde la base (sin las
        // columnas de fila) porque con `only_full_group_by` de MySQL no puedo
        // agregar sobre una consulta que ya proyecta columnas no agrupadas; el
        // valor es cantidad por promedio ponderado (RF-091) con el redondeo a
        // dos decimales dentro de la aritmética DECIMAL exacta del motor.
        $totals = $this->stockBaseQuery($scopeIds, $filters)
            ->selectRaw('COALESCE(SUM(inventories.quantity), 0) AS total_quantity')
            ->selectRaw('COALESCE(SUM(ROUND(inventories.quantity * inventories.average_cost, 2)), 0) AS total_value')
            ->first();

        return [
            'paginator' => $paginator,
            'totals' => [
                'total_quantity' => bcadd((string) $totals->total_quantity, '0', 3),
                'total_value' => bcadd((string) $totals->total_value, '0', 2),
            ],
        ];
    }

    /**
     * Construyo la consulta base (uniones y filtros, sin proyección) una sola
     * vez porque el detalle paginado y los totales deben partir exactamente
     * del mismo conjunto: si vivieran en dos sitios, divergirían igual que
     * temía CatalogRules con el catálogo. Cada llamante añade su propia
     * proyección para no apilar columnas de fila con agregados.
     *
     * @param  array<int>  $scopeIds
     * @param  array{warehouse_id?: int, category_id?: int, below_min?: bool}  $filters
     */
    private function stockBaseQuery(array $scopeIds, array $filters): Builder
    {
        $query = DB::table('inventories')
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventories.warehouse_id')
            ->leftJoin('product_warehouse', function ($join): void {
                $join->on('product_warehouse.product_id', '=', 'inventories.product_id')
                    ->on('product_warehouse.warehouse_id', '=', 'inventories.warehouse_id');
            })
            ->whereIn('inventories.warehouse_id', $scopeIds);

        if (isset($filters['warehouse_id'])) {
            $query->where('inventories.warehouse_id', $filters['warehouse_id']);
        }

        if (isset($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        if (($filters['below_min'] ?? false) === true) {
            $query->whereRaw('inventories.quantity < COALESCE(product_warehouse.min_stock, 0)');
        }

        return $query;
    }
}
