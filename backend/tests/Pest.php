<?php

use App\Domain\Enums\PermissionSlug;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Vinculo mis tests de Feature al TestCase de Laravel para tener el
// contenedor y las aserciones HTTP disponibles sin tocar la base de datos.
uses(TestCase::class)->in('Feature');

// Defino aquí los ayudantes compartidos porque Pest carga este archivo en
// cada ejecución: antes vivían en archivos sueltos tras una guarda
// function_exists y correr un módulo aislado fallaba con funciones sin definir.
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

// Reúno los cuatro permisos de almacenes porque el alta, la edición y la
// consulta con alcance los exigen juntos en las pruebas de escritura.
function administradorAlmacenes(): User
{
    return usuarioConPermisos([
        PermissionSlug::WarehousesView->value,
        PermissionSlug::WarehousesViewAll->value,
        PermissionSlug::WarehousesCreate->value,
        PermissionSlug::WarehousesUpdate->value,
    ]);
}

// Reúno los dos permisos de catálogo porque la importación y la captura
// manual exigen ambos para crear y actualizar en las pruebas.
function administradorCatalogo(): User
{
    return usuarioConPermisos([
        PermissionSlug::CatalogCreate->value,
        PermissionSlug::CatalogUpdate->value,
    ]);
}

// Creo la unidad entera UND porque casi todas las pruebas de inventario e
// importación parten de un producto sin decimales. Vivía en el test de
// catálogo y correr el módulo de inventario aislado fallaba sin ella.
function unidadBase(): UnitOfMeasure
{
    return UnitOfMeasure::factory()->create(['code' => 'UND', 'name' => 'Unidad']);
}

// Creo un usuario con lectura, creación, confirmación y reversión de
// inventario, porque confirmar y revertir exigen sus propios permisos (§7.8)
// y el alcance se lo doy por almacén en cada prueba.
function confirmadorInventario(): User
{
    return usuarioConPermisos([
        PermissionSlug::InventoryView->value,
        PermissionSlug::InventoryCreate->value,
        PermissionSlug::InventoryConfirm->value,
        PermissionSlug::InventoryReverse->value,
    ]);
}

// Ato el usuario a los almacenes indicados porque el alcance por almacén se
// resuelve en la consulta con warehouse_user y sin atadura todo rebota con
// FUERA_DE_ALCANCE.
function conAlmacenes(User $usuario, Warehouse ...$almacenes): User
{
    $usuario->warehouses()->attach(collect($almacenes)->map->id->all());

    return $usuario->fresh();
}

// Armo el cuerpo válido de un documento de entrada porque casi todas las
// pruebas de confirmación parten del mismo alta y solo varían un campo. Uso
// cantidad entera porque la unidad de la factoría no admite decimales y la
// confirmación lo verifica (AC-014).
function cuerpoEntrada(Warehouse $origen, ?Product $producto = null, string $cantidad = '2.000', string $costo = '10.250000'): array
{
    return [
        'type' => 'entrada',
        'origin_warehouse_id' => $origen->id,
        'lines' => [
            [
                'product_id' => ($producto ?? Product::factory()->create())->id,
                'quantity' => $cantidad,
                'unit_cost' => $costo,
            ],
        ],
    ];
}

// Creo el borrador por la API con clave única, porque es el camino que ejerce
// la idempotencia, el alcance y la auditoría de una sola vez. Devuelvo el uuid
// público para operar sobre él en la prueba.
function crearBorradorApi(TestCase $prueba, User $autor, array $cuerpo, ?string $clave = null): string
{
    actuarComo($autor);

    $respuesta = $prueba->postJson(
        '/api/v1/inventory-documents',
        $cuerpo,
        ['Idempotency-Key' => $clave ?? (string) Str::uuid()]
    );

    $respuesta->assertCreated();

    return $respuesta->json('data.uuid');
}

// Confirmo por la API con clave única y devuelvo la respuesta para que cada
// prueba afirme el estado que espera (200, 409 o 422).
function confirmarApi(TestCase $prueba, User $autor, string $uuid, ?string $clave = null): TestResponse
{
    actuarComo($autor);

    return $prueba->postJson(
        "/api/v1/inventory-documents/{$uuid}/confirm",
        [],
        ['Idempotency-Key' => $clave ?? (string) Str::uuid()]
    );
}

// Leo el saldo materializado como cadena normalizada porque los casts del
// modelo devuelven decimales con su escala y la comparación exacta los exige.
function saldoDe(int $productoId, int $almacenId): string
{
    $fila = Inventory::query()
        ->where('product_id', $productoId)
        ->where('warehouse_id', $almacenId)
        ->first();

    return $fila === null ? '0.000' : bcadd((string) $fila->quantity, '0', 3);
}
