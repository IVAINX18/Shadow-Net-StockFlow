<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DevAdminUserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

// Ejerzo los seeders sobre MySQL real porque la idempotencia depende de las
// restricciones únicas y del sync de los pivotes, no de una base en memoria.
uses(RefreshDatabase::class);

// Siembro permisos y roles juntos porque los roles referencian permisos y el
// orden importa: sin catálogo previo el sync quedaría vacío.
function sembrarCatalogo(): void
{
    (new PermissionSeeder)->run();
    (new RoleSeeder)->run();
}

// Devuelvo los slugs de un rol ordenados porque así comparo la matriz exacta
// sin que el orden de inserción del pivote falsee la aserción.
function slugsDeRol(string $slug): array
{
    $role = Role::query()->where('slug', $slug)->firstOrFail();

    return $role->permissions()->pluck('slug')->sort()->values()->all();
}

// Fijo las tres variables en los tres canales porque env() puede leer de
// cualquiera de ellos según cómo haya arrancado el proceso de pruebas.
function fijarVarsDevAdmin(?string $email, ?string $password, ?string $nombre = null): void
{
    foreach (['DEV_ADMIN_EMAIL' => $email, 'DEV_ADMIN_PASSWORD' => $password, 'DEV_ADMIN_NAME' => $nombre] as $clave => $valor) {
        if ($valor === null) {
            putenv($clave);
            unset($_ENV[$clave], $_SERVER[$clave]);
        } else {
            putenv("{$clave}={$valor}");
            $_ENV[$clave] = $valor;
            $_SERVER[$clave] = $valor;
        }
    }
}

test('sembrar crea exactamente 19 permisos y los cuatro roles', function (): void {
    sembrarCatalogo();

    expect(Permission::count())->toBe(19)
        ->and(Role::count())->toBe(4)
        ->and(Permission::pluck('slug')->sort()->values()->all())
        ->toBe(collect(PermissionSlug::values())->sort()->values()->all());
});

test('sembrar dos veces no duplica nada', function (): void {
    sembrarCatalogo();

    $permisos = Permission::count();
    $roles = Role::count();
    $delAdmin = Role::query()->where('slug', 'administrador')->firstOrFail()->permissions()->count();

    sembrarCatalogo();

    // Releo los conteos porque la idempotencia se demuestra contra la base,
    // no contra la memoria del proceso de pruebas.
    expect(Permission::count())->toBe($permisos)
        ->and(Role::count())->toBe($roles)
        ->and(Role::query()->where('slug', 'administrador')->firstOrFail()->permissions()->count())
        ->toBe($delAdmin);
});

test('el administrador recibe los 19 permisos', function (): void {
    sembrarCatalogo();

    expect(slugsDeRol('administrador'))
        ->toBe(collect(PermissionSlug::values())->sort()->values()->all());
});

test('el jefe de almacén recibe exactamente sus once permisos', function (): void {
    sembrarCatalogo();

    expect(slugsDeRol('jefe-de-almacen'))->toBe([
        'audit.view',
        'catalog.create',
        'catalog.update',
        'inventory.confirm',
        'inventory.create',
        'inventory.reverse',
        'inventory.view',
        'reports.export',
        'reports.view',
        'warehouses.update',
        'warehouses.view',
    ]);
});

test('el operador recibe exactamente sus cuatro permisos', function (): void {
    sembrarCatalogo();

    expect(slugsDeRol('operador'))->toBe([
        'inventory.confirm',
        'inventory.create',
        'inventory.view',
        'warehouses.view',
    ]);
});

// AC-006: el auditor solo lee, así que inventory.confirm debe faltar aunque el
// resto de la matriz de lectura esté completa.
test('el auditor solo lee y no confirma', function (): void {
    sembrarCatalogo();

    expect(slugsDeRol('auditor'))->toBe([
        'audit.view',
        'inventory.view',
        'reports.view',
        'warehouses.view',
    ])->and(slugsDeRol('auditor'))->not->toContain('inventory.confirm');
});

test('el permiso se resuelve por rol y no sin rol', function (): void {
    sembrarCatalogo();

    $admin = User::factory()->create(['is_active' => true]);
    $admin->roles()->attach(Role::query()->where('slug', 'administrador')->firstOrFail());
    $admin = $admin->fresh();

    $sinRol = User::factory()->create(['is_active' => true]);

    expect($admin->hasPermission('warehouses.view'))->toBeTrue()
        ->and($admin->can('warehouses.view'))->toBeTrue()
        ->and($sinRol->hasPermission('warehouses.view'))->toBeFalse()
        ->and($sinRol->can('warehouses.view'))->toBeFalse();
});

test('con vars crea al admin de desarrollo con el rol administrador', function (): void {
    sembrarCatalogo();
    fijarVarsDevAdmin('dev-admin@stockflow.local', 'clave-temporal-2026', 'Admin Dev');

    try {
        (new DevAdminUserSeeder)->run();
    } finally {
        fijarVarsDevAdmin(null, null, null);
    }

    $user = User::query()->where('email', 'dev-admin@stockflow.local')->firstOrFail();

    // Compruebo el hash en vez del texto plano porque la clave nunca debe
    // quedar legible en la base ni en la salida de las pruebas.
    expect($user->is_active)->toBeTrue()
        ->and(Hash::check('clave-temporal-2026', $user->password))->toBeTrue()
        ->and($user->roles()->pluck('slug')->all())->toBe(['administrador']);
});

test('en producción no crea al administrador aunque haya vars', function (): void {
    sembrarCatalogo();
    fijarVarsDevAdmin('no-crear@stockflow.local', 'clave-temporal-2026', 'Admin Dev');

    $entorno = $this->app['env'];
    $this->app['env'] = 'production';

    try {
        (new DevAdminUserSeeder)->run();
    } finally {
        $this->app['env'] = $entorno;
        fijarVarsDevAdmin(null, null, null);
    }

    // No dejo rastro en producción aunque el operador haya dejado las vars
    // puestas: el guardia existe justo para ese descuido.
    expect(User::query()->where('email', 'no-crear@stockflow.local')->exists())->toBeFalse();
});

test('sin vars no crea al administrador', function (): void {
    sembrarCatalogo();
    fijarVarsDevAdmin(null, null, null);

    (new DevAdminUserSeeder)->run();

    expect(User::count())->toBe(0);
});
