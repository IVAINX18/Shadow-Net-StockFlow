<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo el producto con categoría, unidad y configuración por almacén: los
// montos y cantidades viajan como cadena gracias a los casts decimal del
// modelo, nunca como flotante (§3). Omito cantidades y costo promedio porque
// la tabla inventories llega con el kardex en una fase posterior: no invento
// ceros, simplemente no los expongo (registrado en api-design.md §7.7).
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'barcode' => $this->resource->barcode,
            'category_id' => $this->resource->category_id,
            'unit_of_measure_id' => $this->resource->unit_of_measure_id,
            'is_active' => (bool) $this->resource->is_active,
            'cost_price' => $this->resource->cost_price,
            'sale_price' => $this->resource->sale_price,
            'costing_method' => $this->resource->costing_method,
            // Expongo las relaciones solo si el controlador las cargó, para
            // que el listado no pague consultas por fila (evito N+1).
            'category' => new CategoryResource($this->whenLoaded('category')),
            'unit_of_measure' => new UnitOfMeasureResource($this->whenLoaded('unitOfMeasure')),
            'warehouses' => ProductWarehouseResource::collection($this->whenLoaded('configurations')),
        ];
    }
}
