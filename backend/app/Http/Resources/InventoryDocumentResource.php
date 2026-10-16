<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo el documento con su identidad pública (uuid y número legible),
// estado, almacenes, responsables y líneas: los montos viajan como cadena y
// nunca se exponen ni la clave de idempotencia ni el id interno, porque son
// datos internos sin valor para el cliente (C-08).
class InventoryDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'doc_number' => $this->resource->doc_number,
            'type' => $this->resource->type,
            'status' => $this->resource->status,
            'origin_warehouse_id' => $this->resource->origin_warehouse_id,
            'destination_warehouse_id' => $this->resource->destination_warehouse_id,
            'reference' => $this->resource->reference,
            'notes' => $this->resource->notes,
            'reason_code' => $this->resource->reason_code,
            'created_by' => $this->resource->created_by,
            'confirmed_by' => $this->resource->confirmed_by,
            'confirmed_at' => $this->resource->confirmed_at,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            // Expongo las líneas solo si el controlador las cargó, para que el
            // listado no pague consultas por fila (evito N+1).
            'lines' => InventoryDocumentLineResource::collection($this->whenLoaded('lines')),
            // Expongo los movimientos generados al confirmar con su delta con
            // signo y su saldo resultante, como el ejemplo del §8: solo viajan
            // cuando el controlador los cargó.
            'movements' => InventoryMovementResource::collection($this->whenLoaded('movements')),
            // Expongo el resumen del documento de reversión que compensó a
            // este original, para que la trazabilidad no exija una segunda
            // consulta (RF-067).
            'reversal' => $this->whenLoaded('reversion', fn () => [
                'uuid' => $this->reversion->uuid,
                'doc_number' => $this->reversion->doc_number,
                'type' => $this->reversion->type,
                'status' => $this->reversion->status,
            ]),
        ];
    }
}
