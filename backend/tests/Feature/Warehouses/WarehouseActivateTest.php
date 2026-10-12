<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Pruebo la reactivación contra MySQL real porque el estado vive en el motor
// definitivo; reutilizo los ayudantes de WarehouseWriteTest sin redeclararlos.
uses(RefreshDatabase::class);

// La desactivación no tenía inverso: reactivar devuelve el almacén a activo y
// repetir la transición es 409 ESTADO_INVALIDO, igual que su inversa.
test('reactivar devuelve el almacén a activo y repetir da 409', function (): void {
    $admin = administradorAlmacenes();
    $almacen = Warehouse::factory()->create(['is_active' => false]);
    actuarComo($admin);

    $this->postJson("/api/v1/warehouses/{$almacen->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect($almacen->fresh()->is_active)->toBeTrue();

    $this->postJson("/api/v1/warehouses/{$almacen->id}/activate")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');
});

test('reactivar fuera del alcance responde 403 FUERA_DE_ALCANCE', function (): void {
    $jefe = usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesUpdate->value,
    ]);
    $propio = Warehouse::factory()->create(['is_active' => false]);
    $ajeno = Warehouse::factory()->create(['is_active' => false]);
    $jefe->warehouses()->attach($propio->id);
    actuarComo($jefe);

    $this->postJson("/api/v1/warehouses/{$ajeno->id}/activate")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');

    expect($ajeno->fresh()->is_active)->toBeFalse();
});

test('reactivar sin permiso responde 403 SIN_PERMISO y sin sesión 401', function (): void {
    $almacen = Warehouse::factory()->create(['is_active' => false]);

    $lector = usuarioConPermisos([PermissionSlug::WarehousesView->value]);
    $lector->warehouses()->attach($almacen->id);
    actuarComo($lector);

    $this->postJson("/api/v1/warehouses/{$almacen->id}/activate")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');
});
