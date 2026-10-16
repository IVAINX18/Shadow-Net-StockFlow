<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Pruebo la escritura contra MySQL real porque la unicidad de `code` y el
// pivote warehouse_user viven en el motor definitivo (estrategia del §7).
uses(RefreshDatabase::class);

// Los ayudantes (permisoMvp, usuarioConPermisos, actuarComo,
// administradorAlmacenes) viven en tests/Pest.php porque todos los módulos los
// usan y deben existir también al correr un módulo aislado.

// AC-007: crear con código nuevo y responsable activo deja el almacén
// consultable; el responsable no obtiene acceso automático (RF-023).
test('AC-007: crear con código nuevo y responsable activo deja el almacén consultable', function (): void {
    $admin = administradorAlmacenes();
    $responsable = User::factory()->create(['is_active' => true]);
    actuarComo($admin);

    $respuesta = $this->postJson('/api/v1/warehouses', [
        'code' => 'WH-BOG-07',
        'name' => 'Almacén Bogotá Norte',
        'address' => 'Calle 100 15-20',
        'city' => 'Bogota',
        'manager_user_id' => $responsable->id,
    ]);

    $respuesta->assertCreated()
        ->assertJsonPath('data.code', 'WH-BOG-07')
        ->assertJsonPath('data.is_active', true);

    $id = $respuesta->json('data.id');

    $this->getJson("/api/v1/warehouses/{$id}")->assertOk()
        ->assertJsonPath('data.manager.email', $responsable->email);

    // El responsable no queda autorizado solo por serlo: sin asignación en
    // warehouse_user, el detalle le responde FUERA_DE_ALCANCE.
    $lector = usuarioConPermisos([PermissionSlug::WarehousesView->value]);
    $responsable->roles()->attach($lector->roles()->first());
    actuarComo($responsable->fresh());

    $this->getJson("/api/v1/warehouses/{$id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');
});

// AC-008: el código duplicado se rechaza al crear.
test('AC-008: crear con código duplicado responde 422', function (): void {
    $admin = administradorAlmacenes();
    Warehouse::factory()->create(['code' => 'WH-DUP-01']);
    actuarComo($admin);

    $this->postJson('/api/v1/warehouses', [
        'code' => 'WH-DUP-01',
        'name' => 'Otro almacén',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// AC-008: renombrar a un código existente se rechaza; a uno libre se acepta.
test('AC-008: renombrar a código existente da 422 y a código libre da 200', function (): void {
    $admin = administradorAlmacenes();
    $origen = Warehouse::factory()->create(['code' => 'WH-REN-01']);
    Warehouse::factory()->create(['code' => 'WH-REN-02']);
    actuarComo($admin);

    $this->putJson("/api/v1/warehouses/{$origen->id}", ['code' => 'WH-REN-02'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $this->putJson("/api/v1/warehouses/{$origen->id}", ['code' => 'WH-REN-03', 'name' => 'Renombrado'])
        ->assertOk()
        ->assertJsonPath('data.code', 'WH-REN-03')
        ->assertJsonPath('data.name', 'Renombrado');
});

// RF-022: el responsable debe existir y estar activo.
test('responsable inexistente o inactivo responde 422', function (): void {
    $admin = administradorAlmacenes();
    $inactivo = User::factory()->create(['is_active' => false]);
    actuarComo($admin);

    $this->postJson('/api/v1/warehouses', [
        'code' => 'WH-RES-01',
        'name' => 'Con responsable fantasma',
        'manager_user_id' => 999999,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $this->postJson('/api/v1/warehouses', [
        'code' => 'WH-RES-02',
        'name' => 'Con responsable inactivo',
        'manager_user_id' => $inactivo->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// La Policy distingue el alcance: con permiso pero sin asignación, la
// actualización responde FUERA_DE_ALCANCE y no toca el registro.
test('actualización fuera del alcance responde 403 FUERA_DE_ALCANCE', function (): void {
    $jefe = usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesUpdate->value,
    ]);
    $propio = Warehouse::factory()->create(['name' => 'Propio']);
    $ajeno = Warehouse::factory()->create(['name' => 'Ajeno']);
    $jefe->warehouses()->attach($propio->id);
    actuarComo($jefe);

    $this->putJson("/api/v1/warehouses/{$ajeno->id}", ['name' => 'Hackeado'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');

    expect($ajeno->fresh()->name)->toBe('Ajeno');
});

// RF-020 y AC-010 (parte implementable): desactivar conserva el registro y el
// detalle sigue respondiendo; la segunda vez es 409 ESTADO_INVALIDO.
test('desactivar conserva el almacén consultable y la segunda vez da 409', function (): void {
    $admin = administradorAlmacenes();
    $almacen = Warehouse::factory()->create(['is_active' => true]);
    actuarComo($admin);

    $this->postJson("/api/v1/warehouses/{$almacen->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    // Sigue existiendo: no hay borrado ni soft delete, solo la bandera.
    $this->assertDatabaseHas('warehouses', ['id' => $almacen->id, 'deleted_at' => null]);
    expect($almacen->fresh()->is_active)->toBeFalse();

    // El historial sigue consultable: detalle y autorizados responden.
    $this->getJson("/api/v1/warehouses/{$almacen->id}")->assertOk();
    $this->getJson("/api/v1/warehouses/{$almacen->id}/users")->assertOk();

    $this->postJson("/api/v1/warehouses/{$almacen->id}/deactivate")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');
});

// RF-023: adjuntar es acumulativo e idempotente; el inactivo cae en 422.
test('adjuntar usuarios es acumulativo e idempotente y rechaza al inactivo', function (): void {
    $admin = administradorAlmacenes();
    $almacen = Warehouse::factory()->create();
    $ana = User::factory()->create(['is_active' => true]);
    $beto = User::factory()->create(['is_active' => true]);
    $inactivo = User::factory()->create(['is_active' => false]);
    actuarComo($admin);

    $this->postJson("/api/v1/warehouses/{$almacen->id}/users", ['user_ids' => [$ana->id]])
        ->assertOk();

    // Adjunto a Ana de nuevo junto a Beto: Ana no se duplica y sigue dentro.
    $respuesta = $this->postJson("/api/v1/warehouses/{$almacen->id}/users", ['user_ids' => [$ana->id, $beto->id]])
        ->assertOk();

    $ids = collect($respuesta->json('data'))->pluck('id')->all();
    expect($ids)->toContain($ana->id, $beto->id);
    expect(collect($ids)->filter(fn ($id) => $id === $ana->id))->toHaveCount(1);

    $this->assertDatabaseCount('warehouse_user', 2);

    $this->postJson("/api/v1/warehouses/{$almacen->id}/users", ['user_ids' => [$inactivo->id]])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// AC-011 por el endpoint de escritura: retirar quita listado y detalle al
// afectado; retirar a quien no estaba es 204 idempotente.
test('AC-011: retirar al usuario le quita listado y detalle de inmediato', function (): void {
    $admin = administradorAlmacenes();
    $almacen = Warehouse::factory()->create();
    $operario = usuarioConPermisos([PermissionSlug::WarehousesView->value]);
    $almacen->users()->attach($operario->id);

    actuarComo($admin);

    // El listado de autorizados expone el resumen sin secretos.
    $listado = $this->getJson("/api/v1/warehouses/{$almacen->id}/users")->assertOk();
    $listado->assertJsonStructure(['data' => [['id', 'name', 'email', 'is_active', 'roles']]]);
    $plano = json_encode($listado->json());
    expect($plano)->not->toContain('password');
    expect($plano)->not->toContain('remember_token');

    $this->deleteJson("/api/v1/warehouses/{$almacen->id}/users/{$operario->id}")
        ->assertNoContent();

    actuarComo($operario);

    $ids = collect($this->getJson('/api/v1/warehouses')->assertOk()->json('data'))
        ->pluck('id')
        ->all();
    expect($ids)->not->toContain($almacen->id);

    $this->getJson("/api/v1/warehouses/{$almacen->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');
});

test('retirar a un usuario no autorizado sigue en 204 y al inexistente en 404', function (): void {
    $admin = administradorAlmacenes();
    $almacen = Warehouse::factory()->create();
    $externo = User::factory()->create(['is_active' => true]);
    actuarComo($admin);

    $this->deleteJson("/api/v1/warehouses/{$almacen->id}/users/{$externo->id}")
        ->assertNoContent();

    $this->deleteJson("/api/v1/warehouses/{$almacen->id}/users/999999")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NO_ENCONTRADO');
});

// Matriz de escritura: sin `warehouses.create` ni `warehouses.update` no hay
// escritura, aunque exista el endpoint; el Auditor queda en solo lectura.
test('matriz: el jefe sin warehouses.create y el auditor no pueden escribir', function (): void {
    $jefe = usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesUpdate->value,
    ]);
    $almacen = Warehouse::factory()->create();
    $jefe->warehouses()->attach($almacen->id);
    actuarComo($jefe);

    $this->postJson('/api/v1/warehouses', ['code' => 'WH-SIN-01', 'name' => 'Sin permiso'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    $auditor = usuarioConPermisos([PermissionSlug::WarehousesView->value]);
    $auditor->warehouses()->attach($almacen->id);
    actuarComo($auditor);

    $this->putJson("/api/v1/warehouses/{$almacen->id}", ['name' => 'Toqueteo'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    $this->postJson("/api/v1/warehouses/{$almacen->id}/deactivate")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');
});

test('matriz: escribir sin sesión responde 401 NO_AUTENTICADO', function (): void {
    $almacen = Warehouse::factory()->create();

    $this->postJson('/api/v1/warehouses', ['code' => 'WH-ANON-01', 'name' => 'Anónimo'])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');

    $this->putJson("/api/v1/warehouses/{$almacen->id}", ['name' => 'Anónimo'])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');

    $this->postJson("/api/v1/warehouses/{$almacen->id}/deactivate")
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');
});

// Regresión del arreglo de alcance: tras desactivar, el Administrador sigue
// viendo el almacén en el listado y puede filtrar por `is_active`.
test('regresión: tras desactivar el administrador sigue viendo el almacén y filtra por estado', function (): void {
    $admin = administradorAlmacenes();
    $activo = Warehouse::factory()->create(['code' => 'WH-REG-01', 'is_active' => true]);
    Warehouse::factory()->create(['code' => 'WH-REG-02', 'is_active' => true]);
    actuarComo($admin);

    $this->postJson("/api/v1/warehouses/{$activo->id}/deactivate")->assertOk();

    $todos = collect($this->getJson('/api/v1/warehouses')->assertOk()->json('data'))
        ->pluck('code')
        ->all();
    expect($todos)->toContain('WH-REG-01', 'WH-REG-02');

    $inactivos = collect($this->getJson('/api/v1/warehouses?is_active=0')->assertOk()->json('data'))
        ->pluck('code')
        ->all();
    expect($inactivos)->toBe(['WH-REG-01']);

    $activos = collect($this->getJson('/api/v1/warehouses?is_active=1')->assertOk()->json('data'))
        ->pluck('code')
        ->all();
    expect($activos)->toBe(['WH-REG-02']);
});
