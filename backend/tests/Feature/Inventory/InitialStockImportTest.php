<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\InventoryDocument;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

// Pruebo la carga inicial contra MySQL real porque la atomicidad
// multi-almacén, los bloqueos del motor de confirmación, la UNIQUE de
// idempotencia y la coherencia kardex-saldo viven en el motor definitivo;
// SQLite no reproduciría el comportamiento transaccional.
uses(RefreshDatabase::class);

if (! function_exists('csvCargaInicial')) {
    // Armo el CSV con los encabezados exactos del contrato porque el lector
    // exige las columnas en ese orden y con esos nombres.
    function csvCargaInicial(array $filas): string
    {
        $lineas = ['sku,almacen,cantidad,costo'];

        foreach ($filas as $fila) {
            $celdas = [];

            foreach (['sku', 'almacen', 'cantidad', 'costo'] as $columna) {
                $valor = $fila[$columna] ?? '';
                $valor = str_replace('"', '""', (string) $valor);
                $celdas[] = str_contains($valor, ',') || str_contains($valor, '"') ? '"'.$valor.'"' : $valor;
            }

            $lineas[] = implode(',', $celdas);
        }

        return implode("\n", $lineas)."\n";
    }
}

if (! function_exists('archivoCarga')) {
    function archivoCarga(string $contenido, string $nombre = 'saldo-inicial.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }
}

if (! function_exists('filaCarga')) {
    // Armo una fila válida porque casi todas las pruebas parten del mismo
    // alta y solo varían un campo para provocar cada error.
    function filaCarga(string $sku, string $almacen, string $cantidad = '10.000', string $costo = '5.250000'): array
    {
        return ['sku' => $sku, 'almacen' => $almacen, 'cantidad' => $cantidad, 'costo' => $costo];
    }
}

if (! function_exists('escenarioCarga')) {
    // Monto el escenario mínimo: dos almacenes, un producto entero (UND, sin
    // decimales) y uno fraccionario (KG, con decimales), con un jefe con
    // alcance en ambos. Reutilizo `unidadBase` (UND) del módulo de catálogo.
    /**
     * @return array{0: User, 1: Warehouse, 2: Warehouse, 3: Product, 4: Product}
     */
    function escenarioCarga(): array
    {
        $almacenA = Warehouse::factory()->create(['code' => 'WH-A']);
        $almacenB = Warehouse::factory()->create(['code' => 'WH-B']);

        $jefe = conAlmacenes(confirmadorInventario(), $almacenA, $almacenB);

        $und = unidadBase();
        $kg = UnitOfMeasure::factory()->create(['code' => 'KG', 'name' => 'Kilogramo', 'allows_decimals' => true]);

        $entero = Product::factory()->create(['sku' => 'ENT-001', 'unit_of_measure_id' => $und->id]);
        $fraccionario = Product::factory()->create(['sku' => 'FRA-001', 'unit_of_measure_id' => $kg->id]);

        return [$jefe, $almacenA, $almacenB, $entero, $fraccionario];
    }
}

if (! function_exists('sumaKardexCarga')) {
    // Sumo el kardex de un par como cadena normalizada para comparar con
    // bcmath, sin flotantes en ningún punto.
    function sumaKardexCarga(int $productoId, int $almacenId): string
    {
        $total = (string) InventoryMovement::query()
            ->where('product_id', $productoId)
            ->where('warehouse_id', $almacenId)
            ->sum('quantity_delta');

        return bcadd($total, '0', 3);
    }
}

// AC-067: la carga válida aplica como entradas confirmadas, las existencias
// igualan la suma del kardex y el promedio ponderado se recalcula.
test('AC-067: la carga inicial válida aplica como entradas y el kardex cuadra', function (): void {
    [$jefe, $almacenA, $almacenB, $entero, $fraccionario] = escenarioCarga();
    actuarComo($jefe);

    $contenido = csvCargaInicial([
        filaCarga('ENT-001', 'WH-A', '10.000', '5.250000'),
        filaCarga('FRA-001', 'WH-A', '2.500', '1.000000'),
        filaCarga('ENT-001', 'WH-B', '4.000', '6.000000'),
    ]);

    $resumen = $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga($contenido)],
        ['Idempotency-Key' => 'clave-carga-001']
    )->assertCreated()->json('data');

    expect($resumen['filas_totales'])->toBe(3)
        ->and($resumen['documentos'])->toHaveCount(2)
        ->and($resumen['repetida'])->toBeFalse()
        ->and($resumen['auditoria_id'])->toBeInt();

    // Un documento de apertura por almacén: la entrada admite un solo origen
    // y cada uno quedó confirmado por el motor transaccional.
    $documentos = InventoryDocument::query()->where('type', 'entrada')->get();
    expect($documentos)->toHaveCount(2);
    foreach ($documentos as $documento) {
        expect($documento->status)->toBe('confirmado');
    }

    // Las existencias igualan lo cargado y la suma exacta del kardex.
    expect(saldoDe($entero->id, $almacenA->id))->toBe('10.000');
    expect(saldoDe($entero->id, $almacenB->id))->toBe('4.000');
    expect(saldoDe($fraccionario->id, $almacenA->id))->toBe('2.500');

    expect(sumaKardexCarga($entero->id, $almacenA->id))->toBe('10.000');
    expect(sumaKardexCarga($entero->id, $almacenB->id))->toBe('4.000');
    expect(sumaKardexCarga($fraccionario->id, $almacenA->id))->toBe('2.500');

    // El promedio ponderado de la apertura es el costo cargado: sobre saldo
    // cero, la fórmula del §8.1 lo deja intacto.
    $fila = Inventory::query()
        ->where('product_id', $entero->id)
        ->where('warehouse_id', $almacenA->id)
        ->firstOrFail();
    expect(bcadd((string) $fila->average_cost, '0', 6))->toBe('5.250000');

    // Los movimientos nacieron con dirección de entrada y saldo resultante.
    expect(InventoryMovement::query()->where('direction', 'entrada')->count())->toBe(3);

    // La bitácora deja una entrada con autor, archivo y conteos (AC-065 vale
    // para la carga por RF-131).
    $entrada = AuditLog::query()->where('action', 'cargar_saldo_inicial')->firstOrFail();
    expect($entrada->user_id)->toBe($jefe->id)
        ->and($entrada->entity_type)->toBe('importacion_saldo_inicial')
        ->and($entrada->entity_id)->toBeNull()
        ->and($entrada->new_values['archivo'] ?? null)->toBe('saldo-inicial.csv')
        ->and($entrada->new_values['filas_totales'] ?? null)->toBe(3);
});

// AC-068: la fila con producto inexistente se rechaza con fila, campo y
// motivo exactos, y no produce ningún efecto.
test('AC-068: producto inexistente se rechaza con fila, campo y motivo', function (): void {
    [$jefe] = escenarioCarga();
    actuarComo($jefe);

    $contenido = csvCargaInicial([
        filaCarga('ENT-001', 'WH-A'),
        ['sku' => 'NOEXISTE-001', 'almacen' => 'WH-A', 'cantidad' => '3.000', 'costo' => '2.000000'],
    ]);

    $reporte = $this->post('/api/v1/imports/initial-stock/validate', [
        'archivo' => archivoCarga($contenido),
    ])->assertOk()->json('data');

    expect($reporte['valida'])->toBeFalse()
        ->and($reporte['filas_invalidas'])->toBe(1);

    $mala = collect($reporte['filas'])->firstWhere('valida', false);
    expect($mala['fila'])->toBe(3)
        ->and($mala['errores'][0]['campo'])->toBe('sku')
        ->and($mala['errores'][0]['motivo'])->toBe('El producto no existe.');

    $respuesta = $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga($contenido)],
        ['Idempotency-Key' => 'clave-carga-002']
    );

    $respuesta->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    $detalles = $respuesta->json('error.details');
    expect($detalles)->toHaveCount(1);
    expect($detalles[0]['fila'])->toBe(3);
    expect($detalles[0]['campo'])->toBe('sku');
    expect($detalles[0]['motivo'])->toBe('El producto no existe.');

    expect(InventoryDocument::count())->toBe(0);
    expect(InventoryMovement::count())->toBe(0);
    expect(Inventory::count())->toBe(0);
});

// AC-068: el almacén fuera del alcance se rechaza por fila, sin 403 global y
// sin efectos.
test('AC-068: almacén fuera de alcance se rechaza por fila', function (): void {
    [$jefe, $almacenA] = escenarioCarga();

    // Le retiro el segundo almacén: solo WH-A queda en su alcance.
    $jefe->warehouses()->detach();
    $jefe = conAlmacenes($jefe->fresh(), $almacenA);
    actuarComo($jefe);

    $contenido = csvCargaInicial([filaCarga('ENT-001', 'WH-B')]);

    $reporte = $this->post('/api/v1/imports/initial-stock/validate', [
        'archivo' => archivoCarga($contenido),
    ])->assertOk()->json('data');

    expect($reporte['valida'])->toBeFalse();
    expect($reporte['filas'][0]['errores'][0]['campo'])->toBe('almacen');
    expect($reporte['filas'][0]['errores'][0]['motivo'])->toBe('El almacén está fuera de su alcance.');

    $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga($contenido)],
        ['Idempotency-Key' => 'clave-carga-003']
    )->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    expect(InventoryDocument::count())->toBe(0);
    expect(Inventory::count())->toBe(0);
});

// RF-134: la cantidad fraccionaria en una unidad que no admite decimales se
// rechaza, y la misma cantidad en una unidad que sí los admite se acepta.
test('cantidad fraccionaria se rechaza sin decimales y se acepta con ellos', function (): void {
    [$jefe] = escenarioCarga();
    actuarComo($jefe);

    $mala = csvCargaInicial([filaCarga('ENT-001', 'WH-A', '2.500', '3.000000')]);

    $reporte = $this->post('/api/v1/imports/initial-stock/validate', [
        'archivo' => archivoCarga($mala),
    ])->assertOk()->json('data');

    expect($reporte['valida'])->toBeFalse();
    expect($reporte['filas'][0]['errores'][0]['campo'])->toBe('cantidad');
    expect($reporte['filas'][0]['errores'][0]['motivo'])->toBe('La unidad de medida no admite decimales.');

    $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga($mala)],
        ['Idempotency-Key' => 'clave-carga-004']
    )->assertUnprocessable();

    expect(InventoryDocument::count())->toBe(0);

    // La misma cantidad fraccionaria con la unidad KG sí pasa y se aplica.
    $buena = csvCargaInicial([filaCarga('FRA-001', 'WH-A', '2.500', '3.000000')]);

    $this->post('/api/v1/imports/initial-stock/validate', [
        'archivo' => archivoCarga($buena),
    ])->assertOk()->assertJsonPath('data.valida', true);

    $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga($buena)],
        ['Idempotency-Key' => 'clave-carga-005']
    )->assertCreated();

    expect(saldoDe(Product::query()->where('sku', 'FRA-001')->firstOrFail()->id, Warehouse::query()->where('code', 'WH-A')->firstOrFail()->id))->toBe('2.500');
});

// La cantidad cero y el costo ausente se rechazan con su motivo exacto.
test('cantidad cero y costo ausente se rechazan con motivo exacto', function (): void {
    [$jefe] = escenarioCarga();
    actuarComo($jefe);

    $contenido = csvCargaInicial([
        ['sku' => 'ENT-001', 'almacen' => 'WH-A', 'cantidad' => '0.000', 'costo' => '3.000000'],
        ['sku' => 'ENT-001', 'almacen' => 'WH-B', 'cantidad' => '1.000', 'costo' => ''],
    ]);

    $respuesta = $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga($contenido)],
        ['Idempotency-Key' => 'clave-carga-006']
    );

    $respuesta->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    $detalles = collect($respuesta->json('error.details'));
    expect($detalles->firstWhere('fila', 2)['motivo'])->toBe('La cantidad debe ser mayor que cero.');
    expect($detalles->firstWhere('fila', 3)['motivo'])->toBe('El costo es obligatorio.');

    expect(InventoryDocument::count())->toBe(0);
});

// El dry-run no escribe nada: los conteos antes y después son idénticos.
test('el dry-run de la carga no escribe nada', function (): void {
    [$jefe] = escenarioCarga();
    actuarComo($jefe);

    $contenido = csvCargaInicial([
        filaCarga('ENT-001', 'WH-A'),
        filaCarga('ENT-001', 'WH-B'),
    ]);

    $antes = [
        InventoryDocument::count(),
        InventoryMovement::count(),
        Inventory::count(),
        AuditLog::count(),
    ];

    $this->post('/api/v1/imports/initial-stock/validate', [
        'archivo' => archivoCarga($contenido),
    ])->assertOk()->assertJsonPath('data.valida', true);

    expect([
        InventoryDocument::count(),
        InventoryMovement::count(),
        Inventory::count(),
        AuditLog::count(),
    ])->toBe($antes);
});

// AC-069: reenviar el mismo archivo con la misma clave no duplica
// existencias ni movimientos.
test('AC-069: reaplicar con la misma clave no duplica existencias', function (): void {
    [$jefe, $almacenA, $almacenB, $entero] = escenarioCarga();
    actuarComo($jefe);

    $contenido = csvCargaInicial([
        filaCarga('ENT-001', 'WH-A', '10.000', '5.250000'),
        filaCarga('ENT-001', 'WH-B', '4.000', '6.000000'),
    ]);

    $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga($contenido)],
        ['Idempotency-Key' => 'clave-carga-007']
    )->assertCreated();

    $documentos = InventoryDocument::count();
    $movimientos = InventoryMovement::count();

    $reaplicacion = $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga($contenido)],
        ['Idempotency-Key' => 'clave-carga-007']
    )->assertOk()->json('data');

    expect($reaplicacion['repetida'])->toBeTrue();
    expect(InventoryDocument::count())->toBe($documentos);
    expect(InventoryMovement::count())->toBe($movimientos);
    expect(saldoDe($entero->id, $almacenA->id))->toBe('10.000');
    expect(saldoDe($entero->id, $almacenB->id))->toBe('4.000');
});

// AC-070: sin el permiso la importación se rechaza con 403 SIN_PERMISO en
// los tres endpoints.
test('AC-070: sin permiso la carga inicial se rechaza con 403 SIN_PERMISO', function (): void {
    escenarioCarga();
    actuarComo(usuarioConPermisos([PermissionSlug::InventoryView->value]));

    $contenido = csvCargaInicial([filaCarga('ENT-001', 'WH-A')]);

    $this->get('/api/v1/imports/initial-stock/template')
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');

    $this->post('/api/v1/imports/initial-stock/validate', [
        'archivo' => archivoCarga($contenido),
    ])->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');

    $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga($contenido)],
        ['Idempotency-Key' => 'clave-carga-008']
    )->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');

    expect(InventoryDocument::count())->toBe(0);
});

// Sin cabecera de idempotencia la aplicación se rechaza con 422.
test('aplicar sin Idempotency-Key se rechaza con 422', function (): void {
    [$jefe] = escenarioCarga();
    actuarComo($jefe);

    $this->post('/api/v1/imports/initial-stock', [
        'archivo' => archivoCarga(csvCargaInicial([filaCarga('ENT-001', 'WH-A')])),
    ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    expect(InventoryDocument::count())->toBe(0);
});

// La plantilla trae las columnas del contrato y un formato inválido da 422.
test('la plantilla trae las columnas sku, almacen, cantidad y costo', function (): void {
    [$jefe] = escenarioCarga();
    actuarComo($jefe);

    $respuesta = $this->get('/api/v1/imports/initial-stock/template?format=csv')->assertOk();
    // El escritor CSV entrecomilla los campos, así que comparo sin comillas.
    $plano = str_replace('"', '', $respuesta->streamedContent());
    expect($plano)->toContain('sku,almacen,cantidad,costo');

    $this->get('/api/v1/imports/initial-stock/template?format=txt')
        ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');
});

// Criterio de cierre de la fase (I-02 sobre toda la base): tras una carga
// inicial seguida de operaciones, ninguna fila materializada diverge de su
// kardex y ningún movimiento queda huérfano.
test('la coherencia kardex-saldo sigue intacta tras la carga inicial', function (): void {
    [$jefe, $almacenA, $almacenB, $entero, $fraccionario] = escenarioCarga();
    actuarComo($jefe);

    $this->post(
        '/api/v1/imports/initial-stock',
        ['archivo' => archivoCarga(csvCargaInicial([
            filaCarga('ENT-001', 'WH-A', '20.000', '5.000000'),
            filaCarga('FRA-001', 'WH-B', '9.000', '3.000000'),
        ]))],
        ['Idempotency-Key' => 'clave-carga-009']
    )->assertCreated();

    // Opero sobre el saldo cargado para que la coherencia se verifique con
    // historia posterior a la apertura, no solo con la apertura misma.
    $salida = crearBorradorApi($this, $jefe, [
        'type' => 'salida',
        'origin_warehouse_id' => $almacenA->id,
        'lines' => [['product_id' => $entero->id, 'quantity' => '6.000']],
    ]);
    confirmarApi($this, $jefe, $salida)->assertOk();

    expect(InventoryMovement::query()->count())->toBeGreaterThan(0);
    expect(Inventory::query()->count())->toBeGreaterThan(0);

    $divergentes = DB::select(
        'SELECT i.product_id, i.warehouse_id, i.quantity, COALESCE(SUM(m.quantity_delta), 0) AS kardex_total '.
        'FROM inventories i '.
        'LEFT JOIN inventory_movements m ON m.product_id = i.product_id AND m.warehouse_id = i.warehouse_id '.
        'GROUP BY i.product_id, i.warehouse_id, i.quantity '.
        'HAVING i.quantity <> COALESCE(SUM(m.quantity_delta), 0)'
    );
    expect($divergentes)->toBe([]);

    $huerfanos = DB::select(
        'SELECT m.product_id, m.warehouse_id FROM inventory_movements m '.
        'LEFT JOIN inventories i ON i.product_id = m.product_id AND i.warehouse_id = m.warehouse_id '.
        'WHERE i.id IS NULL'
    );
    expect($huerfanos)->toBe([]);

    expect(saldoDe($entero->id, $almacenA->id))->toBe('14.000');
    expect(sumaKardexCarga($entero->id, $almacenA->id))->toBe('14.000');
    expect(saldoDe($fraccionario->id, $almacenB->id))->toBe('9.000');
});
