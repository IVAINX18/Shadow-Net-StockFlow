<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo la línea con sus decimales como cadena gracias a los casts del
// modelo: cantidad con tres decimales, costo con seis y total con dos (§3).
// La dirección solo viaja en el ajuste (el signo vive por línea, §4.3); en los
// demás tipos es nula porque la fija el tipo de documento.
class InventoryDocumentLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_id' => $this->resource->product_id,
            'quantity' => $this->resource->quantity,
            'direction' => $this->resource->direction,
            'unit_cost' => $this->resource->unit_cost,
            'line_total' => $this->resource->line_total,
        ];
    }
}
