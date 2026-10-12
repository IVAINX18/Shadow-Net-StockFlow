<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Resumo al usuario autorizado sin exponer jamás password, remember_token ni
// tokens, y sin la carga pesada de UserResource (permisos y almacenes), que es
// el contrato del propio autenticado y aquí provocaría consultas innecesarias.
class UserSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'is_active' => (bool) $this->resource->is_active,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}
