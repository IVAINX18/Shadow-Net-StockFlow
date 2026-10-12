<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo solo la identidad del almacén porque el detalle operativo llegará en
// unidades posteriores; así el contrato se amplía sin romperse.
class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'is_active' => (bool) $this->resource->is_active,
        ];
    }
}
