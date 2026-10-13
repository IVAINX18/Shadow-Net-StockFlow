<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo la unidad tal cual vive en la base: el contrato la representa sin
// decimales como cadena porque la unidad aún no porta cantidades; la regla de
// decimales como cadena aplicará a los productos de la Unidad 2.
class UnitOfMeasureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'allows_decimals' => (bool) $this->resource->allows_decimals,
        ];
    }
}
