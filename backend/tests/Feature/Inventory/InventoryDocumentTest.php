<?php

declare(strict_types=1);

use App\Domain\Enums\PermissionSlug;
use App\Models\AuditLog;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

// Pruebo el borrador contra MySQL real porque la UNIQUE de idempotency_key,
// los CHECK de tipo y cantidad y las FK con RESTRICT viven en el motor
// definitivo, y la idempotencia solo es creíble contra restricciones reales.
uses(RefreshDatabase::class);

if (! function_exists('jefeInventario')) {
    // Creo un usuario con lectura y creación de inventario, porque el
    // borrador exige ambos permisos y el alcance se lo doy por almacén.
    function jefeInventario(): User
    {
        return usuarioConPermisos([
            PermissionSlug::InventoryView->value,
            PermissionSlug::InventoryCreate->value,
        ]);
    }
}

if (! function_exists('jefeInventarioConAlmacen')) {
    // Creo el usuario ya atado a un almacén, porque las pruebas de
    // FUERA_DE_ALCANCE necesitan el permiso concedido pero un almacén ajeno
    // fuera de su warehouse_user.
    function jefeInventarioConAlmacen(Warehouse $almacen): User
    {
        $jefe = jefeInventario();
        $jefe->warehouses()->attach($almacen->id);

        return $jefe->fresh();
    }
}

if (! function_exists('borradorBase')) {
    // Armo el cuerpo válido de creación porque casi todas las pruebas parten
    // del mismo alta en borrador y solo varían un campo para cada 422.
    function borradorBase(Warehouse $origen, ?Product $producto = null): array
    {
        return [
            'type' => 'entrada',
            'origin_warehouse_id' => $origen->id,
            'reference' => 'FAC-001',
            'notes' => 'Carga de prueba',
            'lines' => [
                [
                    'product_id' => ($producto ?? Product::factory()->create())->id,
                    'quantity' => '2.500',
                    'unit_cost' => '10.250000',
                ],
            ],
        ];
    }
}

if (! function_exists('crearBorrador')) {
    // Creo el borrador por la API con clave única, porque es el camino que
    // ejerce la idempotencia, el alcance y la auditoría de una sola vez.
    // Recibo el caso de prueba porque fuera del cierre no hay `$this` con el
    // que lanzar peticiones HTTP (en tiempo de ejecución es el TestCase).
    /**
     * @param  TestCase  $prueba
     */
    function crearBorrador($prueba, User $autor, array $cuerpo, ?string $clave = null): string
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
}

// RF-052: crear un borrador responde 201 con uuid, número legible y líneas
// con decimales como cadena y total calculado en el servidor.
test('crear borrador responde 201 con numero legible y decimales como cadena', function (): void {
    $almacen = Warehouse::factory()->create();
    actuarComo(jefeInventarioConAlmacen($almacen));

    $respuesta = $this->postJson(
        '/api/v1/inventory-documents',
        borradorBase($almacen),
        ['Idempotency-Key' => (string) Str::uuid()]
    );

    $respuesta->assertCreated()
        ->assertJsonPath('data.type', 'entrada')
        ->assertJsonPath('data.status', 'borrador')
        ->assertJsonPath('data.lines.0.quantity', '2.500')
        ->assertJsonPath('data.lines.0.unit_cost', '10.250000')
        ->assertJsonPath('data.lines.0.line_total', '25.63');

    $cuerpo = $respuesta->json('data');
    expect($cuerpo['uuid'])->toBeString();
    expect($cuerpo['doc_number'])->toStartWith('ENT-');
    expect($cuerpo['lines'][0]['quantity'])->toBeString();
    expect($cuerpo['lines'][0]['unit_cost'])->toBeString();
    expect($cuerpo['lines'][0]['line_total'])->toBeString();

    // El alta deja su entrada de auditoría con responsable y entidad.
    expect(AuditLog::query()->where('action', 'crear_documento')->count())->toBe(1);
});

// RF-066: el detalle muestra cabecera, líneas, estado y referencia externa.
test('leer borrador por uuid responde 200 con lineas y referencia', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = jefeInventarioConAlmacen($almacen);
    actuarComo($jefe);

    $uuid = crearBorrador($this, $jefe, borradorBase($almacen));

    $this->getJson("/api/v1/inventory-documents/{$uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $uuid)
        ->assertJsonPath('data.status', 'borrador')
        ->assertJsonPath('data.reference', 'FAC-001')
        ->assertJsonCount(1, 'data.lines');
});

// RF-053: el PUT repone el borrador (cabecera y líneas) sin crear otro.
test('actualizar borrador repone lineas y responde 200', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = jefeInventarioConAlmacen($almacen);
    actuarComo($jefe);

    $uuid = crearBorrador($this, $jefe, borradorBase($almacen));
    $otro = Product::factory()->create();

    $this->putJson("/api/v1/inventory-documents/{$uuid}", [
        'type' => 'entrada',
        'origin_warehouse_id' => $almacen->id,
        'reference' => 'FAC-002',
        'notes' => 'Editado',
        'lines' => [
            ['product_id' => $otro->id, 'quantity' => '1.000', 'unit_cost' => '5.000000'],
        ],
    ])->assertOk()
        ->assertJsonPath('data.reference', 'FAC-002')
        ->assertJsonPath('data.lines.0.quantity', '1.000')
        ->assertJsonCount(1, 'data.lines');

    expect(InventoryDocument::query()->count())->toBe(1);
});

// RF-054 y máquina de estados: descartar cierra el borrador y editar o
// descartar de nuevo se rechaza con 409 ESTADO_INVALIDO.
test('descartar cierra el borrador y bloquea ediciones posteriores', function (): void {
    $almacen = Warehouse::factory()->create();
    $jefe = jefeInventarioConAlmacen($almacen);
    actuarComo($jefe);

    $uuid = crearBorrador($this, $jefe, borradorBase($almacen));

    $this->postJson("/api/v1/inventory-documents/{$uuid}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'descartado');

    $this->putJson("/api/v1/inventory-documents/{$uuid}", borradorBase($almacen))
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');

    $this->postJson("/api/v1/inventory-documents/{$uuid}/cancel")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');
});

// RF-065: el listado pagina, filtra por tipo y solo muestra mi alcance.
test('listar borradores pagina filtra y respeta el alcance', function (): void {
    $mio = Warehouse::factory()->create();
    $ajeno = Warehouse::factory()->create();
    $jefe = jefeInventarioConAlmacen($mio);
    actuarComo($jefe);

    crearBorrador($this, $jefe, borradorBase($mio));
    crearBorrador($this, $jefe, array_merge(borradorBase($mio), ['type' => 'salida']));

    // Este documento vive fuera de mi alcance: lo crea otro usuario atado al
    // almacén ajeno, así que mi listado no debe verlo.
    $vecino = jefeInventarioConAlmacen($ajeno);
    crearBorrador($this, $vecino, borradorBase($ajeno));
    actuarComo($jefe);

    $this->getJson('/api/v1/inventory-documents?per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/inventory-documents?type=salida')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.type', 'salida');
});

// RF-070: repetir la misma clave con el mismo cuerpo no crea un segundo
// documento y devuelve el original con 200.
test('reintento identico devuelve el original sin duplicar', function (): void {
    $almacen = Warehouse::factory()->create();
    actuarComo(jefeInventarioConAlmacen($almacen));

    $cuerpo = borradorBase($almacen);
    $clave = (string) Str::uuid();

    $primero = $this->postJson('/api/v1/inventory-documents', $cuerpo, ['Idempotency-Key' => $clave])
        ->assertCreated()
        ->json('data.uuid');

    $this->postJson('/api/v1/inventory-documents', $cuerpo, ['Idempotency-Key' => $clave])
        ->assertOk()
        ->assertJsonPath('data.uuid', $primero);

    expect(InventoryDocument::query()->count())->toBe(1);
});

// C-05: la misma clave con cuerpo distinto no crea nada y responde 409
// IDEMPOTENCIA_REPETIDA.
test('clave repetida con cuerpo distinto responde 409 IDEMPOTENCIA_REPETIDA', function (): void {
    $almacen = Warehouse::factory()->create();
    actuarComo(jefeInventarioConAlmacen($almacen));

    $clave = (string) Str::uuid();
    $this->postJson('/api/v1/inventory-documents', borradorBase($almacen), ['Idempotency-Key' => $clave])
        ->assertCreated();

    $distinto = borradorBase($almacen);
    $distinto['reference'] = 'FAC-OTRA';

    $this->postJson('/api/v1/inventory-documents', $distinto, ['Idempotency-Key' => $clave])
        ->assertConflict()
        ->assertJsonPath('error.code', 'IDEMPOTENCIA_REPETIDA');

    expect(InventoryDocument::query()->count())->toBe(1);
});

// C-05: sin clave no hay garantía de no-duplicado, así que se exige con 422.
test('crear sin Idempotency-Key responde 422 VALIDACION', function (): void {
    $almacen = Warehouse::factory()->create();
    actuarComo(jefeInventarioConAlmacen($almacen));

    $this->postJson('/api/v1/inventory-documents', borradorBase($almacen))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.field', 'Idempotency-Key');

    expect(InventoryDocument::query()->count())->toBe(0);
});

// AC-005: sin el permiso, la petición directa rebota con 403 SIN_PERMISO
// aunque la interfaz oculte la pantalla.
test('sin permiso el borrador responde 403 SIN_PERMISO', function (): void {
    $almacen = Warehouse::factory()->create();
    actuarComo(usuarioConPermisos([PermissionSlug::UsersView->value]));

    $this->postJson('/api/v1/inventory-documents', borradorBase($almacen), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');

    $this->getJson('/api/v1/inventory-documents')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');
});

// AC-055: con permiso pero fuera de alcance, el servidor niega con 403
// FUERA_DE_ALCANCE tanto al crear como al leer.
test('fuera de alcance el borrador responde 403 FUERA_DE_ALCANCE', function (): void {
    $mio = Warehouse::factory()->create();
    $ajeno = Warehouse::factory()->create();
    $jefe = jefeInventarioConAlmacen($mio);
    actuarComo($jefe);

    $this->postJson('/api/v1/inventory-documents', borradorBase($ajeno), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');

    $uuidAjeno = crearBorrador($this, jefeInventarioConAlmacen($ajeno), borradorBase($ajeno));
    actuarComo($jefe);

    $this->getJson("/api/v1/inventory-documents/{$uuidAjeno}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');
});

// RN-INV-08, RN-INV-03 y RN-INV-01: el fondo del borrador se valida en el
// servicio con 422 por campo (ajuste sin motivo, transferencia al mismo
// almacén, destino fuera de transferencia, cantidad no positiva y producto
// inactivo).
test('el fondo del borrador se valida con 422 por campo', function (): void {
    $origen = Warehouse::factory()->create();
    $destino = Warehouse::factory()->create();
    // El usuario alcanza ambos almacenes para que el alcance no se adelante a
    // la validación de fondo: aquí quiero ejercer el 422, no el 403.
    $jefe = jefeInventario();
    $jefe->warehouses()->attach([$origen->id, $destino->id]);
    actuarComo($jefe->fresh());

    $this->postJson('/api/v1/inventory-documents', array_merge(
        borradorBase($origen), ['type' => 'ajuste']
    ), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'reason_code');

    $this->postJson('/api/v1/inventory-documents', array_merge(
        borradorBase($origen), ['type' => 'transferencia', 'destination_warehouse_id' => $origen->id]
    ), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'destination_warehouse_id');

    $this->postJson('/api/v1/inventory-documents', array_merge(
        borradorBase($origen), ['destination_warehouse_id' => $destino->id]
    ), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'destination_warehouse_id');

    $cero = borradorBase($origen);
    $cero['lines'][0]['quantity'] = '0.000';

    $this->postJson('/api/v1/inventory-documents', $cero, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable();

    $inactivo = Product::factory()->create(['is_active' => false]);
    $muerto = borradorBase($origen, $inactivo);

    $this->postJson('/api/v1/inventory-documents', $muerto, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.0.field', 'lines.0.product_id');

    expect(InventoryDocument::query()->count())->toBe(0);
});

// RF-075: la confirmación inmediata crea y confirma en un solo paso por el
// mismo servicio transaccional, con movimientos generados.
test('confirmar al crear responde 201 con documento confirmado', function (): void {
    $almacen = Warehouse::factory()->create();
    // La confirmación inmediata aplica efectos, así que exige también el
    // permiso de confirmación además del de creación.
    $jefe = usuarioConPermisos([
        PermissionSlug::InventoryView->value,
        PermissionSlug::InventoryCreate->value,
        PermissionSlug::InventoryConfirm->value,
    ]);
    $jefe->warehouses()->attach($almacen->id);
    actuarComo($jefe->fresh());

    // Uso cantidad entera porque la unidad de la factoría no admite decimales
    // y la confirmación lo verifica (AC-014); el 2.500 de borradorBase es
    // fraccionario y solo vale para el borrador sin confirmar.
    $producto = Product::factory()->create();

    $this->postJson(
        '/api/v1/inventory-documents',
        array_merge(borradorBase($almacen), [
            'confirm' => true,
            'lines' => [['product_id' => $producto->id, 'quantity' => '2.000', 'unit_cost' => '10.250000']],
        ]),
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertCreated()
        ->assertJsonPath('data.status', 'confirmado')
        ->assertJsonCount(1, 'data.movements')
        ->assertJsonPath('data.movements.0.direction', 'entrada')
        ->assertJsonPath('data.movements.0.balance_after', '2.000');

    expect(InventoryDocument::query()->count())->toBe(1);
});
