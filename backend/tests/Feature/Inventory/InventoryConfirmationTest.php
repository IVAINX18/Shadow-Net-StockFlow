<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\InventoryDocument;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Pruebo la confirmación y la reversión contra MySQL real porque los bloqueos
// SELECT ... FOR UPDATE, los CHECK y las UNIQUE solo existen en el motor
// definitivo, y la coherencia kardex-saldo solo es creíble contra ellos.
uses(RefreshDatabase::class);

// AC-023: la entrada confirmada aumenta existencias, escribe el movimiento y
// recalcula el promedio ponderado con la fórmula del §8.1.
test('confirmar entrada aumenta existencias y recalcula el promedio', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    $uuid = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto));

    confirmarApi($this, $jefe, $uuid)
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmado')
        ->assertJsonPath('data.movements.0.direction', 'entrada')
        ->assertJsonPath('data.movements.0.quantity_delta', '2.000')
        ->assertJsonPath('data.movements.0.unit_cost', '10.250000')
        ->assertJsonPath('data.movements.0.balance_after', '2.000');

    expect(saldoDe($producto->id, $almacen->id))->toBe('2.000');
    expect(InventoryMovement::query()->count())->toBe(1);

    // Segunda entrada: (2.0 * 10.25 + 1.0 * 5.00) / 3.0 = 8.50 exacto.
    $otro = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto, '1.000', '5.000000'));
    confirmarApi($this, $jefe, $otro)->assertOk();

    $fila = Inventory::query()->where('product_id', $producto->id)->where('warehouse_id', $almacen->id)->first();
    expect(bcadd((string) $fila->quantity, '0', 3))->toBe('3.000');
    expect(bcadd((string) $fila->average_cost, '0', 6))->toBe('8.500000');
    expect($fila->confirmed_by ?? null)->toBeNull();

    // Cada confirmación deja su entrada de auditoría con responsable.
    expect(AuditLog::query()->where('action', 'confirmar_documento')->count())->toBe(2);
});

// RF-057 y AC-024: la salida sin disponibilidad se rechaza con 409
// STOCK_INSUFICIENTE y ningún efecto parcial (sigue en borrador, sin
// movimientos y con el saldo intacto).
test('confirmar salida sin stock responde 409 sin efectos parciales', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    $uuid = crearBorradorApi($this, $jefe, [
        'type' => 'salida',
        'origin_warehouse_id' => $almacen->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '8.000']],
    ]);

    confirmarApi($this, $jefe, $uuid)
        ->assertConflict()
        ->assertJsonPath('error.code', 'STOCK_INSUFICIENTE')
        ->assertJsonPath('error.details.0.field', 'lines.0.quantity');

    expect(InventoryDocument::query()->where('uuid', $uuid)->first()->status)->toBe('borrador');
    expect(InventoryMovement::query()->count())->toBe(0);
    expect(saldoDe($producto->id, $almacen->id))->toBe('0.000');
});

// La salida válida descuenta y valora al promedio vigente sin alterarlo.
test('confirmar salida descuenta valorada al promedio vigente', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    confirmarApi($this, $jefe, crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto, '10.000', '10.000000')))->assertOk();

    $uuid = crearBorradorApi($this, $jefe, [
        'type' => 'salida',
        'origin_warehouse_id' => $almacen->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '8.000']],
    ]);

    confirmarApi($this, $jefe, $uuid)
        ->assertOk()
        ->assertJsonPath('data.movements.0.direction', 'salida')
        ->assertJsonPath('data.movements.0.quantity_delta', '-8.000')
        ->assertJsonPath('data.movements.0.unit_cost', '10.000000')
        ->assertJsonPath('data.movements.0.balance_after', '2.000');

    expect(saldoDe($producto->id, $almacen->id))->toBe('2.000');
});

// RF-060 y AC-032: el ajuste suma o resta según la dirección de la línea; el
// negativo mayor que la existencia se rechaza sin dejar saldo negativo.
test('confirmar ajuste respeta su signo y rechaza el negativo sin stock', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    confirmarApi($this, $jefe, crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto, '10.000', '10.000000')))->assertOk();

    $positivo = crearBorradorApi($this, $jefe, [
        'type' => 'ajuste',
        'origin_warehouse_id' => $almacen->id,
        'reason_code' => 'inventario_fisico',
        'lines' => [['product_id' => $producto->id, 'quantity' => '5.000', 'direction' => 'entrada', 'unit_cost' => '99.000000']],
    ]);

    confirmarApi($this, $jefe, $positivo)
        ->assertOk()
        ->assertJsonPath('data.movements.0.direction', 'entrada')
        ->assertJsonPath('data.movements.0.quantity_delta', '5.000');

    // El ajuste positivo conserva la valorización: el costo capturado (99) no
    // mueve el promedio vigente (10).
    $fila = Inventory::query()->where('product_id', $producto->id)->where('warehouse_id', $almacen->id)->first();
    expect(bcadd((string) $fila->quantity, '0', 3))->toBe('15.000');
    expect(bcadd((string) $fila->average_cost, '0', 6))->toBe('10.000000');

    $negativo = crearBorradorApi($this, $jefe, [
        'type' => 'ajuste',
        'origin_warehouse_id' => $almacen->id,
        'reason_code' => 'merma',
        'lines' => [['product_id' => $producto->id, 'quantity' => '3.000', 'direction' => 'salida']],
    ]);

    confirmarApi($this, $jefe, $negativo)
        ->assertOk()
        ->assertJsonPath('data.movements.0.quantity_delta', '-3.000');

    expect(saldoDe($producto->id, $almacen->id))->toBe('12.000');

    $excedido = crearBorradorApi($this, $jefe, [
        'type' => 'ajuste',
        'origin_warehouse_id' => $almacen->id,
        'reason_code' => 'robo',
        'lines' => [['product_id' => $producto->id, 'quantity' => '20.000', 'direction' => 'salida']],
    ]);

    confirmarApi($this, $jefe, $excedido)
        ->assertConflict()
        ->assertJsonPath('error.code', 'STOCK_INSUFICIENTE');

    expect(saldoDe($producto->id, $almacen->id))->toBe('12.000');
});

// La dirección por línea solo existe en el ajuste: fuera de él se rechaza con
// 422 y un valor ajeno también.
test('la direccion por linea solo se admite en el ajuste', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();
    actuarComo($jefe);

    $cuerpo = cuerpoEntrada($almacen, $producto);
    $cuerpo['lines'][0]['direction'] = 'salida';

    $this->postJson('/api/v1/inventory-documents', $cuerpo, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'lines.0.direction');

    $ajuste = [
        'type' => 'ajuste',
        'origin_warehouse_id' => $almacen->id,
        'reason_code' => 'otro',
        'lines' => [['product_id' => $producto->id, 'quantity' => '1.000', 'direction' => 'alza']],
    ];

    $this->postJson('/api/v1/inventory-documents', $ajuste, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable();

    expect(InventoryDocument::query()->count())->toBe(0);
});

// RF-061, AC-026 y AC-041: la transferencia descuenta origen y acredita
// destino en una transacción, con dos movimientos enlazados y el costo
// viajero del promedio vigente en origen.
test('confirmar transferencia mueve atomico con el costo del origen', function (): void {
    $origen = Warehouse::factory()->create();
    $destino = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $origen, $destino);
    $producto = Product::factory()->create();

    confirmarApi($this, $jefe, crearBorradorApi($this, $jefe, cuerpoEntrada($origen, $producto, '10.000', '10.000000')))->assertOk();

    $uuid = crearBorradorApi($this, $jefe, [
        'type' => 'transferencia',
        'origin_warehouse_id' => $origen->id,
        'destination_warehouse_id' => $destino->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '4.000']],
    ]);

    $respuesta = confirmarApi($this, $jefe, $uuid)->assertOk();

    $movimientos = $respuesta->json('data.movements');
    expect($movimientos)->toHaveCount(2);

    $salida = collect($movimientos)->firstWhere('direction', 'salida');
    $entrada = collect($movimientos)->firstWhere('direction', 'entrada');

    expect($salida['warehouse_id'])->toBe($origen->id);
    expect($salida['quantity_delta'])->toBe('-4.000');
    expect($entrada['warehouse_id'])->toBe($destino->id);
    expect($entrada['quantity_delta'])->toBe('4.000');
    expect($entrada['unit_cost'])->toBe('10.000000');

    expect(saldoDe($producto->id, $origen->id))->toBe('6.000');
    expect(saldoDe($producto->id, $destino->id))->toBe('4.000');

    // El destino recalcula su promedio con el costo viajero: (4*10 + 4*20)/8.
    confirmarApi($this, $jefe, crearBorradorApi($this, $jefe, array_merge(
        cuerpoEntrada($destino, $producto, '4.000', '20.000000')
    )))->assertOk();

    $fila = Inventory::query()->where('product_id', $producto->id)->where('warehouse_id', $destino->id)->first();
    expect(bcadd((string) $fila->average_cost, '0', 6))->toBe('15.000000');
});

// RF-069: reintentar la confirmación con la misma clave devuelve el original
// sin duplicar efectos.
test('reintentar la confirmacion con la misma clave no duplica efectos', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    $clave = (string) Str::uuid();
    $uuid = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto), $clave);

    confirmarApi($this, $jefe, $uuid, $clave)->assertOk();
    confirmarApi($this, $jefe, $uuid, $clave)
        ->assertOk()
        ->assertJsonPath('data.uuid', $uuid)
        ->assertJsonPath('data.status', 'confirmado');

    expect(InventoryMovement::query()->count())->toBe(1);
    expect(saldoDe($producto->id, $almacen->id))->toBe('2.000');
});

// AC-029 (versión secuencial): confirmar dos veces con claves distintas
// rechaza la segunda por estado, sin tocar el saldo.
test('segunda confirmacion con otra clave responde 409 ESTADO_INVALIDO', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    $uuid = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto));

    confirmarApi($this, $jefe, $uuid)->assertOk();
    confirmarApi($this, $jefe, $uuid)
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');

    expect(InventoryMovement::query()->count())->toBe(1);
});

// La confirmación exige borrador, clave de idempotencia y existencia.
test('confirmar fuera de borrador sin clave o inexistente se rechaza', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);

    $uuid = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen));

    $this->postJson("/api/v1/inventory-documents/{$uuid}/confirm", [])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'Idempotency-Key');

    $this->postJson("/api/v1/inventory-documents/{$uuid}/cancel")->assertOk();

    confirmarApi($this, $jefe, $uuid)
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');

    actuarComo($jefe);
    $this->postJson('/api/v1/inventory-documents/00000000-0000-0000-0000-000000000000/confirm', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NO_ENCONTRADO');
});

// RF-074: lo que se desactivó tras crear el borrador no se confirma.
test('confirmar con producto o almacen desactivado responde 422', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    $uuid = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto));
    $producto->update(['is_active' => false]);

    confirmarApi($this, $jefe, $uuid)
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'lines.0.product_id');

    expect(InventoryMovement::query()->count())->toBe(0);
});

// AC-018 y RN-INV-09 como criterio de frontera de la Fase 3: el producto con
// historial que se desactiva tras crearse el borrador se rechaza AL CONFIRMAR
// con un error de negocio claro (422 VALIDACION con código, campo y mensaje),
// sin tocar el saldo ni el kardex, y su historial sigue consultable. El test
// anterior solo afirma el campo; aquí ato el mensaje exacto del servicio y la
// segunda mitad del criterio (el kardex del inactivo sigue respondiendo 200).
test('AC-018: confirmar con producto desactivado se rechaza y su kardex sigue consultable', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    // Dejo historial confirmado para que el kardex tenga algo que mostrar y
    // la prueba no pase en vacío sobre un producto sin movimientos.
    confirmarApi($this, $jefe, crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto, '10.000', '10.000000')))->assertOk();
    expect(InventoryMovement::query()->count())->toBe(1);
    expect(saldoDe($producto->id, $almacen->id))->toBe('10.000');

    // Creo la salida con el producto aún activo: si el borrador no naciera en
    // 201, el 422 posterior no probaría nada sobre la confirmación.
    $uuid = crearBorradorApi($this, $jefe, [
        'type' => 'salida',
        'origin_warehouse_id' => $almacen->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '4.000']],
    ]);

    $producto->update(['is_active' => false]);
    expect($producto->fresh()->is_active)->toBeFalse();

    confirmarApi($this, $jefe, $uuid)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.field', 'lines.0.product_id')
        ->assertJsonPath('error.details.0.message', 'El producto no está activo y no admite movimientos nuevos.');

    // Sin efectos: el documento sigue en borrador, no hay movimiento nuevo y
    // el saldo conserva el historial intacto.
    expect(InventoryDocument::query()->where('uuid', $uuid)->first()->status)->toBe('borrador');
    expect(InventoryMovement::query()->count())->toBe(1);
    expect(saldoDe($producto->id, $almacen->id))->toBe('10.000');

    // Segunda mitad de AC-018: el kardex del producto inactivo sigue
    // consultable con su historial completo.
    actuarComo($jefe);
    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}")
        ->assertOk()
        ->assertJsonPath('balances.final_balance', '10.000')
        ->assertJsonCount(1, 'data');
});

// AC-014 y AC-015: la unidad sin decimales rechaza la fracción al confirmar y
// la unidad decimal la acepta.
test('la unidad sin decimales rechaza la fraccion al confirmar', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);

    $entera = Product::factory()->create([
        'unit_of_measure_id' => UnitOfMeasure::factory()->create(['allows_decimals' => false])->id,
    ]);

    $uuid = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $entera, '1.250', '4.000000'));

    confirmarApi($this, $jefe, $uuid)
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'lines.0.quantity');

    expect(InventoryMovement::query()->count())->toBe(0);

    $decimal = Product::factory()->create([
        'unit_of_measure_id' => UnitOfMeasure::factory()->create(['allows_decimals' => true])->id,
    ]);

    $otro = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $decimal, '1.250', '4.000000'));
    confirmarApi($this, $jefe, $otro)->assertOk();

    expect(saldoDe($decimal->id, $almacen->id))->toBe('1.250');
});

// AC-006 y AC-055: el Auditor no confirma ni revierte, y fuera de alcance se
// niega aunque haya permiso.
test('sin permiso o fuera de alcance confirmar y revertir se niegan', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $uuid = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen));

    $auditor = conAlmacenes(usuarioConPermisos([
        PermissionSlug::InventoryView->value,
        PermissionSlug::ReportsView->value,
    ]), $almacen);

    confirmarApi($this, $auditor, $uuid)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    actuarComo($auditor);
    $this->postJson("/api/v1/inventory-documents/{$uuid}/reverse", ['reason' => 'Prueba'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    $ajeno = usuarioConPermisos([
        PermissionSlug::InventoryView->value,
        PermissionSlug::InventoryCreate->value,
        PermissionSlug::InventoryConfirm->value,
        PermissionSlug::InventoryReverse->value,
    ]);

    confirmarApi($this, $ajeno, $uuid)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');

    expect(InventoryDocument::query()->where('uuid', $uuid)->first()->status)->toBe('borrador');
});

// El atajo confirm:true exige también el permiso de confirmación.
test('confirmar al crear sin permiso de confirmacion responde 403', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(usuarioConPermisos([
        PermissionSlug::InventoryView->value,
        PermissionSlug::InventoryCreate->value,
    ]), $almacen);
    actuarComo($jefe);

    $this->postJson(
        '/api/v1/inventory-documents',
        array_merge(cuerpoEntrada($almacen), ['confirm' => true]),
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    expect(InventoryDocument::query()->count())->toBe(0);
});

// RF-075 idempotente: repetir la creación inmediata con la misma clave no
// duplica documentos ni movimientos.
test('repetir la creacion inmediata no duplica efectos', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();
    $clave = (string) Str::uuid();
    $cuerpo = array_merge(cuerpoEntrada($almacen, $producto), ['confirm' => true]);

    actuarComo($jefe);
    $primero = $this->postJson('/api/v1/inventory-documents', $cuerpo, ['Idempotency-Key' => $clave])
        ->assertCreated()
        ->json('data.uuid');

    $this->postJson('/api/v1/inventory-documents', $cuerpo, ['Idempotency-Key' => $clave])
        ->assertOk()
        ->assertJsonPath('data.uuid', $primero);

    expect(InventoryDocument::query()->count())->toBe(1);
    expect(InventoryMovement::query()->count())->toBe(1);
    expect(saldoDe($producto->id, $almacen->id))->toBe('2.000');
});

// AC-034: revertir una entrada compensada deja el saldo en cero, marca el
// original como revertido y enlaza la reversión sin borrar nada.
test('revertir entrada compensada deja saldo cero con trazabilidad', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    $uuid = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto, '10.000', '10.000000'));
    confirmarApi($this, $jefe, $uuid)->assertOk();

    actuarComo($jefe);
    $respuesta = $this->postJson("/api/v1/inventory-documents/{$uuid}/reverse", ['reason' => 'Factura duplicada'])
        ->assertOk()
        ->assertJsonPath('data.status', 'revertido');

    $reversion = $respuesta->json('data.reversal');
    expect($reversion['type'])->toBe('salida');
    expect($reversion['status'])->toBe('confirmado');

    expect(saldoDe($producto->id, $almacen->id))->toBe('0.000');
    expect(InventoryMovement::query()->count())->toBe(2);

    // El original conserva sus movimientos: la corrección es otro documento.
    $original = InventoryDocument::query()->where('uuid', $uuid)->first();
    expect($original->status)->toBe('revertido');
    expect((int) $original->reversed_by_id)->toBeGreaterThan(0);

    $compensacion = InventoryMovement::query()->orderBy('id', 'desc')->first();
    expect($compensacion->quantity_delta)->toBe('-10.000');

    expect(AuditLog::query()->where('action', 'revertir_documento')->count())->toBe(1);
});

// Revertir una salida restaura la existencia con el costo espejado.
test('revertir salida restaura la existencia', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    confirmarApi($this, $jefe, crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto, '10.000', '10.000000')))->assertOk();

    $uuid = crearBorradorApi($this, $jefe, [
        'type' => 'salida',
        'origin_warehouse_id' => $almacen->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '4.000']],
    ]);
    confirmarApi($this, $jefe, $uuid)->assertOk();
    expect(saldoDe($producto->id, $almacen->id))->toBe('6.000');

    actuarComo($jefe);
    $this->postJson("/api/v1/inventory-documents/{$uuid}/reverse", ['reason' => 'Despacho erróneo'])
        ->assertOk()
        ->assertJsonPath('data.status', 'revertido');

    expect(saldoDe($producto->id, $almacen->id))->toBe('10.000');

    $fila = Inventory::query()->where('product_id', $producto->id)->where('warehouse_id', $almacen->id)->first();
    expect(bcadd((string) $fila->average_cost, '0', 6))->toBe('10.000000');
});

// Revertir una transferencia devuelve cada pata a su almacén.
test('revertir transferencia devuelve ambas patas', function (): void {
    $origen = Warehouse::factory()->create();
    $destino = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $origen, $destino);
    $producto = Product::factory()->create();

    confirmarApi($this, $jefe, crearBorradorApi($this, $jefe, cuerpoEntrada($origen, $producto, '10.000', '10.000000')))->assertOk();

    $uuid = crearBorradorApi($this, $jefe, [
        'type' => 'transferencia',
        'origin_warehouse_id' => $origen->id,
        'destination_warehouse_id' => $destino->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '4.000']],
    ]);
    confirmarApi($this, $jefe, $uuid)->assertOk();

    actuarComo($jefe);
    $respuesta = $this->postJson("/api/v1/inventory-documents/{$uuid}/reverse", ['reason' => 'Envío equivocado'])
        ->assertOk();

    expect($respuesta->json('data.reversal.type'))->toBe('transferencia');
    expect(saldoDe($producto->id, $origen->id))->toBe('10.000');
    expect(saldoDe($producto->id, $destino->id))->toBe('0.000');
    // Entrada inicial (1) más transferencia (2) más reversión (2).
    expect(InventoryMovement::query()->count())->toBe(5);
});

// RF-078 y AC-035: si el destino ya consumió parte, la reversión completa se
// rechaza y el original sigue confirmado.
test('revertir transferencia consumida se rechaza sin efectos', function (): void {
    $origen = Warehouse::factory()->create();
    $destino = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $origen, $destino);
    $producto = Product::factory()->create();

    confirmarApi($this, $jefe, crearBorradorApi($this, $jefe, cuerpoEntrada($origen, $producto, '10.000', '10.000000')))->assertOk();

    $uuid = crearBorradorApi($this, $jefe, [
        'type' => 'transferencia',
        'origin_warehouse_id' => $origen->id,
        'destination_warehouse_id' => $destino->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '4.000']],
    ]);
    confirmarApi($this, $jefe, $uuid)->assertOk();

    $consumo = crearBorradorApi($this, $jefe, [
        'type' => 'salida',
        'origin_warehouse_id' => $destino->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '3.000']],
    ]);
    confirmarApi($this, $jefe, $consumo)->assertOk();

    actuarComo($jefe);
    $this->postJson("/api/v1/inventory-documents/{$uuid}/reverse", ['reason' => 'Devolución total'])
        ->assertConflict()
        ->assertJsonPath('error.code', 'STOCK_INSUFICIENTE');

    expect(InventoryDocument::query()->where('uuid', $uuid)->first()->status)->toBe('confirmado');
    expect(saldoDe($producto->id, $origen->id))->toBe('6.000');
    expect(saldoDe($producto->id, $destino->id))->toBe('1.000');
});

// La reversión exige motivo y documento confirmado; repetirla devuelve el par.
test('revertir exige motivo confirmado y es idempotente', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    $borrador = crearBorradorApi($this, $jefe, cuerpoEntrada($almacen, $producto));

    actuarComo($jefe);
    $this->postJson("/api/v1/inventory-documents/{$borrador}/reverse", ['reason' => 'Prueba'])
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');

    $this->postJson("/api/v1/inventory-documents/{$borrador}/reverse", [])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'reason');

    confirmarApi($this, $jefe, $borrador)->assertOk();

    actuarComo($jefe);
    $this->postJson("/api/v1/inventory-documents/{$borrador}/reverse", [])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'reason');

    $primera = $this->postJson("/api/v1/inventory-documents/{$borrador}/reverse", ['reason' => 'Motivo real'])
        ->assertOk()
        ->json('data.reversal.uuid');

    $this->postJson("/api/v1/inventory-documents/{$borrador}/reverse", ['reason' => 'Motivo real'])
        ->assertOk()
        ->assertJsonPath('data.reversal.uuid', $primera);

    expect(InventoryMovement::query()->count())->toBe(2);
});

// I-02: tras confirmar y revertir, la suma del kardex iguala el saldo
// materializado en cada par, sin filas divergentes en ningún sentido.
test('la suma del kardex iguala el saldo materializado', function (): void {
    $origen = Warehouse::factory()->create();
    $destino = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $origen, $destino);
    $producto = Product::factory()->create();

    confirmarApi($this, $jefe, crearBorradorApi($this, $jefe, cuerpoEntrada($origen, $producto, '10.000', '10.000000')))->assertOk();

    $traslado = crearBorradorApi($this, $jefe, [
        'type' => 'transferencia',
        'origin_warehouse_id' => $origen->id,
        'destination_warehouse_id' => $destino->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '4.000']],
    ]);
    confirmarApi($this, $jefe, $traslado)->assertOk();

    actuarComo($jefe);
    $this->postJson("/api/v1/inventory-documents/{$traslado}/reverse", ['reason' => 'Cuadre I-02'])->assertOk();

    $divergentes = DB::select(
        'SELECT i.product_id, i.warehouse_id FROM inventories i '.
        'LEFT JOIN inventory_movements m ON m.product_id = i.product_id AND m.warehouse_id = i.warehouse_id '.
        'GROUP BY i.product_id, i.warehouse_id, i.quantity '.
        'HAVING i.quantity <> COALESCE(SUM(m.quantity_delta), 0)'
    );

    expect($divergentes)->toBe([]);

    $huerfanos = DB::select(
        'SELECT m.product_id, m.warehouse_id FROM inventory_movements m '.
        'LEFT JOIN inventories i ON i.product_id = m.product_id AND i.warehouse_id = m.warehouse_id '.
        'WHERE i.id IS NULL LIMIT 1'
    );

    expect($huerfanos)->toBe([]);
});
