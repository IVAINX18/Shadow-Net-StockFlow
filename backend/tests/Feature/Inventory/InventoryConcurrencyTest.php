<?php

declare(strict_types=1);

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidDocumentStateException;
use App\Models\InventoryDocument;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Sin RefreshDatabase a propósito: esa ayuda envuelve cada prueba en una
// transacción abierta y un hijo bifurcado no puede heredar el socket de MySQL
// del padre. Migro en limpio por prueba y confirmo el commit antes de
// bifurcar, así cada hijo abre su propia conexión contra datos visibles. Al
// cerrar migro de nuevo para no contaminar los archivos que corren después,
// que sí aíslan con transacciones y contarían mis filas confirmadas.
uses()->beforeEach(function (): void {
    $this->artisan('migrate:fresh');
});

uses()->afterEach(function (): void {
    $this->artisan('migrate:fresh');
});

// Ejecuto cada confirmación en un proceso hijo con su propia conexión: el
// padre purga la suya antes de bifurcar para que ningún socket se comparta, y
// cada hijo reconecta en limpio antes de tocar la base. Devuelvo por hijo si
// confirmó o con qué excepción falló, leído de archivos temporales.
function confirmarEnParalelo(array $trabajos): array
{
    DB::purge(DB::getDefaultConnection());

    $hijos = [];

    foreach ($trabajos as $trabajo) {
        $archivo = tempnam(sys_get_temp_dir(), 'u2c');

        $pid = pcntl_fork();

        if ($pid === 0) {
            DB::purge(DB::getDefaultConnection());

            try {
                $documento = InventoryDocument::query()->where('uuid', $trabajo['uuid'])->firstOrFail();

                app(InventoryDocumentService::class)->confirm($documento, $trabajo['autorId'], $trabajo['clave']);

                file_put_contents((string) $archivo, json_encode(['ok' => true]));
            } catch (Throwable $e) {
                file_put_contents((string) $archivo, json_encode([
                    'ok' => false,
                    'clase' => get_class($e),
                    'mensaje' => $e->getMessage(),
                ]));
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            exit(0);
        }

        $hijos[] = ['pid' => $pid, 'archivo' => $archivo];
    }

    foreach ($hijos as $hijo) {
        $limite = time() + 60;

        while (true) {
            $espera = pcntl_waitpid($hijo['pid'], $estado, WNOHANG);

            if ($espera === $hijo['pid'] || $espera === -1) {
                break;
            }

            if (time() > $limite) {
                posix_kill($hijo['pid'], SIGKILL);
                pcntl_waitpid($hijo['pid'], $estado);

                throw new RuntimeException('La confirmación concurrente no terminó: posible interbloqueo sin resolver.');
            }

            usleep(50000);
        }
    }

    $resultados = array_map(
        static fn (array $hijo): array => json_decode((string) file_get_contents((string) $hijo['archivo']), true),
        $hijos
    );

    foreach ($hijos as $hijo) {
        @unlink((string) $hijo['archivo']);
    }

    return $resultados;
}

// Preparo 10 unidades confirmadas por el servicio, porque en este archivo no
// hay HTTP: la concurrencia se ejerce contra el motor transaccional.
function existenciaInicial(User $autor, Warehouse $almacen, Product $producto, string $cantidad, string $costo = '10.000000'): void
{
    $servicio = app(InventoryDocumentService::class);

    $borrador = $servicio->create(
        [
            'type' => 'entrada',
            'origin_warehouse_id' => $almacen->id,
            'lines' => [['product_id' => $producto->id, 'quantity' => $cantidad, 'unit_cost' => $costo]],
        ],
        (string) Str::uuid(),
        $autor->id,
    )['document'];

    $servicio->confirm($borrador, $autor->id, (string) Str::uuid());
}

function borradorSalida(User $autor, Warehouse $almacen, Product $producto, string $cantidad): InventoryDocument
{
    return app(InventoryDocumentService::class)->create(
        [
            'type' => 'salida',
            'origin_warehouse_id' => $almacen->id,
            'lines' => [['product_id' => $producto->id, 'quantity' => $cantidad]],
        ],
        (string) Str::uuid(),
        $autor->id,
    )['document'];
}

// C-01 y AC-025: con 10 unidades, dos salidas concurrentes de 8 dejan el
// saldo en 2 con exactamente una confirmada; la otra falla limpio.
test('sobreventa concurrente confirma exactamente una salida', function (): void {
    $autor = User::factory()->create(['is_active' => true]);
    $almacen = Warehouse::factory()->create();
    $producto = Product::factory()->create();

    existenciaInicial($autor, $almacen, $producto, '10.000');

    $primera = borradorSalida($autor, $almacen, $producto, '8.000');
    $segunda = borradorSalida($autor, $almacen, $producto, '8.000');

    [$resultadoUno, $resultadoDos] = confirmarEnParalelo([
        ['uuid' => $primera->uuid, 'autorId' => $autor->id, 'clave' => (string) Str::uuid()],
        ['uuid' => $segunda->uuid, 'autorId' => $autor->id, 'clave' => (string) Str::uuid()],
    ]);

    $exitos = collect([$resultadoUno, $resultadoDos])->where('ok', true)->count();
    $fallos = collect([$resultadoUno, $resultadoDos])->where('ok', false)->values();

    expect($exitos)->toBe(1);
    expect($fallos)->toHaveCount(1);
    expect($fallos[0]['clase'])->toBe(InsufficientStockException::class);

    expect(saldoDe($producto->id, $almacen->id))->toBe('2.000');
    expect(InventoryDocument::query()->where('status', 'confirmado')->count())->toBe(2);
    expect(InventoryDocument::query()->where('status', 'borrador')->count())->toBe(1);

    // Coherencia: 10 de entrada menos 8 de la única salida ganadora.
    $suma = (string) InventoryMovement::query()
        ->where('product_id', $producto->id)
        ->where('warehouse_id', $almacen->id)
        ->sum('quantity_delta');

    expect(bcadd($suma, '0', 3))->toBe('2.000');
})->skip(! function_exists('pcntl_fork'), 'Sin pcntl no hay concurrencia real.');

// C-02: dos transferencias cruzadas del mismo producto no dejan saldos
// negativos ni interbloqueos sin resolver, gracias al orden global.
test('transferencias cruzadas concurrentes no se interbloquean', function (): void {
    $autor = User::factory()->create(['is_active' => true]);
    $origen = Warehouse::factory()->create();
    $destino = Warehouse::factory()->create();
    $producto = Product::factory()->create();

    existenciaInicial($autor, $origen, $producto, '10.000');
    existenciaInicial($autor, $destino, $producto, '10.000');

    $servicio = app(InventoryDocumentService::class);

    $ida = $servicio->create(
        [
            'type' => 'transferencia',
            'origin_warehouse_id' => $origen->id,
            'destination_warehouse_id' => $destino->id,
            'lines' => [['product_id' => $producto->id, 'quantity' => '4.000']],
        ],
        (string) Str::uuid(),
        $autor->id,
    )['document'];

    $vuelta = $servicio->create(
        [
            'type' => 'transferencia',
            'origin_warehouse_id' => $destino->id,
            'destination_warehouse_id' => $origen->id,
            'lines' => [['product_id' => $producto->id, 'quantity' => '4.000']],
        ],
        (string) Str::uuid(),
        $autor->id,
    )['document'];

    [$resultadoIda, $resultadoVuelta] = confirmarEnParalelo([
        ['uuid' => $ida->uuid, 'autorId' => $autor->id, 'clave' => (string) Str::uuid()],
        ['uuid' => $vuelta->uuid, 'autorId' => $autor->id, 'clave' => (string) Str::uuid()],
    ]);

    expect($resultadoIda['ok'])->toBeTrue();
    expect($resultadoVuelta['ok'])->toBeTrue();

    expect(saldoDe($producto->id, $origen->id))->toBe('10.000');
    expect(saldoDe($producto->id, $destino->id))->toBe('10.000');
})->skip(! function_exists('pcntl_fork'), 'Sin pcntl no hay concurrencia real.');

// C-04 y AC-029: dos confirmaciones simultáneas del mismo documento aplican
// efectos una sola vez; la otra es rechazada por estado.
test('doble confirmacion simultanea aplica efectos una sola vez', function (): void {
    $autor = User::factory()->create(['is_active' => true]);
    $almacen = Warehouse::factory()->create();
    $producto = Product::factory()->create();

    existenciaInicial($autor, $almacen, $producto, '10.000');

    $salida = borradorSalida($autor, $almacen, $producto, '3.000');

    [$resultadoUno, $resultadoDos] = confirmarEnParalelo([
        ['uuid' => $salida->uuid, 'autorId' => $autor->id, 'clave' => (string) Str::uuid()],
        ['uuid' => $salida->uuid, 'autorId' => $autor->id, 'clave' => (string) Str::uuid()],
    ]);

    $exitos = collect([$resultadoUno, $resultadoDos])->where('ok', true)->count();
    $fallos = collect([$resultadoUno, $resultadoDos])->where('ok', false)->values();

    expect($exitos)->toBe(1);
    expect($fallos)->toHaveCount(1);
    expect($fallos[0]['clase'])->toBe(InvalidDocumentStateException::class);

    expect(InventoryMovement::query()->where('document_id', $salida->id)->count())->toBe(1);
    expect(saldoDe($producto->id, $almacen->id))->toBe('7.000');
})->skip(! function_exists('pcntl_fork'), 'Sin pcntl no hay concurrencia real.');
