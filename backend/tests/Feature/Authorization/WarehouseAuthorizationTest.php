<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

// Pruebo la autorización contra MySQL real porque el alcance por almacén vive
// en la tabla pivote warehouse_user y quiero ver el comportamiento del motor
// definitivo, no el de una base en memoria (estrategia de pruebas del §7).
uses(RefreshDatabase::class);

// Creo el permiso con el slug exacto del catálogo para poder asignarlo al rol;
// el slug es la clave que comparan los Gates y el User::hasPermission.
function permisoMvp(string $slug): Permission
{
    return Permission::firstOrCreate(
        ['slug' => $slug],
        ['name' => $slug, 'module' => explode('.', $slug)[0]],
    );
}

// Concedo capacidades a través de un rol, no por asignación directa, porque así
// es como el RBAC real otorga permisos y es lo que quiero ejercer en las pruebas.
function usuarioConPermisos(array $slugs, array $atributos = []): User
{
    $user = User::factory()->create(array_merge(['is_active' => true], $atributos));

    $role = Role::factory()->create();

    foreach ($slugs as $slug) {
        $role->permissions()->attach(permisoMvp($slug));
    }

    $user->roles()->attach($role);

    return $user->fresh();
}

// Autentico con el guard de Sanctum porque es el que exige `auth:sanctum`; así
// `$request->user()` queda disponible para la Policy y el middleware de permiso.
function actuarComo(User $user): void
{
    Sanctum::actingAs($user);
}

// AC-005: aunque la SPA oculte la pantalla, la petición directa sin permiso
// debe rebotar con 403 SIN_PERMISO tanto en el listado como en el detalle.
test('AC-005: sin el permiso el acceso directo recibe 403 SIN_PERMISO', function (): void {
    $sinPermiso = usuarioConPermisos([PermissionSlug::UsersView->value]);
    $almacen = Warehouse::factory()->create();
    actuarComo($sinPermiso);

    $this->getJson('/api/v1/warehouses')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    $this->getJson("/api/v1/warehouses/{$almacen->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');
});

// AC-009: el Jefe de almacén asignado a dos almacenes solo ve esos dos, ni uno
// más, tal como exige el alcance warehouse_user del §5.1.
test('AC-009: el jefe de almacén ve solo sus dos almacenes asignados', function (): void {
    $jefe = usuarioConPermisos([PermissionSlug::WarehousesView->value]);

    $propioUno = Warehouse::factory()->create();
    $propioDos = Warehouse::factory()->create();
    $ajeno = Warehouse::factory()->create();
    $jefe->warehouses()->attach([$propioUno->id, $propioDos->id]);

    actuarComo($jefe);

    $ids = collect($this->getJson('/api/v1/warehouses')->assertOk()->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->toHaveCount(2);
    expect($ids)->toContain($propioUno->id, $propioDos->id);
    expect($ids)->not->toContain($ajeno->id);
});

// AC-011: retirar al usuario de un almacén le quita el acceso en la petición
// inmediatamente siguiente, porque canAccessWarehouse lee la asignación viva.
test('AC-011: al retirar al usuario pierde el acceso en la petición siguiente', function (): void {
    $jefe = usuarioConPermisos([PermissionSlug::WarehousesView->value]);
    $almacen = Warehouse::factory()->create();
    $jefe->warehouses()->attach($almacen->id);

    actuarComo($jefe);

    $this->getJson("/api/v1/warehouses/{$almacen->id}")->assertOk();

    $jefe->warehouses()->detach($almacen->id);

    $this->getJson("/api/v1/warehouses/{$almacen->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');

    $ids = collect($this->getJson('/api/v1/warehouses')->assertOk()->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->not->toContain($almacen->id);
});

// AC-055: con alcance limitado, el detalle de un almacén ajeno se niega con
// FUERA_DE_ALCANCE (tiene el permiso, pero el almacén no está en su alcance).
test('AC-055: con alcance limitado el detalle de otro almacén da 403 FUERA_DE_ALCANCE', function (): void {
    $jefe = usuarioConPermisos([PermissionSlug::WarehousesView->value]);
    $propio = Warehouse::factory()->create();
    $ajeno = Warehouse::factory()->create();
    $jefe->warehouses()->attach($propio->id);

    actuarComo($jefe);

    $this->getJson("/api/v1/warehouses/{$propio->id}")->assertOk();

    $this->getJson("/api/v1/warehouses/{$ajeno->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');
});

// Matriz: el Administrador con warehouses.view_all ve todos los almacenes,
// incluidos los que no tiene asignados, y puede abrir cualquiera.
test('matriz: el administrador con view_all ve todos los almacenes', function (): void {
    $admin = usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesViewAll->value,
    ]);
    $almacenes = Warehouse::factory()->count(3)->create();

    actuarComo($admin);

    $ids = collect($this->getJson('/api/v1/warehouses')->assertOk()->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->toHaveCount(3);

    $this->getJson('/api/v1/warehouses/'.$almacenes->first()->id)->assertOk();
});

// Matriz: el rol Auditor lee pero no puede mutar; sin endpoint de escritura en
// esta unidad lo compruebo sobre la Policy y los Gates del catálogo.
test('matriz: el auditor lee pero no puede mutar', function (): void {
    $auditor = usuarioConPermisos([PermissionSlug::WarehousesView->value]);
    $almacen = Warehouse::factory()->create();
    $auditor->warehouses()->attach($almacen->id);

    actuarComo($auditor);

    $this->getJson("/api/v1/warehouses/{$almacen->id}")->assertOk();

    expect($auditor->can('update', $almacen))->toBeFalse();
    expect($auditor->can('deactivate', $almacen))->toBeFalse();
    expect(Gate::forUser($auditor)->allows(PermissionSlug::WarehousesUpdate->value))->toBeFalse();
});

// Matriz: sin sesión, la API protegida responde 401 con el sobre uniforme.
test('matriz: sin sesión la API responde 401 NO_AUTENTICADO', function (): void {
    $this->getJson('/api/v1/warehouses')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');
});

// El listado debe respetar la forma paginada documentada: data, meta y links.
test('el listado entrega data, meta y links con la paginación del §4', function (): void {
    $admin = usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesViewAll->value,
    ]);
    Warehouse::factory()->count(3)->create();

    actuarComo($admin);

    $respuesta = $this->getJson('/api/v1/warehouses?per_page=2')->assertOk();

    $respuesta->assertJsonStructure([
        'data',
        'links',
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
    ]);

    expect($respuesta->json('meta.per_page'))->toBe(2);
    expect($respuesta->json('meta.total'))->toBe(3);
    expect($respuesta->json('data'))->toHaveCount(2);
});

// El listado filtra por ciudad y por actividad, y busca por código, siempre
// dentro del alcance del usuario.
test('el listado filtra por ciudad y actividad y busca por código', function (): void {
    $admin = usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesViewAll->value,
    ]);
    Warehouse::factory()->create(['code' => 'WH-BOG-01', 'city' => 'Bogota', 'is_active' => true]);
    Warehouse::factory()->create(['code' => 'WH-MED-02', 'city' => 'Medellin', 'is_active' => false]);

    actuarComo($admin);

    $porCiudad = collect($this->getJson('/api/v1/warehouses?city=Bogota')->assertOk()->json('data'))
        ->pluck('code')
        ->all();
    expect($porCiudad)->toBe(['WH-BOG-01']);

    $inactivos = collect($this->getJson('/api/v1/warehouses?is_active=0')->assertOk()->json('data'))
        ->pluck('code')
        ->all();
    expect($inactivos)->toBe(['WH-MED-02']);

    $busqueda = collect($this->getJson('/api/v1/warehouses?q=MED')->assertOk()->json('data'))
        ->pluck('code')
        ->all();
    expect($busqueda)->toBe(['WH-MED-02']);
});

// El orden acepta solo las columnas del §4 y el prefijo descendente; cualquier
// otra produce 422 VALIDACION.
test('el listado ordena por la columna permitida y rechaza la no permitida', function (): void {
    $admin = usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesViewAll->value,
    ]);
    Warehouse::factory()->create(['code' => 'WH-C']);
    Warehouse::factory()->create(['code' => 'WH-A']);
    Warehouse::factory()->create(['code' => 'WH-B']);

    actuarComo($admin);

    $ascendente = collect($this->getJson('/api/v1/warehouses?sort=code')->assertOk()->json('data'))
        ->pluck('code')
        ->all();
    expect($ascendente)->toBe(['WH-A', 'WH-B', 'WH-C']);

    $descendente = collect($this->getJson('/api/v1/warehouses?sort=-code')->assertOk()->json('data'))
        ->pluck('code')
        ->all();
    expect($descendente)->toBe(['WH-C', 'WH-B', 'WH-A']);

    $this->getJson('/api/v1/warehouses?sort=password')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// El detalle de un almacén inexistente responde 404 con el sobre uniforme.
test('un almacén inexistente responde 404 NO_ENCONTRADO', function (): void {
    $admin = usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesViewAll->value,
    ]);
    actuarComo($admin);

    $this->getJson('/api/v1/warehouses/999999')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NO_ENCONTRADO');
});

// El detalle expone responsable y autorizados solo al cargarlos, sin filtrar
// password, remember_token ni tokens (C-07/C-08).
test('el detalle expone responsable y autorizados sin secretos', function (): void {
    $admin = usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesViewAll->value,
    ]);
    $gerente = User::factory()->create(['name' => 'Gerente Uno', 'email' => 'gerente@example.com']);
    $almacen = Warehouse::factory()->create(['manager_user_id' => $gerente->id]);
    $almacen->users()->attach($gerente->id);

    actuarComo($admin);

    $respuesta = $this->getJson("/api/v1/warehouses/{$almacen->id}")->assertOk();

    $respuesta->assertJsonPath('data.manager.email', 'gerente@example.com');
    $respuesta->assertJsonStructure([
        'data' => [
            'id', 'code', 'name', 'address', 'city', 'is_active',
            'manager' => ['id', 'name', 'email'],
            'users' => [['id', 'name', 'email']],
        ],
    ]);

    $plano = json_encode($respuesta->json());
    expect($plano)->not->toContain('password');
    expect($plano)->not->toContain('remember_token');
});
