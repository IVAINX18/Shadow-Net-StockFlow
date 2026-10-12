<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Expongo el detalle mínimo del permiso (id, nombre, slug y módulo) porque el
// catálogo debe servir para construir el formulario de roles (§7.3): con el
// slug basta para asignar y con el módulo para agrupar en la interfaz.
class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'module' => $this->resource->module,
        ];
    }
}
