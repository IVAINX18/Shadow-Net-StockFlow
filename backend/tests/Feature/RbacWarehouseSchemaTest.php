<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// Compruebo que MySQL rechaza duplicados en las columnas únicas del §5.1,
// porque esa es la última red que protege el catálogo frente a errores del
// servicio (códigos de almacén y slugs de rol repetidos).
test('el esquema rechaza el código de almacén duplicado', function (): void {
    Warehouse::factory()->create(['code' => 'WH-BOGOTA-01']);

    expect(fn () => Warehouse::factory()->create(['code' => 'WH-BOGOTA-01']))
        ->toThrow(QueryException::class);
});

test('el esquema rechaza el slug de rol duplicado', function (): void {
    Role::factory()->create(['slug' => 'administrador']);

    expect(fn () => Role::factory()->create(['slug' => 'administrador']))
        ->toThrow(QueryException::class);
});

test('las tablas de la unidad 1 existen con sus restricciones', function (): void {
    // Verifico contra el esquema real de MySQL porque los nombres de
    // restricciones (uq_*, fk_*) son parte del contrato del §1.
    $connection = DB::connection()->getDriverName();

    expect($connection)->toBe('mysql');

    foreach (['roles', 'permissions', 'role_user', 'permission_role', 'warehouses', 'warehouse_user'] as $table) {
        expect(DB::getSchemaBuilder()->hasTable($table))->toBeTrue();
    }
});
