<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enums\PermissionSlug;
use App\Models\User;

// Cubro la administración de usuarios con permisos RBAC, sin alcance por
// almacén: las cuentas son globales y su asignación a almacenes se gobierna
// desde WarehousePolicy::manageUsers.
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionSlug::UsersView->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission(PermissionSlug::UsersView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionSlug::UsersCreate->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission(PermissionSlug::UsersUpdate->value);
    }

    public function activate(User $user, User $model): bool
    {
        return $user->hasPermission(PermissionSlug::UsersUpdate->value);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->hasPermission(PermissionSlug::UsersUpdate->value);
    }
}
