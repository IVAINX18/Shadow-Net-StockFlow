<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo la identidad jerárquica de la categoría con sus hijas y el conteo
// de productos del contrato: el conteo llega en la Unidad 2 con la tabla
// `products` y viaja solo cuando el controlador lo cargó (whenCounted).
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'parent_id' => $this->resource->parent_id,
            'is_active' => (bool) $this->resource->is_active,
            // Expongo las hijas solo si el controlador las cargó, para que el
            // listado no pague una consulta por fila (evito N+1 con whenLoaded).
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            // Expongo el conteo solo si el controlador lo contó, por el mismo
            // motivo de no encarecer el listado.
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
