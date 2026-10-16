<?php

declare(strict_types=1);

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Pruebo el kardex contra MySQL real porque el orden (created_at, id) y los
// saldos dependen del motor definitivo, igual que las confirmaciones que lo
// alimentan. Construyo el historial por la API para ejercer el libro tal
// como lo escribe el servicio transaccional, nunca insertando movimientos a
// mano (I-04: el libro solo se escribe al confirmar).
uses(RefreshDatabase::class);

// AC-039: el kardex recorre los movimientos en orden cronológico con su saldo
// resultante coherente con el anterior, más los saldos inicial y final del
// rango (RF-064, RF-076).
test('el kardex lista movimientos ordenados con saldos inicial y final', function (): void {
    $almacen = Warehouse::factory()->create();
    $lector = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    confirmarApi($this, $lector, crearBorradorApi($this, $lector, cuerpoEntrada($almacen, $producto, '10.000', '10.000000')))->assertOk();

    $salida = crearBorradorApi($this, $lector, [
        'type' => 'salida',
        'origin_warehouse_id' => $almacen->id,
        'lines' => [['product_id' => $producto->id, 'quantity' => '8.000']],
    ]);
    confirmarApi($this, $lector, $salida)->assertOk();

    $ajuste = crearBorradorApi($this, $lector, [
        'type' => 'ajuste',
        'origin_warehouse_id' => $almacen->id,
        'reason_code' => 'inventario_fisico',
        'lines' => [['product_id' => $producto->id, 'quantity' => '5.000', 'direction' => 'entrada']],
    ]);
    confirmarApi($this, $lector, $ajuste)->assertOk();

    actuarComo($lector);
    $respuesta = $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}");

    $respuesta->assertOk()
        ->assertJsonPath('balances.initial_balance', '0.000')
        ->assertJsonPath('balances.final_balance', '7.000')
        ->assertJsonPath('data.0.quantity_delta', '10.000')
        ->assertJsonPath('data.0.balance_after', '10.000')
        ->assertJsonPath('data.1.quantity_delta', '-8.000')
        ->assertJsonPath('data.1.balance_after', '2.000')
        ->assertJsonPath('data.2.quantity_delta', '5.000')
        ->assertJsonPath('data.2.balance_after', '7.000');

    // Los decimales viajan como cadena, nunca como flotante (§3).
    $fila = $respuesta->json('data.0');
    expect($fila['quantity_delta'])->toBeString();
    expect($fila['balance_after'])->toBeString();
    expect($fila['unit_cost'])->toBeString();

    // La marca del movimiento viaja en ISO-8601 UTC aunque el kardex se arme
    // con el constructor de consultas y no pase por el cast del modelo (§3),
    // no en el formato crudo `Y-m-d H:i:s` de MySQL.
    expect($fila['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/');

    // La cadena de saldos cuadra con bcmath en todo el recorrido: cada saldo
    // es el anterior más su delta, y el primero es el inicial más su delta.
    $movimientos = $respuesta->json('data');
    $anterior = $respuesta->json('balances.initial_balance');
    foreach ($movimientos as $movimiento) {
        expect(bcadd($anterior, $movimiento['quantity_delta'], 3))->toBe($movimiento['balance_after']);
        $anterior = $movimiento['balance_after'];
    }
    expect($anterior)->toBe($respuesta->json('balances.final_balance'));
    expect(InventoryMovement::query()->count())->toBe(3);
});

// El rango es inclusivo y el saldo inicial arrastra lo anterior al `from`:
// con `to` en el ayer el rango queda vacío con saldos en cero, y con `from`
// en el mañana todo el historial queda en el saldo inicial (RF-064).
test('el rango del kardex es inclusivo y arrastra el saldo inicial', function (): void {
    $almacen = Warehouse::factory()->create();
    $lector = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    confirmarApi($this, $lector, crearBorradorApi($this, $lector, cuerpoEntrada($almacen, $producto, '4.000', '5.000000')))->assertOk();

    actuarComo($lector);

    $ayer = now()->subDay()->toDateString();
    $manana = now()->addDay()->toDateString();
    $hoy = now()->toDateString();

    // Una fecha sola abarca su día completo: el movimiento de hoy cae dentro
    // del rango de hoy en ambos extremos.
    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}&from={$hoy}&to={$hoy}")
        ->assertOk()
        ->assertJsonPath('balances.initial_balance', '0.000')
        ->assertJsonPath('balances.final_balance', '4.000')
        ->assertJsonCount(1, 'data');

    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}&to={$ayer}")
        ->assertOk()
        ->assertJsonPath('balances.initial_balance', '0.000')
        ->assertJsonPath('balances.final_balance', '0.000')
        ->assertJsonCount(0, 'data');

    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}&from={$manana}")
        ->assertOk()
        ->assertJsonPath('balances.initial_balance', '4.000')
        ->assertJsonPath('balances.final_balance', '4.000')
        ->assertJsonCount(0, 'data');
});

// El §7.9 exige 422 ante rango inválido: el `from` posterior al `to` no llega
// siquiera a la consulta.
test('el kardex rechaza el rango invertido con 422', function (): void {
    $almacen = Warehouse::factory()->create();
    $lector = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    actuarComo($lector);
    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}&from=2026-10-20&to=2026-10-10")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// El §7.9 exige ambos identificadores: sin ellos la petición es 422, y el
// identificador inexistente en la validación también es 422 (la regla exists
// lo ataja antes de tocar el libro).
test('el kardex exige producto y almacen validos', function (): void {
    $almacen = Warehouse::factory()->create();
    $lector = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();

    actuarComo($lector);
    // La petición trae solo el almacén, así que el campo que falta es el producto.
    $this->getJson("/api/v1/inventory/kardex?warehouse_id={$almacen->id}")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.message', 'El producto es obligatorio.');

    // Y aquí falta el almacén. Sin mensaje propio, este campo caía al texto por
    // defecto de Laravel, que está en inglés, y la interfaz lo mostraba tal cual
    // al usuario: la prueba comprobaba el código de error, no el idioma.
    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.message', 'El almacén es obligatorio.');

    $this->getJson("/api/v1/inventory/kardex?product_id=999999&warehouse_id={$almacen->id}")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// El par borrado lógicamente ya no se consulta: responde 404 NO_ENCONTRADO
// por el mapeo de ModelNotFoundException (el §7.9 lo lista antes que el
// alcance). El producto desactivado (is_active=false) sigue consultable
// según AC-018, así que solo pruebo el borrado.
test('el kardex responde 404 ante producto o almacen inexistente', function (): void {
    $almacen = Warehouse::factory()->create();
    $lector = conAlmacenes(confirmadorInventario(), $almacen);
    $producto = Product::factory()->create();
    $producto->delete();

    actuarComo($lector);
    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NO_ENCONTRADO');
});

// Sin alcance sobre el almacén pedido rebota con 403 FUERA_DE_ALCANCE aunque
// el kardex exista, y sin el permiso `inventory.view` rebota con 403
// SIN_PERMISO (C-02).
test('el kardex exige permiso y alcance sobre el almacen', function (): void {
    $almacen = Warehouse::factory()->create();
    $producto = Product::factory()->create();
    $dueno = conAlmacenes(confirmadorInventario(), $almacen);
    confirmarApi($this, $dueno, crearBorradorApi($this, $dueno, cuerpoEntrada($almacen, $producto)))->assertOk();

    $ajeno = confirmadorInventario();
    actuarComo($ajeno);
    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FUERA_DE_ALCANCE');

    actuarComo(usuarioConPermisos([]));
    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SIN_PERMISO');
});

// Sin sesión, el kardex responde 401 NO_AUTENTICADO. Lo aíslo en su propio
// test porque la sesión de Sanctum no se limpia dentro del mismo caso.
test('el kardex sin sesion responde 401 NO_AUTENTICADO', function (): void {
    $almacen = Warehouse::factory()->create();
    $producto = Product::factory()->create();

    $this->getJson("/api/v1/inventory/kardex?product_id={$producto->id}&warehouse_id={$almacen->id}")
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');
});
