<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Enums\PermissionSlug;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

// Siembro los cuatro roles base con su matriz exacta porque el RBAC del MVP
// no admite asignaciones manuales: cada despliegue debe partir del mismo mapa.
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Uso sync en lugar de attach porque así re-ejecutar corrige la deriva
        // (permisos quitados o añadidos a mano) en vez de acumular duplicados.
        $this->sincronizar('administrador', 'Administrador', 'Acceso total al sistema.', PermissionSlug::values());

        $this->sincronizar('jefe-de-almacen', 'Jefe de almacén', 'Opera almacenes, catálogo e inventario.', [
            PermissionSlug::WarehousesView->value,
            PermissionSlug::WarehousesUpdate->value,
            PermissionSlug::CatalogCreate->value,
            PermissionSlug::CatalogUpdate->value,
            PermissionSlug::InventoryView->value,
            PermissionSlug::InventoryCreate->value,
            PermissionSlug::InventoryConfirm->value,
            PermissionSlug::InventoryReverse->value,
            PermissionSlug::ReportsView->value,
            PermissionSlug::ReportsExport->value,
            PermissionSlug::AuditView->value,
        ]);

        $this->sincronizar('operador', 'Operador', 'Crea y confirma movimientos en su alcance.', [
            PermissionSlug::WarehousesView->value,
            PermissionSlug::InventoryView->value,
            PermissionSlug::InventoryCreate->value,
            PermissionSlug::InventoryConfirm->value,
        ]);

        // El auditor solo lee por AC-006: deliberadamente no recibe
        // inventory.confirm para que nunca pueda confirmar documentos.
        $this->sincronizar('auditor', 'Auditor', 'Solo lectura de almacenes, reportes y auditoría.', [
            PermissionSlug::WarehousesView->value,
            PermissionSlug::InventoryView->value,
            PermissionSlug::ReportsView->value,
            PermissionSlug::AuditView->value,
        ]);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function sincronizar(string $slug, string $name, string $description, array $slugs): void
    {
        // Busco por slug porque es el identificador estable del rol; el nombre
        // puede llevar tilde y no sirve como clave idempotente.
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => $description]
        );

        $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id')->all();

        $role->permissions()->sync($ids);
    }
}
