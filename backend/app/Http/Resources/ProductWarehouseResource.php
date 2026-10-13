<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo la configuración de stock por almacén: las cantidades viajan como
// cadena gracias a los casts decimal del modelo, nunca como flotante (§3).
class ProductWarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_id' => $this->resource->product_id,
            'warehouse_id' => $this->resource->warehouse_id,
            'min_stock' => $this->resource->min_stock,
            'max_stock' => $this->resource->max_stock,
            'reorder_point' => $this->resource->reorder_point,
        ];
    }
}
