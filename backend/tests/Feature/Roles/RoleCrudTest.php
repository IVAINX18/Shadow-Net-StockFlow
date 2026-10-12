<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Pruebo el CRUD contra MySQL real porque la unicidad del slug y el pivote
// permission_role viven en el motor definitivo (estrategia del §7).
uses(RefreshDatabase::class);

// Reutilizo los ayudantes de WarehouseWriteTest sin redeclararlos, porque Pest
// carga todos los archivos y la doble definición sería fatal.
if (! function_exists('administradorRoles')) {
    function administradorRoles(): User
    {
        return usuarioConPermisos([
            PermissionSlug::RolesView->value,
            PermissionSlug::RolesCreate->value,
            PermissionSlug::RolesUpdate->value,
        ]);
    }
}

if (! function_exists('lectorRoles')) {
    function lectorRoles(): User
    {
        return usuarioConPermisos([PermissionSlug::RolesView->value]);
    }
}

// AC-006 (parte implementable): listado paginado con permisos, alta,
// detalle, actualización y catálogo de permisos.
test('AC-006: listado paginado de roles con sus permisos', function (): void {
    actuarComo(administradorRoles());

    $permisoA = permisoMvp(PermissionSlug::UsersView->value);
    $permisoB = permisoMvp(PermissionSlug::InventoryView->value);
    $rol = Role::factory()->create(['slug' => 'rol-listado']);
    $rol->permissions()->attach([$permisoA->id, $permisoB->id]);

    $respuesta = $this->getJson('/api/v1/roles');

    $respuesta->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'description', 'permissions']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);

    $fila = collect($respuesta->json('data'))->firstWhere('slug', 'rol-listado');
    $slugs = collect($fila['permissions'])->pluck('slug')->all();
    expect($slugs)->toContain($permisoA->slug, $permisoB->slug);
});

test('AC-006: alta 201 del rol con permisos asignados', function (): void {
    actuarComo(administradorRoles());

    $permiso = permisoMvp(PermissionSlug::CatalogCreate->value);

    $respuesta = $this->postJson('/api/v1/roles', [
        'name' => 'Gestor de catálogo',
        'slug' => 'gestor-catalogo',
        'description' => 'Administra el catálogo.',
        'permission_ids' => [$permiso->id],
    ]);

    $respuesta->assertCreated()
        ->assertJsonPath('data.slug', 'gestor-catalogo')
        ->assertJsonPath('data.name', 'Gestor de catálogo');

    $slugs = collect($respuesta->json('data.permissions'))->pluck('slug')->all();
    expect($slugs)->toContain($permiso->slug);

    $this->assertDatabaseHas('roles', ['slug' => 'gestor-catalogo']);
});

test('slug duplicado y permiso inexistente responden 422', function (): void {
    actuarComo(administradorRoles());
    Role::factory()->create(['slug' => 'rol-duplicado']);

    $this->postJson('/api/v1/roles', [
        'name' => 'Otro',
        'slug' => 'rol-duplicado',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $this->postJson('/api/v1/roles', [
        'name' => 'Con permiso fantasma',
        'slug' => 'rol-fantasma',
        'permission_ids' => [999999],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

test('detalle inexistente responde 404 NO_ENCONTRADO', function (): void {
    actuarComo(lectorRoles());

    $this->getJson('/api/v1/roles/999999')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NO_ENCONTRADO');
});

test('AC-006: actualizar cambia nombre y sustituye permisos', function (): void {
    actuarComo(administradorRoles());

    $viejo = permisoMvp(PermissionSlug::ReportsView->value);
    $nuevo = permisoMvp(PermissionSlug::AuditView->value);
    $rol = Role::factory()->create(['slug' => 'rol-editable', 'name' => 'Antes']);
    $rol->permissions()->attach($viejo);

    $respuesta = $this->putJson("/api/v1/roles/{$rol->id}", [
        'name' => 'Después',
        'permission_ids' => [$nuevo->id],
    ]);

    $respuesta->assertOk()->assertJsonPath('data.name', 'Después');

    // El PUT sustituye, no acumula: el permiso viejo desaparece del pivote.
    $this->assertDatabaseMissing('permission_role', ['role_id' => $rol->id, 'permission_id' => $viejo->id]);
    $this->assertDatabaseHas('permission_role', ['role_id' => $rol->id, 'permission_id' => $nuevo->id]);
});

test('el slug no cambia al actualizar aunque viaje en el cuerpo', function (): void {
    actuarComo(administradorRoles());
    $rol = Role::factory()->create(['slug' => 'rol-estable', 'name' => 'Estable']);

    $this->putJson("/api/v1/roles/{$rol->id}", [
        'name' => 'Renombrado',
        'slug' => 'rol-cambiado',
    ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'rol-estable')
        ->assertJsonPath('data.name', 'Renombrado');

    expect($rol->fresh()->slug)->toBe('rol-estable');
});

test('catálogo de permisos con filtro por module', function (): void {
    actuarComo(lectorRoles());
    permisoMvp(PermissionSlug::UsersCreate->value);
    permisoMvp(PermissionSlug::RolesCreate->value);

    $respuesta = $this->getJson('/api/v1/permissions?module=users&per_page=100')->assertOk();

    $modulos = collect($respuesta->json('data'))->pluck('module')->unique()->all();
    expect($modulos)->toBe(['users']);

    $respuesta->assertJsonStructure([
        'data' => [['id', 'name', 'slug', 'module']],
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
    ]);
});

// Matriz de acceso: sin roles.create no hay alta; sin sesión, 401.
test('matriz: crear sin roles.create responde 403 SIN_PERMISO', function (): void {
    actuarComo(lectorRoles());

    $this->postJson('/api/v1/roles', ['name' => 'Intruso', 'slug' => 'intruso'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');
});

test('matriz: sin sesión responde 401 NO_AUTENTICADO', function (): void {
    $this->getJson('/api/v1/roles')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');

    $this->getJson('/api/v1/permissions')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');
});
