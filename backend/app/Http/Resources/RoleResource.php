<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo id, nombre, slug y descripción, más los permisos como detalle mínimo
// (PermissionResource) solo cuando la relación viene cargada: el RoleController
// siempre la precarga, así que el listado y el detalle de roles incluyen
// permisos sin que los resúmenes de usuario paguen esa carga cuando no la piden.
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
