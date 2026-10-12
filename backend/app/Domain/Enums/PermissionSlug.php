<?php

declare(strict_types=1);

namespace App\Domain\Enums;

// Enumero el catálogo de permisos del MVP porque así los slugs dejan de ser
// cadenas sueltas repartidas por el código: el compilador detecta una errata y
// los Gates de AppServiceProvider se generan desde una única fuente de verdad.
// El literal de cada caso DEBE coincidir con permission.slug en la base.
enum PermissionSlug: string
{
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';

    case WarehousesView = 'warehouses.view';
    // Permiso global que excede el alcance por almacén: solo el Administrador
    // lo recibe y le permite ver todos los almacenes, no solo los asignados.
    case WarehousesViewAll = 'warehouses.view_all';
    case WarehousesCreate = 'warehouses.create';
    case WarehousesUpdate = 'warehouses.update';

    case CatalogCreate = 'catalog.create';
    case CatalogUpdate = 'catalog.update';

    case InventoryView = 'inventory.view';
    case InventoryCreate = 'inventory.create';
    case InventoryConfirm = 'inventory.confirm';
    case InventoryReverse = 'inventory.reverse';

    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    case AuditView = 'audit.view';

    // Expongo los slugs como lista plana para registrarlos de golpe como Gates
    // y para compararlos contra los permisos que trae el usuario.
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $slug): string => $slug->value,
            self::cases(),
        );
    }
}
