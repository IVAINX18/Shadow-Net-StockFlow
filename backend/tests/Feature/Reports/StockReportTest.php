<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Pruebo el reporte de existencias y el de faltantes contra MySQL real porque
// los totales usan la aritmética DECIMAL del motor y el bajo mínimo compara la
// existencia contra la configuración min/max por almacén (RF-080, RF-091).
uses(RefreshDatabase::class);

// Reúno lectura de inventario y de reportes porque el reporte de existencias
// exige `reports.view` (§7.11) mientras que alimentar el historial exige los
// permisos de inventario: el rol Auditor solo tiene los de lectura (RF-115).
function reportadorStock(): User
{
    return usuarioConPermisos([
        PermissionSlug::InventoryView->value,
        PermissionSlug::InventoryCreate->value,
        PermissionSlug::InventoryConfirm->value,
        PermissionSlug::ReportsView->value,
    ]);
}

// RF-091: el reporte lista cantidad, costo promedio y valor por producto y
// almacén, con totales coherentes con el detalle (RF-102, AC-047).
test('el reporte de existencias lista cantidad, costo y valor con totales', function (): void {
    $almacen = Warehouse::factory()->create();
    $lector = conAlmacenes(reportadorStock(), $almacen);
    $producto = Product::factory()->create();

    confirmarApi($this, $lector, crearBorradorApi($this, $lector, cuerpoEntrada($almacen, $producto, '10.000', '10.000000')))->assertOk();

    actuarComo($lector);
    $respuesta = $this->getJson('/api/v1/reports/stock');

    $respuesta->assertOk()
        ->assertJsonPath('data.0.product_id', $producto->id)
        ->assertJsonPath('data.0.warehouse_id', $almacen->id)
        ->assertJsonPath('data.0.quantity', '10.000')
        ->assertJsonPath('data.0.average_cost', '10.000000')
        ->assertJsonPath('data.0.stock_value', '100.00')
        ->assertJsonPath('data.0.below_min', false)
        ->assertJsonPath('totals.total_quantity', '10.000')
        ->assertJsonPath('totals.total_value', '100.00');

    // Los decimales viajan como cadena, nunca como flotante (§3).
    expect($respuesta->json('data.0.quantity'))->toBeString();
    expect($respuesta->json('data.0.average_cost'))->toBeString();
});

// RF-080/RF-104: `below_min=true` devuelve solo los pares cuya existencia
// cayó bajo el mínimo configurado; el par sin configuración tiene mínimo 0 y
// nunca aparece porque la existencia no es negativa (I-01).
test('el reporte de faltantes devuelve solo los pares bajo minimo', function (): void {
    $almacen = Warehouse::factory()->create();
    $lector = conAlmacenes(reportadorStock(), $almacen);

    $bajo = Product::factory()->create();
    $holgado = Product::factory()->create();

    ProductWarehouse::factory()->create([
        'product_id' => $bajo->id,
        'warehouse_id' => $almacen->id,
        'min_stock' => '5.000',
    ]);
    ProductWarehouse::factory()->create([
        'product_id' => $holgado->id,
        'warehouse_id' => $almacen->id,
        'min_stock' => '2.000',
    ]);

    confirmarApi($this, $lector, crearBorradorApi($this, $lector, cuerpoEntrada($almacen, $bajo, '3.000', '1.000000')))->assertOk();
    confirmarApi($this, $lector, crearBorradorApi($this, $lector, cuerpoEntrada($almacen, $holgado, '4.000', '1.000000')))->assertOk();

    actuarComo($lector);
    $this->getJson('/api/v1/reports/stock?below_min=true')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product_id', $bajo->id)
        ->assertJsonPath('data.0.below_min', true)
        ->assertJsonPath('data.0.min_stock', '5.000');
});

// El filtro por almacén acota el conjunto y el alcance se resuelve en la
// consulta: un almacén fuera de alcance rebota con 403 FUERA_DE_ALCANCE
// (RF-097) y un almacén ajeno no aparece en el listado sin filtro.
test('el reporte respeta el alcance por almacen', function (): void {
    $propio = Warehouse::factory()->create();
    $ajeno = Warehouse::factory()->create();
    $lector = conAlmacenes(reportadorStock(), $propio);
    $dueno = conAlmacenes(reportadorStock(), $ajeno);

    $productoAjeno = Product::factory()->create();
    confirmarApi($this, $dueno, crearBorradorApi($this, $dueno, cuerpoEntrada($ajeno, $productoAjeno, '7.000', '1.000000')))->assertOk();

    actuarComo($lector);
    $this->getJson('/api/v1/reports/stock')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('totals.total_quantity', '0.000');

    $this->getJson("/api/v1/reports/stock?warehouse_id={$ajeno->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');
});

// Sin `reports.view` el endpoint rebota con 403 SIN_PERMISO: el Operador
// confirma documentos pero no ve reportes (§7.11).
test('el reporte exige el permiso reports.view', function (): void {
    actuarComo(usuarioConPermisos([PermissionSlug::InventoryView->value]));
    $this->getJson('/api/v1/reports/stock')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');
});

// Sin sesión responde 401 NO_AUTENTICADO. Lo aíslo en su propio test porque
// la sesión de Sanctum no se limpia dentro del mismo caso.
test('el reporte sin sesion responde 401 NO_AUTENTICADO', function (): void {
    $this->getJson('/api/v1/reports/stock')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');
});

// El booleano del filtro llega por query string como texto, así que `true`,
// `false`, `1` y `0` deben leerse por su valor real y no por la veracidad de
// la cadena: PHP considera truthy la cadena "false", de modo que leerla con un
// cast ciego invertiría el filtro y `?below_min=false` devolvería los
// faltantes en lugar del listado completo (RF-080). Un valor ajeno al
// booleano se rechaza con 422 y no se interpreta como falso.
test('el filtro below_min interpreta true false 1 y 0 por su valor', function (): void {
    $almacen = Warehouse::factory()->create();
    $lector = conAlmacenes(reportadorStock(), $almacen);

    $bajo = Product::factory()->create();
    $holgado = Product::factory()->create();

    ProductWarehouse::factory()->create([
        'product_id' => $bajo->id,
        'warehouse_id' => $almacen->id,
        'min_stock' => '5.000',
    ]);
    ProductWarehouse::factory()->create([
        'product_id' => $holgado->id,
        'warehouse_id' => $almacen->id,
        'min_stock' => '2.000',
    ]);

    confirmarApi($this, $lector, crearBorradorApi($this, $lector, cuerpoEntrada($almacen, $bajo, '3.000', '1.000000')))->assertOk();
    confirmarApi($this, $lector, crearBorradorApi($this, $lector, cuerpoEntrada($almacen, $holgado, '4.000', '1.000000')))->assertOk();

    actuarComo($lector);

    // Verdadero: solo el par que quedó bajo su mínimo.
    $this->getJson('/api/v1/reports/stock?below_min=true')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product_id', $bajo->id);

    $this->getJson('/api/v1/reports/stock?below_min=1')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Falso: el listado completo, no los faltantes.
    $this->getJson('/api/v1/reports/stock?below_min=false')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson('/api/v1/reports/stock?below_min=0')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson('/api/v1/reports/stock?below_min=quizas')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});
