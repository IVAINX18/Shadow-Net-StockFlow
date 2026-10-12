<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

// Pruebo el CRUD contra MySQL real porque la unicidad del correo y los pivotes
// role_user y warehouse_user viven en el motor definitivo (estrategia del §7).
uses(RefreshDatabase::class);

// Reutilizo los ayudantes de WarehouseWriteTest sin redeclararlos, porque Pest
// carga todos los archivos y la doble definición sería fatal.
if (! function_exists('administradorUsuarios')) {
    function administradorUsuarios(): User
    {
        return usuarioConPermisos([
            PermissionSlug::UsersView->value,
            PermissionSlug::UsersCreate->value,
            PermissionSlug::UsersUpdate->value,
        ]);
    }
}

if (! function_exists('lectorUsuarios')) {
    function lectorUsuarios(): User
    {
        return usuarioConPermisos([PermissionSlug::UsersView->value]);
    }
}

// AC-003 (parte implementable): el listado pagina con meta y links, y filtra
// por estado, rol (slug), almacén, búsqueda y orden cerrado.
test('AC-003: listado paginado con meta y links', function (): void {
    $admin = administradorUsuarios();
    User::factory()->count(4)->create(['is_active' => true]);
    actuarComo($admin);

    $respuesta = $this->getJson('/api/v1/users?per_page=2');

    $respuesta->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'email', 'is_active', 'roles']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'links' => ['first', 'last', 'prev', 'next'],
        ])
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', User::count());

    expect($respuesta->json('data'))->toHaveCount(2);
});

test('AC-003: filtrar por is_active acota el listado', function (): void {
    $admin = administradorUsuarios();
    User::factory()->create(['is_active' => true, 'email' => 'activo@example.com']);
    User::factory()->create(['is_active' => false, 'email' => 'inactivo@example.com']);
    actuarComo($admin);

    $correos = collect($this->getJson('/api/v1/users?is_active=0')->assertOk()->json('data'))
        ->pluck('email')
        ->all();

    expect($correos)->toContain('inactivo@example.com');
    expect($correos)->not->toContain('activo@example.com');
});

test('AC-003: filtrar por role usa el slug y por warehouse_id el identificador', function (): void {
    $admin = administradorUsuarios();
    $rol = Role::factory()->create(['slug' => 'jefatura-prueba']);
    $conRol = User::factory()->create(['email' => 'conrol@example.com']);
    $sinRol = User::factory()->create(['email' => 'sinrol@example.com']);
    $conRol->roles()->attach($rol);
    $almacen = Warehouse::factory()->create();
    $sinRol->warehouses()->attach($almacen);
    actuarComo($admin);

    $porRol = collect($this->getJson('/api/v1/users?role=jefatura-prueba')->assertOk()->json('data'))
        ->pluck('email')
        ->all();
    expect($porRol)->toContain('conrol@example.com');
    expect($porRol)->not->toContain('sinrol@example.com');

    $porAlmacen = collect($this->getJson("/api/v1/users?warehouse_id={$almacen->id}")->assertOk()->json('data'))
        ->pluck('email')
        ->all();
    expect($porAlmacen)->toContain('sinrol@example.com');
    expect($porAlmacen)->not->toContain('conrol@example.com');
});

test('AC-003: buscar con q encuentra por nombre y correo', function (): void {
    $admin = administradorUsuarios();
    User::factory()->create(['name' => 'Amapola Singular', 'email' => 'amapola@example.com']);
    User::factory()->create(['name' => 'Otro Nombre', 'email' => 'otro@example.com']);
    actuarComo($admin);

    $porNombre = collect($this->getJson('/api/v1/users?q=amapola')->assertOk()->json('data'))
        ->pluck('email')
        ->all();
    expect($porNombre)->toBe(['amapola@example.com']);

    $porCorreo = collect($this->getJson('/api/v1/users?q=otro@example')->assertOk()->json('data'))
        ->pluck('email')
        ->all();
    expect($porCorreo)->toBe(['otro@example.com']);
});

test('AC-003: ordenar por campo no permitido responde 422 VALIDACION', function (): void {
    actuarComo(administradorUsuarios());

    $this->getJson('/api/v1/users?sort=password')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $this->getJson('/api/v1/users?sort=-created_at')->assertOk();
    $this->getJson('/api/v1/users?sort=email')->assertOk();
});

// AC-005 (parte implementable): alta con roles y almacenes, y rechazos de forma.
test('AC-005: alta 201 con roles y almacenes asignados', function (): void {
    $admin = administradorUsuarios();
    $rol = Role::factory()->create(['slug' => 'operador-prueba']);
    $almacen = Warehouse::factory()->create();
    actuarComo($admin);

    $respuesta = $this->postJson('/api/v1/users', [
        'name' => 'Nueva Operaria',
        'email' => 'nueva@example.com',
        'password' => 'Secreto123',
        'role_ids' => [$rol->id],
        'warehouse_ids' => [$almacen->id],
    ]);

    $respuesta->assertCreated()
        ->assertJsonPath('data.email', 'nueva@example.com')
        ->assertJsonPath('data.is_active', true);

    $idsRoles = collect($respuesta->json('data.roles'))->pluck('id')->all();
    expect($idsRoles)->toContain($rol->id);

    $idsAlmacenes = collect($respuesta->json('data.warehouses'))->pluck('id')->all();
    expect($idsAlmacenes)->toContain($almacen->id);

    $this->assertDatabaseHas('users', ['email' => 'nueva@example.com', 'is_active' => true]);
    expect(Hash::check('Secreto123', User::where('email', 'nueva@example.com')->first()->password))->toBeTrue();
});

test('AC-005: correo duplicado y contraseña corta responden 422', function (): void {
    $admin = administradorUsuarios();
    User::factory()->create(['email' => 'duplicado@example.com']);
    actuarComo($admin);

    $this->postJson('/api/v1/users', [
        'name' => 'Duplicada',
        'email' => 'duplicado@example.com',
        'password' => 'Secreto123',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $this->postJson('/api/v1/users', [
        'name' => 'Clave Corta',
        'email' => 'corta@example.com',
        'password' => 'corta',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// Detalle y actualización, incluida la sustitución completa de asignaciones.
test('detalle inexistente responde 404 NO_ENCONTRADO', function (): void {
    actuarComo(lectorUsuarios());

    $this->getJson('/api/v1/users/999999')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NO_ENCONTRADO');
});

test('AC-005: actualizar sustituye roles y almacenes con sync', function (): void {
    $admin = administradorUsuarios();
    $rolViejo = Role::factory()->create(['slug' => 'rol-viejo']);
    $rolNuevo = Role::factory()->create(['slug' => 'rol-nuevo']);
    $almacenViejo = Warehouse::factory()->create();
    $almacenNuevo = Warehouse::factory()->create();
    $usuario = User::factory()->create(['name' => 'Antes']);
    $usuario->roles()->attach($rolViejo);
    $usuario->warehouses()->attach($almacenViejo);
    actuarComo($admin);

    $respuesta = $this->putJson("/api/v1/users/{$usuario->id}", [
        'name' => 'Después',
        'role_ids' => [$rolNuevo->id],
        'warehouse_ids' => [$almacenNuevo->id],
    ]);

    $respuesta->assertOk()->assertJsonPath('data.name', 'Después');

    // El PUT sustituye, no acumula: lo viejo desaparece de los pivotes.
    $this->assertDatabaseMissing('role_user', ['user_id' => $usuario->id, 'role_id' => $rolViejo->id]);
    $this->assertDatabaseMissing('warehouse_user', ['user_id' => $usuario->id, 'warehouse_id' => $almacenViejo->id]);
    $this->assertDatabaseHas('role_user', ['user_id' => $usuario->id, 'role_id' => $rolNuevo->id]);
    $this->assertDatabaseHas('warehouse_user', ['user_id' => $usuario->id, 'warehouse_id' => $almacenNuevo->id]);
});

// El PUT no es la vía del cambio de contraseña (RF-014): si el campo viaja con
// valor lo rechazo en vez de responder 200 sin cambiarla, para que ningún
// cliente crea que la contraseña se modificó. Un campo vacío sí se tolera,
// porque un formulario puede enviarlo sin contenido.
test('AC-005: el PUT rechaza una contraseña con valor y no la modifica', function (): void {
    $admin = administradorUsuarios();
    $usuario = User::factory()->create(['password' => Hash::make('ClaveOriginal1')]);
    actuarComo($admin);

    $this->putJson("/api/v1/users/{$usuario->id}", ['password' => 'ClaveNueva123'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.field', 'password')
        ->assertJsonPath('error.details.0.message', 'La contraseña no se cambia por este endpoint.');

    expect(Hash::check('ClaveOriginal1', $usuario->fresh()->password))->toBeTrue();

    // Un campo vacío no es un intento de cambio: el resto de la actualización procede.
    $this->putJson("/api/v1/users/{$usuario->id}", ['name' => 'Renombrada', 'password' => ''])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renombrada');
});

// Activación, desactivación y la guarda del último administrador.
test('activar y desactivar conmutan el estado y repetir da 409', function (): void {
    $admin = administradorUsuarios();
    $usuario = User::factory()->create(['is_active' => false]);
    actuarComo($admin);

    $this->postJson("/api/v1/users/{$usuario->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    $this->postJson("/api/v1/users/{$usuario->id}/activate")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');

    // Para desactivar sin chocar con la guarda, el objetivo no es admin.
    $otro = User::factory()->create(['is_active' => true]);

    $this->postJson("/api/v1/users/{$otro->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->postJson("/api/v1/users/{$otro->id}/deactivate")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');
});

test('AC-055: desactivar al último administrador activo da 409', function (): void {
    $rolAdmin = Role::factory()->create(['slug' => 'administrador', 'name' => 'Administrador']);
    $permiso = permisoMvp(PermissionSlug::UsersUpdate->value);
    $rolAdmin->permissions()->attach($permiso);

    $unico = User::factory()->create(['is_active' => true]);
    $unico->roles()->attach($rolAdmin);
    actuarComo($unico);

    $this->postJson("/api/v1/users/{$unico->id}/deactivate")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');

    expect($unico->fresh()->is_active)->toBeTrue();

    // Con un segundo administrador activo, la desactivación sí procede.
    $segundo = User::factory()->create(['is_active' => true]);
    $segundo->roles()->attach($rolAdmin);

    $this->postJson("/api/v1/users/{$unico->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

// Matriz de acceso: 401 sin sesión, 403 sin permiso y sin secretos.
test('matriz: sin sesión responde 401 NO_AUTENTICADO', function (): void {
    $usuario = User::factory()->create();

    $this->getJson('/api/v1/users')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');

    $this->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'Secreto123'])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');

    $this->getJson("/api/v1/users/{$usuario->id}")
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');

    $this->putJson("/api/v1/users/{$usuario->id}", ['name' => 'X'])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');
});

test('matriz: sin permiso responde 403 SIN_PERMISO', function (): void {
    $sinPermiso = usuarioConPermisos([PermissionSlug::WarehousesView->value]);
    $usuario = User::factory()->create();
    actuarComo($sinPermiso);

    $this->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    $this->postJson('/api/v1/users', ['name' => 'X', 'email' => 'y@example.com', 'password' => 'Secreto123'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    $this->getJson("/api/v1/users/{$usuario->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    $this->putJson("/api/v1/users/{$usuario->id}", ['name' => 'X'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');
});

test('el listado y el detalle nunca exponen secretos', function (): void {
    actuarComo(lectorUsuarios());
    User::factory()->create(['email' => 'visible@example.com']);

    $listado = $this->getJson('/api/v1/users')->assertOk();
    $planoListado = json_encode($listado->json());
    expect($planoListado)->not->toContain('password');
    expect($planoListado)->not->toContain('remember_token');

    $id = collect($listado->json('data'))->firstWhere('email', 'visible@example.com')['id'];
    $detalle = $this->getJson("/api/v1/users/{$id}")->assertOk();
    $planoDetalle = json_encode($detalle->json());
    expect($planoDetalle)->not->toContain('password');
    expect($planoDetalle)->not->toContain('remember_token');
});
