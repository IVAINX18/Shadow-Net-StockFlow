<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Serializo el usuario autenticado sin exponer jamás password, remember_token
// ni tokens, porque C-07/C-08 prohíben filtrar secretos en respuestas.
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $user->loadMissing(['roles.permissions:id,slug', 'warehouses:id,code,name,is_active']);

        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'last_login_at' => $user->last_login_at?->utc()->toIso8601String(),
            'roles' => RoleResource::collection($user->roles)->toArray($request),
            'permissions' => $permissions,
            'warehouses' => WarehouseResource::collection($user->warehouses)->toArray($request),
        ];
    }
}
