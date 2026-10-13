<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Pruebo los productos contra MySQL real porque la unicidad del SKU y del
// código de barras, las FK con RESTRICT y la UNIQUE (product_id, warehouse_id)
// viven en el motor definitivo.
uses(RefreshDatabase::class);

if (! function_exists('productoBase')) {
    // Armo el cuerpo válido de creación porque casi todas las pruebas parten
    // del mismo alta y solo varían un campo para provocar cada 422.
    function productoBase(?Category $categoria = null, ?UnitOfMeasure $unidad = null): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'name' => 'Producto '.fake()->unique()->words(2, true),
            'category_id' => ($categoria ?? Category::factory()->create())->id,
            'unit_of_measure_id' => ($unidad ?? UnitOfMeasure::factory()->create())->id,
            'cost_price' => '12.50',
            'sale_price' => '19.99',
        ];
    }
}

if (! function_exists('jefeCatalogoConAlmacen')) {
    // Creo un usuario con permiso de catálogo y alcance a un solo almacén,
    // porque las pruebas de FUERA_DE_ALCANCE necesitan el permiso concedido
    // pero un almacén ajeno fuera de su warehouse_user.
    function jefeCatalogoConAlmacen(Warehouse $almacen): User
    {
        $jefe = usuarioConPermisos([
            PermissionSlug::CatalogCreate->value,
            PermissionSlug::CatalogUpdate->value,
        ]);
        $jefe->warehouses()->attach($almacen->id);

        return $jefe->fresh();
    }
}

// AC-016: crear con SKU nuevo, categoría y unidad activas responde 201 y deja
// la ficha consultable, con los montos como cadena (AC-022 verifica el costeo).
test('AC-016: crear producto responde 201 con montos como cadena', function (): void {
    actuarComo(administradorCatalogo());

    $respuesta = $this->postJson('/api/v1/products', productoBase());

    $respuesta->assertCreated()
        ->assertJsonPath('data.costing_method', 'promedio_ponderado')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.cost_price', '12.50')
        ->assertJsonPath('data.sale_price', '19.99');

    $cuerpo = $respuesta->json('data');
    expect($cuerpo['cost_price'])->toBeString();
    expect($cuerpo['sale_price'])->toBeString();

    $id = $respuesta->json('data.id');

    $this->getJson("/api/v1/products/{$id}")->assertOk()
        ->assertJsonPath('data.id', $id);
});

// AC-017: el SKU duplicado se rechaza al crear.
test('AC-017: SKU duplicado al crear responde 422 VALIDACION', function (): void {
    actuarComo(administradorCatalogo());

    $primero = productoBase();
    $this->postJson('/api/v1/products', $primero)->assertCreated();

    $reintento = productoBase();
    $reintento['sku'] = $primero['sku'];

    $this->postJson('/api/v1/products', $reintento)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.field', 'sku');
});

// AC-017: el SKU es inmutable y cualquier intento de cambio en el PUT da 422,
// aunque el valor nuevo sea único (lectura conservadora del contrato).
test('AC-017: cambiar el SKU en la edición responde 422', function (): void {
    actuarComo(administradorCatalogo());

    $id = $this->postJson('/api/v1/products', productoBase())->assertCreated()->json('data.id');

    $this->putJson("/api/v1/products/{$id}", ['sku' => 'SKU-NUEVO-001'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.field', 'sku');

    expect(Product::find($id)->sku)->not->toBe('SKU-NUEVO-001');
});

// RF-037: el código de barras es único cuando viene informado, pero varios
// productos sin código conviven (la UNIQUE admite varios NULL).
test('código duplicado da 422 y varios sin código conviven', function (): void {
    actuarComo(administradorCatalogo());

    $conCodigo = productoBase();
    $conCodigo['barcode'] = '7701234567890';
    $this->postJson('/api/v1/products', $conCodigo)->assertCreated();

    $duplicado = productoBase();
    $duplicado['barcode'] = '7701234567890';
    $this->postJson('/api/v1/products', $duplicado)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $this->postJson('/api/v1/products', productoBase())->assertCreated();
    $this->postJson('/api/v1/products', productoBase())->assertCreated();

    $this->assertDatabaseCount('products', 3);
});

// RF-035 y AC-013: crear con categoría inactiva da 422 con mensaje claro.
test('crear con categoría inactiva responde 422', function (): void {
    actuarComo(administradorCatalogo());

    $inactiva = Category::factory()->create(['is_active' => false]);

    $cuerpo = productoBase($inactiva);

    $this->postJson('/api/v1/products', $cuerpo)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.field', 'category_id');

    $this->assertDatabaseCount('products', 0);
});

// Los precios negativos se rechazan con 422 (decisión de la Unidad 2).
test('costo o precio negativo responde 422', function (): void {
    actuarComo(administradorCatalogo());

    $conCostoNegativo = productoBase();
    $conCostoNegativo['cost_price'] = '-1.00';
    $this->postJson('/api/v1/products', $conCostoNegativo)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $conPrecioNegativo = productoBase();
    $conPrecioNegativo['sale_price'] = '-5.00';
    $this->postJson('/api/v1/products', $conPrecioNegativo)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $this->assertDatabaseCount('products', 0);
});

// RF-047: la edición actualiza nombre, precios y estado sin tocar el SKU.
test('editar nombre, precios y estado responde 200', function (): void {
    actuarComo(administradorCatalogo());

    $id = $this->postJson('/api/v1/products', productoBase())->assertCreated()->json('data');

    $this->putJson("/api/v1/products/{$id['id']}", [
        'sku' => $id['sku'],
        'name' => 'Producto Renombrado',
        'cost_price' => '15.00',
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Producto Renombrado')
        ->assertJsonPath('data.cost_price', '15.00')
        ->assertJsonPath('data.sku', $id['sku']);

    expect(Product::find($id['id'])->name)->toBe('Producto Renombrado');
});

// RF-050 y AC-021: la ficha trae categoría, unidad y configuración visible,
// pero omite cantidades y costo promedio porque inventories llega con el kardex.
test('la ficha omite cantidades y trae relaciones con decimales como cadena', function (): void {
    $propio = Warehouse::factory()->create();
    $ajeno = Warehouse::factory()->create();
    actuarComo(jefeCatalogoConAlmacen($propio));

    $id = $this->postJson('/api/v1/products', productoBase())->assertCreated()->json('data.id');

    $this->putJson("/api/v1/products/{$id}/warehouses/{$propio->id}", [
        'min_stock' => '5.000',
        'max_stock' => '50.000',
    ])->assertOk();

    $ficha = $this->getJson("/api/v1/products/{$id}")->assertOk()->json('data');

    expect($ficha)->toHaveKeys(['category', 'unit_of_measure', 'warehouses'])
        ->and($ficha)->not->toHaveKeys(['quantity', 'average_cost', 'stock'])
        ->and($ficha['warehouses'])->toHaveCount(1)
        ->and($ficha['warehouses'][0]['warehouse_id'])->toBe($propio->id)
        ->and($ficha['warehouses'][0]['min_stock'])->toBe('5.000')
        ->and($ficha['warehouses'][0]['min_stock'])->toBeString();
});

// RF-046 y AC-020: el listado pagina, filtra, busca y ordena según el §7.7.
test('listar pagina con filtros, búsqueda y orden', function (): void {
    $admin = administradorCatalogo();

    $categoria = Category::factory()->create(['name' => 'Ferretería']);
    $otra = Category::factory()->create(['name' => 'Pinturas']);
    $almacen = Warehouse::factory()->create();

    // Asigno el almacén al administrador porque la escritura de configuración
    // exige alcance además del permiso: sin asignación daría FUERA_DE_ALCANCE.
    $admin->warehouses()->attach($almacen->id);
    actuarComo($admin);

    $tornillo = productoBase($categoria);
    $tornillo['sku'] = 'TOR-001';
    $tornillo['name'] = 'Tornillo hexagonal';
    $idTornillo = $this->postJson('/api/v1/products', $tornillo)->assertCreated()->json('data.id');

    $pintura = productoBase($otra);
    $pintura['sku'] = 'PIN-001';
    $pintura['name'] = 'Pintura blanca';
    $pintura['barcode'] = '7700000000011';
    $idPintura = $this->postJson('/api/v1/products', $pintura)->assertCreated()->json('data.id');

    $inactivo = productoBase($categoria);
    $inactivo['sku'] = 'INA-001';
    $inactivo['name'] = 'Tornillo viejo';
    $idInactivo = $this->postJson('/api/v1/products', $inactivo)->assertCreated()->json('data.id');
    $this->putJson("/api/v1/products/{$idInactivo}", ['is_active' => false])->assertOk();

    $this->putJson("/api/v1/products/{$idTornillo}/warehouses/{$almacen->id}", ['min_stock' => '1.000'])->assertOk();

    $pagina = $this->getJson('/api/v1/products?per_page=2')->assertOk()->json();
    expect($pagina['data'])->toHaveCount(2);
    expect($pagina['meta'])->toHaveKeys(['current_page', 'per_page', 'total', 'last_page']);
    expect($pagina['meta']['total'])->toBe(3);
    expect($pagina)->toHaveKey('links');

    $activos = collect($this->getJson('/api/v1/products?is_active=1&per_page=100')->assertOk()->json('data'))
        ->pluck('sku')->all();
    expect($activos)->toContain('TOR-001', 'PIN-001');
    expect($activos)->not->toContain('INA-001');

    $porCategoria = collect($this->getJson("/api/v1/products?category_id={$otra->id}&per_page=100")->assertOk()->json('data'))
        ->pluck('sku')->all();
    expect($porCategoria)->toBe(['PIN-001']);

    $porAlmacen = collect($this->getJson("/api/v1/products?warehouse_id={$almacen->id}&per_page=100")->assertOk()->json('data'))
        ->pluck('sku')->all();
    expect($porAlmacen)->toBe(['TOR-001']);

    $porSku = collect($this->getJson('/api/v1/products?q=TOR-001&per_page=100')->assertOk()->json('data'))
        ->pluck('sku')->all();
    expect($porSku)->toBe(['TOR-001']);

    $porNombre = collect($this->getJson('/api/v1/products?q=tornillo&per_page=100')->assertOk()->json('data'))
        ->pluck('sku')->all();
    expect($porNombre)->toContain('TOR-001', 'INA-001');

    $porBarras = collect($this->getJson('/api/v1/products?q=7700000000011&per_page=100')->assertOk()->json('data'))
        ->pluck('sku')->all();
    expect($porBarras)->toBe(['PIN-001']);

    $orden = collect($this->getJson('/api/v1/products?sort=-sku&per_page=100')->assertOk()->json('data'))
        ->pluck('sku')->all();
    expect($orden)->toBe(['TOR-001', 'PIN-001', 'INA-001']);
});

// Un `sort` fuera de la lista cerrada responde 422, no 500.
test('orden no documentado en productos responde 422 VALIDACION', function (): void {
    actuarComo(administradorCatalogo());

    $this->getJson('/api/v1/products?sort=barcode')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// RF-040: desactivar apaga el producto, conserva el registro y repetir da 409.
test('desactivar apaga el producto, lo conserva y repetir da 409', function (): void {
    actuarComo(administradorCatalogo());

    $id = $this->postJson('/api/v1/products', productoBase())->assertCreated()->json('data.id');

    $this->postJson("/api/v1/products/{$id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $producto = Product::find($id);
    expect($producto)->not->toBeNull();
    expect($producto->is_active)->toBeFalse();

    $this->postJson("/api/v1/products/{$id}/deactivate")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');
});

// La configuración por almacén se crea y se actualiza con decimales como
// cadena, y el máximo menor que el mínimo da 422.
test('configurar almacén crea y actualiza; máximo menor que mínimo da 422', function (): void {
    $almacen = Warehouse::factory()->create();
    actuarComo(jefeCatalogoConAlmacen($almacen));

    $id = $this->postJson('/api/v1/products', productoBase())->assertCreated()->json('data.id');

    $creada = $this->putJson("/api/v1/products/{$id}/warehouses/{$almacen->id}", [
        'min_stock' => '5.000',
        'max_stock' => '50.000',
        'reorder_point' => '10.000',
    ])->assertOk()->json('data');

    expect($creada['min_stock'])->toBe('5.000');
    expect($creada['max_stock'])->toBe('50.000');
    expect($creada['reorder_point'])->toBe('10.000');
    expect($creada['min_stock'])->toBeString();

    $actualizada = $this->putJson("/api/v1/products/{$id}/warehouses/{$almacen->id}", [
        'min_stock' => '8.000',
    ])->assertOk()->json('data');

    expect($actualizada['min_stock'])->toBe('8.000');
    expect($actualizada['max_stock'])->toBe('50.000');

    $this->putJson("/api/v1/products/{$id}/warehouses/{$almacen->id}", [
        'min_stock' => '60.000',
        'max_stock' => '50.000',
    ])->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $lista = $this->getJson("/api/v1/products/{$id}/warehouses")->assertOk()->json('data');
    expect($lista)->toHaveCount(1);
    expect($lista[0]['min_stock'])->toBe('8.000');
});

// Con el permiso pero fuera de alcance, la escritura de configuración da 403
// FUERA_DE_ALCANCE y la lectura solo muestra los almacenes propios.
test('almacén fuera de alcance da 403 FUERA_DE_ALCANCE y se oculta en lectura', function (): void {
    $propio = Warehouse::factory()->create();
    $ajeno = Warehouse::factory()->create();
    actuarComo(jefeCatalogoConAlmacen($propio));

    $id = $this->postJson('/api/v1/products', productoBase())->assertCreated()->json('data.id');

    $this->putJson("/api/v1/products/{$id}/warehouses/{$ajeno->id}", ['min_stock' => '1.000'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');

    $this->assertDatabaseMissing('product_warehouse', [
        'product_id' => $id,
        'warehouse_id' => $ajeno->id,
    ]);

    $this->putJson("/api/v1/products/{$id}/warehouses/{$propio->id}", ['min_stock' => '2.000'])->assertOk();

    $lista = $this->getJson("/api/v1/products/{$id}/warehouses")->assertOk()->json('data');
    expect($lista)->toHaveCount(1);
    expect($lista[0]['warehouse_id'])->toBe($propio->id);
});

// Matriz: sin sesión hay 401 en los 7 endpoints; con sesión pero sin permiso
// de escritura hay 403 SIN_PERMISO en las 4 escrituras.
test('matriz: lecturas anónimas dan 401 y escrituras sin permiso dan 403', function (): void {
    actuarComo(administradorCatalogo());
    $id = $this->postJson('/api/v1/products', productoBase())->assertCreated()->json('data.id');
    $almacen = Warehouse::factory()->create();

    // Descarto los guardias cacheados para volver al estado anónimo: Sanctum
    // resolvía al administrador desde la memoria del proceso de pruebas y sin
    // esto ninguna petición ejercería el 401 real (patrón de TestCase).
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/products')->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->getJson("/api/v1/products/{$id}")->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->getJson("/api/v1/products/{$id}/warehouses")->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->postJson('/api/v1/products', productoBase())->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->putJson("/api/v1/products/{$id}", ['name' => 'X'])->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->postJson("/api/v1/products/{$id}/deactivate")->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->putJson("/api/v1/products/{$id}/warehouses/{$almacen->id}", ['min_stock' => '1.000'])->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');

    actuarComo(usuarioConPermisos([]));

    $this->postJson('/api/v1/products', productoBase())
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');
    $this->putJson("/api/v1/products/{$id}", ['name' => 'X'])
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');
    $this->postJson("/api/v1/products/{$id}/deactivate")
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');
    $this->putJson("/api/v1/products/{$id}/warehouses/{$almacen->id}", ['min_stock' => '1.000'])
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');

    // Las lecturas con sesión valen sin permiso de catálogo.
    $this->getJson('/api/v1/products')->assertOk();
    $this->getJson("/api/v1/products/{$id}")->assertOk();
    $this->getJson("/api/v1/products/{$id}/warehouses")->assertOk();
});

// Cierre de la Unidad 1: el detalle de la categoría expone el conteo de
// productos del contrato.
test('cierre Unidad 1: el detalle de categoría expone products_count', function (): void {
    actuarComo(administradorCatalogo());

    $categoria = Category::factory()->create();
    $this->postJson('/api/v1/products', productoBase($categoria))->assertCreated();
    $this->postJson('/api/v1/products', productoBase($categoria))->assertCreated();

    $this->getJson("/api/v1/categories/{$categoria->id}")->assertOk()
        ->assertJsonPath('data.products_count', 2);
});

// Cierre de la Unidad 1: desactivar una categoría con productos activos da 409
// ESTADO_INVALIDO y la deja encendida.
test('cierre Unidad 1: desactivar categoría con productos da 409', function (): void {
    actuarComo(administradorCatalogo());

    $categoria = Category::factory()->create(['is_active' => true]);
    $this->postJson('/api/v1/products', productoBase($categoria))->assertCreated();

    $this->postJson("/api/v1/categories/{$categoria->id}/deactivate")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');

    expect($categoria->fresh()->is_active)->toBeTrue();
});

// La bitácora registra el alta del producto con acción, entidad y valores.
test('auditoría: el alta de producto deja entrada con acción y valores', function (): void {
    $admin = administradorCatalogo();
    actuarComo($admin);

    $cuerpo = productoBase();
    $id = $this->postJson('/api/v1/products', $cuerpo)->assertCreated()->json('data.id');

    $entrada = AuditLog::query()->where('entity_type', 'product')->where('entity_id', $id)->firstOrFail();

    expect($entrada->action)->toBe('crear_producto')
        ->and($entrada->user_id)->toBe($admin->id)
        ->and($entrada->new_values['sku'] ?? null)->toBe($cuerpo['sku'])
        ->and($entrada->old_values)->toBeNull();
});

// La bitácora registra la configuración por almacén con sus valores.
test('auditoría: la configuración por almacén deja entrada con valores', function (): void {
    $almacen = Warehouse::factory()->create();
    actuarComo(jefeCatalogoConAlmacen($almacen));

    $id = $this->postJson('/api/v1/products', productoBase())->assertCreated()->json('data.id');

    $this->putJson("/api/v1/products/{$id}/warehouses/{$almacen->id}", [
        'min_stock' => '3.000',
    ])->assertOk();

    $entrada = AuditLog::query()->where('entity_type', 'product_warehouse')->firstOrFail();

    expect($entrada->action)->toBe('configurar_producto_almacen')
        ->and($entrada->new_values['min_stock'] ?? null)->toBe('3.000');
});
