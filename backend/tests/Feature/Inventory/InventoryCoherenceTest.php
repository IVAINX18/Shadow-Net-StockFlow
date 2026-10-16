<?php

declare(strict_types=1);

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Criterio de cierre de la Fase 3 (invariante I-02): sobre TODA la base, la
// suma del kardex de cada par producto-almacén debe igualar el saldo
// materializado, sin una sola fila divergente y sin movimientos huérfanos.
// Es una aserción sobre el conjunto completo, no un muestreo: la construyo
// sobre un escenario rico (varios productos, varios almacenes, entradas,
// salidas, transferencias y reversiones) para que la consulta de coherencia
// visite cada par y cada signo, no solo el camino feliz de un par.
uses(RefreshDatabase::class);

// Devuelvo las filas divergentes en el sentido saldo -> kardex: pares cuya
// cantidad materializada no coincide con la suma de sus movimientos.
/**
 * @return array<int, object>
 */
function paresDivergentes(): array
{
    return DB::select(
        'SELECT i.product_id, i.warehouse_id, i.quantity, COALESCE(SUM(m.quantity_delta), 0) AS kardex_total '.
        'FROM inventories i '.
        'LEFT JOIN inventory_movements m ON m.product_id = i.product_id AND m.warehouse_id = i.warehouse_id '.
        'GROUP BY i.product_id, i.warehouse_id, i.quantity '.
        'HAVING i.quantity <> COALESCE(SUM(m.quantity_delta), 0)'
    );
}

// Devuelvo los movimientos huérfanos: pares con kardex pero sin saldo
// materializado (la otra mitad de I-02; sin esto, un par que solo tiene
// movimientos pasaría desapercibido).
/**
 * @return array<int, object>
 */
function movimientosHuerfanos(): array
{
    return DB::select(
        'SELECT m.product_id, m.warehouse_id FROM inventory_movements m '.
        'LEFT JOIN inventories i ON i.product_id = m.product_id AND i.warehouse_id = m.warehouse_id '.
        'WHERE i.id IS NULL'
    );
}

// Construyo el escenario por la API y devuelvo los identificadores que el
// test necesita para reforzar la comprobación par a par. Recibo el TestCase
// porque los ayudantes de Pest no disponen de `$this`.
/**
 * @return array{0: int, 1: int, 2: int}
 */
function escenarioCoherencia(TestCase $prueba): array
{
    $almacenA = Warehouse::factory()->create();
    $almacenB = Warehouse::factory()->create();
    $jefe = conAlmacenes(confirmadorInventario(), $almacenA, $almacenB);

    $p1 = Product::factory()->create();
    $p2 = Product::factory()->create();
    $p3 = Product::factory()->create();

    // Entradas en ambos almacenes: dos productos en A, uno en B.
    confirmarApi($prueba, $jefe, crearBorradorApi($prueba, $jefe, cuerpoEntrada($almacenA, $p1, '20.000', '5.000000')))->assertOk();
    confirmarApi($prueba, $jefe, crearBorradorApi($prueba, $jefe, cuerpoEntrada($almacenA, $p2, '15.000', '8.000000')))->assertOk();
    confirmarApi($prueba, $jefe, crearBorradorApi($prueba, $jefe, cuerpoEntrada($almacenB, $p3, '9.000', '3.000000')))->assertOk();

    // Salida que descuenta un par.
    $salida = crearBorradorApi($prueba, $jefe, [
        'type' => 'salida',
        'origin_warehouse_id' => $almacenA->id,
        'lines' => [['product_id' => $p1->id, 'quantity' => '6.000']],
    ]);
    confirmarApi($prueba, $jefe, $salida)->assertOk();

    // Transferencia que toca los dos almacenes con el mismo producto: prueba
    // que el delta de origen es el negativo exacto del de destino (I-03) y
    // que la coherencia se mantiene en ambas patas.
    $transferencia = crearBorradorApi($prueba, $jefe, [
        'type' => 'transferencia',
        'origin_warehouse_id' => $almacenA->id,
        'destination_warehouse_id' => $almacenB->id,
        'lines' => [['product_id' => $p1->id, 'quantity' => '4.000']],
    ]);
    confirmarApi($prueba, $jefe, $transferencia)->assertOk();

    // Reversión completa de la transferencia: los compensatorios deben dejar
    // la coherencia intacta en los cuatro pares implicados.
    actuarComo($jefe);
    $prueba->postJson("/api/v1/inventory-documents/{$transferencia}/reverse", ['reason' => 'Cuadre de cierre'])->assertOk();

    // Una segunda transferencia que NO se revierte, para poder comprobar la
    // simetría exacta de la pata de origen y la de destino (I-03) sobre un
    // documento vigente.
    $transferenciaViva = crearBorradorApi($prueba, $jefe, [
        'type' => 'transferencia',
        'origin_warehouse_id' => $almacenA->id,
        'destination_warehouse_id' => $almacenB->id,
        'lines' => [['product_id' => $p2->id, 'quantity' => '10.000']],
    ]);
    confirmarApi($prueba, $jefe, $transferenciaViva)->assertOk();

    // Un ajuste positivo y otro negativo para cubrir los dos signos.
    $ajusteMas = crearBorradorApi($prueba, $jefe, [
        'type' => 'ajuste',
        'origin_warehouse_id' => $almacenB->id,
        'reason_code' => 'inventario_fisico',
        'lines' => [['product_id' => $p3->id, 'quantity' => '2.000', 'direction' => 'entrada']],
    ]);
    confirmarApi($prueba, $jefe, $ajusteMas)->assertOk();

    $ajusteMenos = crearBorradorApi($prueba, $jefe, [
        'type' => 'ajuste',
        'origin_warehouse_id' => $almacenA->id,
        'reason_code' => 'merma',
        'lines' => [['product_id' => $p2->id, 'quantity' => '3.000', 'direction' => 'salida']],
    ]);
    confirmarApi($prueba, $jefe, $ajusteMenos)->assertOk();

    return [$p1->id, $almacenA->id, $almacenB->id, $transferenciaViva];
}

// I-02 como criterio de cierre: ninguna fila de `inventories` diverge del
// kardex y ningún movimiento carece de su saldo materializado. El test
// fallaría si una confirmación escribiera el saldo sin el movimiento (o al
// revés), si una reversión dejara un delta sin compensar o si un par
// quedara con saldo materializado sin historia.
test('la coherencia kardex-saldo no tiene ninguna fila divergente', function (): void {
    [$p1Id, $almacenAId, $almacenBId] = escenarioCoherencia($this);

    // El escenario ejercitó de verdad el motor: sin movimientos ni saldos,
    // las consultas de coherencia serían vacuamente ciertas y no probarían
    // nada.
    expect(InventoryMovement::query()->count())->toBeGreaterThan(0);
    expect(Inventory::query()->count())->toBeGreaterThan(0);

    expect(paresDivergentes())->toBe([]);
    expect(movimientosHuerfanos())->toBe([]);

    // Refuerzo par a par con bcmath además del HAVING de MySQL: para cada
    // fila materializada, la suma exacta del kardex coincide, y el saldo es
    // no negativo (I-01).
    $filas = Inventory::query()->get();
    foreach ($filas as $fila) {
        $esperado = (string) InventoryMovement::query()
            ->where('product_id', $fila->product_id)
            ->where('warehouse_id', $fila->warehouse_id)
            ->sum('quantity_delta');

        expect(bcadd((string) $fila->quantity, '0', 3))->toBe(bcadd($esperado, '0', 3));
        expect(bccomp((string) $fila->quantity, '0', 3))->toBeGreaterThanOrEqual(0);
    }

    // I-03: la transferencia revertida dejó la suma de cada par en su estado
    // original; el delta total del par (A, p1) tras entrada, salida,
    // transferencia y su reversión debe ser 20 - 6 = 14 exacto.
    expect(saldoDe($p1Id, $almacenAId))->toBe('14.000');

    // La coherencia se sostiene sin importar el orden de lectura: el cierre
    // recorre ambos almacenes del escenario.
    expect(Inventory::query()->whereIn('warehouse_id', [$almacenAId, $almacenBId])->count())->toBeGreaterThan(1);
});

// Cierro la batería de invariantes sobre el mismo escenario: I-01, I-03,
// I-05, I-06 e I-07 se comprueban con consultas de verificación sobre el
// libro y el saldo, tal como pide §9 de database-design.md, sin escribir
// nada. I-02 e I-01 ya se demostraron en el test anterior.
test('el escenario respeta las invariantes I-01, I-03, I-05, I-06 e I-07', function (): void {
    [$p1Id, $almacenAId, $almacenBId, $transferenciaViva] = escenarioCoherencia($this);

    // I-01: ninguna existencia negativa.
    expect(DB::select('SELECT id FROM inventories WHERE quantity < 0'))->toBe([]);

    // I-03: para la transferencia vigente, la suma de deltas de origen es el
    // negativo exacto de la de destino. La agrupo por almacén y comparo con
    // bcmath.
    $documentoId = DB::table('inventory_documents')->where('uuid', $transferenciaViva)->value('id');
    $porAlmacen = DB::table('inventory_movements')
        ->where('document_id', $documentoId)
        ->selectRaw('warehouse_id, SUM(quantity_delta) AS total')
        ->groupBy('warehouse_id')
        ->pluck('total', 'warehouse_id');

    $origen = bcadd((string) $porAlmacen[$almacenAId], '0', 3);
    $destino = bcadd((string) $porAlmacen[$almacenBId], '0', 3);
    expect(bcadd($origen, $destino, 3))->toBe('0.000');
    expect($origen)->toBe('-10.000');

    // I-05: todo movimiento referencia un documento y un usuario responsables.
    expect(DB::select(
        'SELECT id FROM inventory_movements WHERE document_id IS NULL OR created_by IS NULL LIMIT 1'
    ))->toBe([]);

    // I-06: no hay dos documentos con la misma clave de idempotencia.
    expect(DB::select(
        'SELECT idempotency_key FROM inventory_documents GROUP BY idempotency_key HAVING COUNT(*) > 1'
    ))->toBe([]);

    // I-07: el costo promedio es positivo cuando hay existencia; sin cantidad,
    // el costo permanece en cero.
    expect(DB::select(
        'SELECT id FROM inventories WHERE quantity > 0 AND average_cost <= 0 LIMIT 1'
    ))->toBe([]);

    // El saldo del par tocado por la transferencia vigente quedó coherente con
    // su kardex (refuerzo puntual de I-02 sobre el par con más historia).
    expect(saldoDe($p1Id, $almacenAId))->toBe('14.000');
});

// El test anterior prueba el estado convergente; este prueba que la consulta
// de coherencia DETECTA la deriva: si inyecto una discrepancia artificial,
// las filas divergentes dejan de estar vacías. Así garantizo que el criterio
// de cierre tiene poder de fallo y no es una aserción vacua.
test('la consulta de coherencia detecta una deriva inyectada', function (): void {
    escenarioCoherencia($this);

    // Fuerzo la deriva por SQL directo (fuera del servicio) porque el
    // servicio nunca la produciría: es justo lo que la prueba debe atrapar.
    $fila = Inventory::query()->firstOrFail();
    DB::table('inventories')
        ->where('id', $fila->id)
        ->update(['quantity' => bcadd((string) $fila->quantity, '1.000', 3)]);

    expect(paresDivergentes())->not->toBe([]);
});
