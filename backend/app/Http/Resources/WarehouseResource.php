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
            'address' => $this->resource->address,
            'city' => $this->resource->city,
            'is_active' => (bool) $this->resource->is_active,
            // Expongo responsable y autorizados solo si el controlador los
            // cargó, para que el listado no arrastre estas relaciones ni pague
            // una consulta por fila (evito N+1 con whenLoaded).
            'manager' => $this->whenLoaded('manager', function (): ?array {
                $manager = $this->resource->manager;

                if ($manager === null) {
                    return null;
                }

                return [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'email' => $manager->email,
                ];
            }),
            'users' => $this->whenLoaded('users', fn () => $this->resource->users
                ->map(fn ($user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values()),
        ];
    }
}
