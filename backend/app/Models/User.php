<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Enums\PermissionSlug;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    // Incluyo HasApiTokens porque la API se autenticará con tokens Sanctum
    // desde la Unidad 2; lo dejo ya cableado para no reabrir este modelo.
    use HasApiTokens, HasFactory, Notifiable;

    // Guardo aquí los slugs de permiso del request actual para no repetir la
    // consulta de roles+permisos en cada chequeo de autorización.
    /** @var array<string>|null */
    protected ?array $cachedPermissionSlugs = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** @return BelongsToMany<Warehouse, $this> */
    public function warehouses(): BelongsToMany
    {
        // Nombro la tabla explícita porque la migración la creó como
        // warehouse_user y el nombre por defecto no coincidiría.
        return $this->belongsToMany(Warehouse::class, 'warehouse_user');
    }

    /** @return HasMany<Warehouse, $this> */
    public function managedWarehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'manager_user_id');
    }

    // Compruebo el permiso sobre la unión de permisos de todos mis roles,
    // porque un usuario puede acumular varios roles y basta con que uno lo
    // conceda. Cacheo por request para no golpear la base en cada gate.
    public function hasPermission(string $slug): bool
    {
        if ($this->cachedPermissionSlugs === null) {
            $this->cachedPermissionSlugs = $this->roles()
                ->with('permissions:id,slug')
                ->get()
                ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
                ->unique()
                ->values()
                ->all();
        }

        return in_array($slug, $this->cachedPermissionSlugs, true);
    }

    // Devuelvo los almacenes que puedo operar: con el permiso global veo todos
    // sin filtrar por estado, porque el alcance decide QUÉ almacenes veo y el
    // filtro `is_active` del listado ya es un parámetro aparte; ocultar aquí
    // los desactivados dejaría al Administrador sin ver en el listado uno que
    // acaba de desactivar, aunque su detalle sí se lo permite. Sin el permiso
    // global, solo los asignados. Así el alcance del §5.1 queda en un único
    // punto en lugar de repartido por servicios.
    /**
     * @return array<int>
     */
    public function accessibleWarehouseIds(): array
    {
        if ($this->hasPermission('warehouses.view_all')) {
            return Warehouse::query()->pluck('id')->all();
        }

        return $this->warehouses()->pluck('warehouses.id')->all();
    }

    // Decido el acceso a un almacén concreto sin cachear la pertenencia: leo
    // la asignación en cada llamada porque AC-011 exige que retirar a un
    // usuario de un almacén le quite el acceso en la petición inmediatamente
    // siguiente. El permiso global `warehouses.view_all` salta el alcance.
    public function canAccessWarehouse(int $warehouseId): bool
    {
        if ($this->hasPermission(PermissionSlug::WarehousesViewAll->value)) {
            return true;
        }

        return in_array($warehouseId, $this->accessibleWarehouseIds(), true);
    }
}
