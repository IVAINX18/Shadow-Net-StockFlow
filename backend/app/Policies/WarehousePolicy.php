<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enums\PermissionSlug;
use App\Exceptions\OutsideWarehouseScopeException;
use App\Models\User;
use App\Models\Warehouse;

// Combino el permiso RBAC con el alcance por almacén porque C-02 exige que
// cada lectura y escritura sobre un almacén pase por warehouse_user, y que
// ningún rol (ni siquiera Administrador) se salte ese alcance.
class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionSlug::WarehousesView->value);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->conPermisoYAlcance($user, $warehouse, PermissionSlug::WarehousesView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionSlug::WarehousesCreate->value);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->conPermisoYAlcance($user, $warehouse, PermissionSlug::WarehousesUpdate->value);
    }

    public function deactivate(User $user, Warehouse $warehouse): bool
    {
        return $this->conPermisoYAlcance($user, $warehouse, PermissionSlug::WarehousesUpdate->value);
    }

    // La reactivación exige el mismo permiso y alcance que la desactivación,
    // porque ambas mutan el ciclo de vida del almacén (RF-020).
    public function activate(User $user, Warehouse $warehouse): bool
    {
        return $this->conPermisoYAlcance($user, $warehouse, PermissionSlug::WarehousesUpdate->value);
    }

    public function manageUsers(User $user, Warehouse $warehouse): bool
    {
        return $this->conPermisoYAlcance($user, $warehouse, PermissionSlug::WarehousesUpdate->value);
    }

    // Centralizo la regla doble para no repetirla en cada método: sin permiso
    // devuelvo false (SIN_PERMISO) y con permiso pero fuera de alcance lanzo la
    // excepción dedicada (FUERA_DE_ALCANCE), que es la única forma de que el
    // cliente distinga ambos 403 del §5.
    private function conPermisoYAlcance(User $user, Warehouse $warehouse, string $permission): bool
    {
        if (! $user->hasPermission($permission)) {
            return false;
        }

        if (! $user->canAccessWarehouse((int) $warehouse->id)) {
            throw new OutsideWarehouseScopeException;
        }

        return true;
    }
}
