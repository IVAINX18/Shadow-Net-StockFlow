<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo el movimiento del kardex con sus decimales como cadena: el delta con
// signo, el costo aplicado y el saldo resultante que permite reconstruir el
// kardex sin recalcular (RF-076). El kardex se arma con el constructor de
// consultas (objetos planos) y no con modelos, así que la marca de tiempo no
// pasa por el cast de Eloquent; la normalizo a ISO-8601 UTC aquí para que la
// fecha salga igual que en el resto de la API (§3 de api-design.md) en vez del
// formato crudo de MySQL.
class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'document_id' => $this->resource->document_id,
            'product_id' => $this->resource->product_id,
            'warehouse_id' => $this->resource->warehouse_id,
            'direction' => $this->resource->direction,
            'quantity_delta' => $this->resource->quantity_delta,
            'unit_cost' => $this->resource->unit_cost,
            'balance_after' => $this->resource->balance_after,
            'created_at' => CarbonImmutable::parse($this->resource->created_at)->utc()->toIso8601String(),
        ];
    }
}
