<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo una fila de existencias del reporte de stock (§7.11, RF-091): la
// cantidad y el costo promedio viajan como cadena con su escala exacta, y el
// valor (cantidad por promedio) redondeado a dos decimales. Incluyo la
// configuración min/max del par para que el bajo mínimo se entienda sin otra
// consulta (RF-080/RF-104). Como leo con el query builder y no con modelos,
// normalizo la escala aquí con bcmath en lugar de confiar en los casts.
class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = (array) $this->resource;

        return [
            'product_id' => (int) $row['product_id'],
            'sku' => $row['sku'],
            'product_name' => $row['product_name'],
            'category_id' => $row['category_id'] === null ? null : (int) $row['category_id'],
            'warehouse_id' => (int) $row['warehouse_id'],
            'warehouse_code' => $row['warehouse_code'],
            'quantity' => bcadd((string) $row['quantity'], '0', 3),
            'average_cost' => bcadd((string) $row['average_cost'], '0', 6),
            'stock_value' => bcadd((string) $row['stock_value'], '0', 2),
            'min_stock' => $row['min_stock'] === null ? null : bcadd((string) $row['min_stock'], '0', 3),
            'max_stock' => $row['max_stock'] === null ? null : bcadd((string) $row['max_stock'], '0', 3),
            'reorder_point' => $row['reorder_point'] === null ? null : bcadd((string) $row['reorder_point'], '0', 3),
            'below_min' => (bool) $row['below_min'],
            'last_movement_at' => $row['last_movement_at'],
        ];
    }
}
