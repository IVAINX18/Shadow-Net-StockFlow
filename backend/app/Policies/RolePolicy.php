<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enums\PermissionSlug;
use App\Models\Role;
use App\Models\User;

// Los roles son globales y solo exigen permiso RBAC; no llevan alcance por
// almacén porque su efecto sobre almacenes ya lo validan las otras Policies.
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionSlug::RolesView->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission(PermissionSlug::RolesView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionSlug::RolesCreate->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission(PermissionSlug::RolesUpdate->value);
    }
}
