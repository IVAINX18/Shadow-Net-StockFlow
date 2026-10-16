<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Domain\Enums\InventoryDocumentStatus;
use App\Domain\Enums\InventoryDocumentType;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidDocumentStateException;
use App\Models\Inventory;
use App\Models\InventoryDocument;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Concentro aquí las reglas de fondo del borrador (almacenes y productos
// activos, coherencia por tipo, motivo obligatorio en ajustes y cálculo de
// totales) porque deben aplicarse igual desde cualquier puerta: el Form
// Request valida la forma con InventoryRules y yo el fondo, como hace
// ProductService con la categoría activa. El controlador solo traduce HTTP y
// el alcance por almacén lo verifica él, porque el alcance depende del
// usuario de la petición y no del contenido del documento.
//
// La Unidad 2 añade el motor transaccional: confirmar aplica los efectos
// (movimientos append-only + saldo materializado + promedio ponderado) y
// revertir los compensa, siempre dentro de una transacción con bloqueos en
// orden determinista y reintento acotado ante interbloqueos (§5 y §6).
final class InventoryDocumentService
{
    // Espejo de tipos para la reversión: la entrada se compensa con una
    // salida, la salida con una entrada, la transferencia con otra en sentido
    // contrario y el ajuste con otro de signo invertido por línea.
    private const TIPO_ESPEJO = [
        'entrada' => 'salida',
        'salida' => 'entrada',
        'ajuste' => 'ajuste',
        'transferencia' => 'transferencia',
    ];

    public function __construct(
        private readonly AuditLogger $auditoria,
    ) {}

    /**
     * Creo el borrador de forma idempotente: si la clave ya existe y el cuerpo
     * canónico coincide, devuelvo el documento original sin insertar nada; si
     * difiere, lanzo conflicto en lugar de un segundo documento (C-05).
     *
     * Con `confirm: true` (RF-075) el borrador y sus efectos nacen en la misma
     * transacción, por el mismo camino que la confirmación diferida: ningún
     * lector ve el borrador intermedio fuera de ella.
     *
     * @param  array<string, mixed>  $data  Datos ya validados en forma.
     * @return array{document: InventoryDocument, replayed: bool, confirmed: bool}
     *
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function create(array $data, string $idempotencyKey, int $authorId, bool $confirm = false, ?Request $solicitud = null): array
    {
        $this->assertDominio($data);

        $existente = InventoryDocument::query()
            ->where('idempotency_key', $idempotencyKey)
            ->with('lines')
            ->first();

        if ($existente !== null) {
            $documento = $this->replayOrConflict($existente, $data);

            // Si el original quedó en borrador y ahora me piden confirmarlo,
            // lo confirmo por el camino diferido: los efectos siguen siendo
            // exactamente uno (C-05) aunque la primera llamada no confirmara.
            if ($confirm && $documento->status === InventoryDocumentStatus::Borrador->value) {
                return [...$this->confirm($documento, $authorId, $idempotencyKey, $solicitud), 'confirmed' => true];
            }

            return ['document' => $documento, 'replayed' => true, 'confirmed' => false];
        }

        try {
            $documento = $this->reintentarAnteInterbloqueo(fn (): InventoryDocument => DB::transaction(function () use ($data, $idempotencyKey, $authorId, $confirm, $solicitud): InventoryDocument {
                $documento = $this->insertarBorrador($data, $idempotencyKey, $authorId);

                // Dejo constancia de la creación dentro de la misma
                // transacción cuando voy a confirmar de inmediato: si la
                // confirmación revierte, la bitácora no queda a medias. El
                // borrador simple lo audita el controlador como en la Unidad 1.
                if ($confirm) {
                    $this->auditoria->record(
                        'crear_documento',
                        'inventory_document',
                        (int) $documento->id,
                        null,
                        $documento->toArray(),
                        $this->solicitudParaAuditoria($solicitud, $authorId),
                    );

                    return $this->confirmarBorrador($documento, $authorId, $solicitud);
                }

                return $documento;
            }));
        } catch (QueryException $e) {
            // La UNIQUE es la garantía dura ante dos inserciones simultáneas
            // con la misma clave (§6.3 del dominio): una gana y la otra cae
            // aquí; releyo y aplico la misma regla que en el camino normal.
            if ($this->esConflictoDeIdempotencia($e)) {
                $carrera = InventoryDocument::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->with('lines')
                    ->firstOrFail();

                $documento = $this->replayOrConflict($carrera, $data);

                if ($confirm && $documento->status === InventoryDocumentStatus::Borrador->value) {
                    return [...$this->confirm($documento, $authorId, $idempotencyKey, $solicitud), 'confirmed' => true];
                }

                return ['document' => $documento, 'replayed' => true, 'confirmed' => false];
            }

            throw $e;
        }

        return ['document' => $documento, 'replayed' => false, 'confirmed' => $confirm];
    }

    /**
     * Confirmo un borrador aplicando sus efectos en una transacción: bloqueo el
     * documento para que dos confirmaciones no lo apliquen dos veces (C-04),
     * revalido el fondo al momento de aplicar (RF-074), bloqueo los saldos en
     * orden determinista, valido disponibilidad, escribo movimientos y saldo
     * juntos (I-02), marco confirmado y audito dentro de la misma
     * transacción (§5.1).
     *
     * El reintento con la misma clave sobre un documento ya confirmado
     * devuelve el original sin efectos nuevos (RF-069); con otra clave es un
     * conflicto de estado (C-04, AC-029).
     *
     * @return array{document: InventoryDocument, replayed: bool}
     *
     * @throws ValidationException
     * @throws InvalidDocumentStateException
     * @throws InsufficientStockException
     */
    public function confirm(InventoryDocument $documento, int $autorId, string $claveIdempotencia, ?Request $solicitud = null): array
    {
        return $this->reintentarAnteInterbloqueo(fn (): array => DB::transaction(function () use ($documento, $autorId, $claveIdempotencia, $solicitud): array {
            // Bloqueo el documento antes de leer su estado: sin este candado
            // dos confirmaciones simultáneas leerían `borrador` a la vez y
            // ambas aplicarían efectos (C-04).
            $bloqueado = InventoryDocument::query()
                ->whereKey($documento->id)
                ->lockForUpdate()
                ->firstOrFail();

            $bloqueado->load('lines');

            if ($bloqueado->status === InventoryDocumentStatus::Confirmado->value) {
                if ($bloqueado->idempotency_key === $claveIdempotencia) {
                    return ['document' => $bloqueado, 'replayed' => true];
                }

                throw new InvalidDocumentStateException('El documento ya está confirmado.');
            }

            return [
                'document' => $this->confirmarBorrador($bloqueado, $autorId, $solicitud),
                'replayed' => false,
            ];
        }));
    }

    /**
     * Revierto un documento confirmado generando sus movimientos
     * compensatorios: creo un documento de reversión ya confirmado (espejo del
     * original), aplico sus efectos en la misma transacción, marco el
     * original como `revertido` y lo enlazo con `reversed_by_id`. Nunca edito
     * ni borro el original ni sus movimientos (RN-INV-07).
     *
     * Si el destino ya consumió parte de lo recibido, la compensación completa
     * se rechaza con STOCK_INSUFICIENTE y se exige compensar por partes
     * (RF-078): no existe la reversión parcial automática.
     *
     * @return array{original: InventoryDocument, reversal: ?InventoryDocument, replayed: bool}
     *
     * @throws ValidationException
     * @throws InvalidDocumentStateException
     * @throws InsufficientStockException
     */
    public function reverse(InventoryDocument $documento, int $autorId, string $motivo, ?Request $solicitud = null): array
    {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'reason' => ['La reversión exige un motivo.'],
            ]);
        }

        return $this->reintentarAnteInterbloqueo(fn (): array => DB::transaction(function () use ($documento, $autorId, $motivo, $solicitud): array {
            $bloqueado = InventoryDocument::query()
                ->whereKey($documento->id)
                ->lockForUpdate()
                ->firstOrFail();

            $bloqueado->load('lines');

            // La reversión también es idempotente en la práctica: si el
            // original ya quedó revertido, devuelvo el par sin efectos
            // nuevos en lugar de compensar dos veces.
            if ($bloqueado->status === InventoryDocumentStatus::Revertido->value) {
                $reversion = $bloqueado->reversed_by_id === null
                    ? null
                    : InventoryDocument::query()->with('lines')->find($bloqueado->reversed_by_id);

                return ['original' => $bloqueado, 'reversal' => $reversion, 'replayed' => true];
            }

            if ($bloqueado->status !== InventoryDocumentStatus::Confirmado->value) {
                throw new InvalidDocumentStateException('Solo un documento confirmado admite reversión.');
            }

            $this->revalidarActivos($bloqueado);

            // Espejo los costos de los movimientos originales, no los de las
            // líneas: la salida se valoró al promedio vigente y la entrada al
            // costo capturado, y la compensación debe deshacer esos importes
            // exactos para que el kardex quede trazado pata a pata.
            $movimientos = InventoryMovement::query()
                ->where('document_id', $bloqueado->id)
                ->orderBy('id')
                ->get()
                ->groupBy('document_line_id');

            $tipoEspejo = self::TIPO_ESPEJO[$bloqueado->type];

            $origenEspejo = $bloqueado->type === InventoryDocumentType::Transferencia->value
                ? (int) $bloqueado->destination_warehouse_id
                : (int) $bloqueado->origin_warehouse_id;

            $destinoEspejo = $bloqueado->type === InventoryDocumentType::Transferencia->value
                ? (int) $bloqueado->origin_warehouse_id
                : $bloqueado->destination_warehouse_id;

            // La clave de la reversión deriva del uuid del original: es
            // determinista y única, así que dos reversiones simultáneas del
            // mismo documento chocan en la UNIQUE y solo una inserta; la otra
            // relee el par ya revertido en lugar de compensar dos veces.
            $claveReversion = 'REV-'.$bloqueado->uuid;

            try {
                $reversion = InventoryDocument::create([
                    'uuid' => (string) Str::uuid(),
                    'doc_number' => 'TMP-'.(string) Str::uuid(),
                    'type' => $tipoEspejo,
                    'status' => InventoryDocumentStatus::Confirmado->value,
                    'origin_warehouse_id' => $origenEspejo,
                    'destination_warehouse_id' => $destinoEspejo,
                    'idempotency_key' => $claveReversion,
                    'reference' => null,
                    // Guardo el motivo en las notas porque el esquema no
                    // define columna de motivo de reversión y las notas son su
                    // justificación libre; la auditoría lo conserva además.
                    'notes' => trim($motivo),
                    'reason_code' => $bloqueado->reason_code,
                    'created_by' => $autorId,
                    'confirmed_by' => $autorId,
                    'confirmed_at' => now(),
                ]);
            } catch (QueryException $e) {
                if ($this->esConflictoDeIdempotencia($e)) {
                    $reversion = InventoryDocument::query()
                        ->where('idempotency_key', $claveReversion)
                        ->with('lines')
                        ->firstOrFail();

                    $original = $bloqueado->refresh()->load('lines');

                    return ['original' => $original, 'reversal' => $reversion, 'replayed' => true];
                }

                throw $e;
            }

            $reversion->update([
                'doc_number' => $this->numeroLegible($tipoEspejo, (int) $reversion->id),
            ]);

            $efectos = [];
            $indice = 0;

            foreach ($bloqueado->lines as $linea) {
                $patas = $this->patasOriginales($movimientos, (int) $linea->id, $bloqueado, $indice);

                $direccionEspejo = $bloqueado->type === InventoryDocumentType::Ajuste->value
                    ? ($linea->direction === 'salida' ? 'entrada' : 'salida')
                    : null;

                $costoEspejo = $this->costoEspejo($patas, $bloqueado->type);

                $lineaReversion = $reversion->lines()->create([
                    'product_id' => $linea->product_id,
                    'quantity' => (string) $linea->quantity,
                    'unit_cost' => $costoEspejo,
                    'line_total' => bcadd(bcmul((string) $linea->quantity, $costoEspejo, 3), '0.005', 2),
                    'direction' => $direccionEspejo,
                ]);

                foreach ($this->efectosDeLineaEspejo($bloqueado, $linea, $lineaReversion, $patas, $origenEspejo, $indice) as $efecto) {
                    $efectos[] = $efecto;
                }

                $indice++;
            }

            // Aplico la compensación con el mismo motor que la confirmación:
            // mismos bloqueos ordenados, misma validación de disponibilidad
            // (aquí es donde RF-078 rechaza el destino ya consumido) y mismos
            // movimientos append-only enlazados al documento de reversión.
            $this->ejecutarEfectos((int) $reversion->id, $efectos, $autorId);

            $valoresViejos = $bloqueado->toArray();

            $bloqueado->update([
                'status' => InventoryDocumentStatus::Revertido->value,
                'reversed_by_id' => (int) $reversion->id,
            ]);

            $this->auditoria->record(
                'revertir_documento',
                'inventory_document',
                (int) $bloqueado->id,
                $valoresViejos,
                [
                    'original' => $bloqueado->refresh()->toArray(),
                    'reversal_uuid' => $reversion->uuid,
                    'reversal_doc_number' => $reversion->doc_number,
                    'reason' => trim($motivo),
                ],
                $this->solicitudParaAuditoria($solicitud, $autorId),
            );

            return [
                'original' => $bloqueado->load('lines'),
                'reversal' => $reversion->refresh()->load('lines'),
                'replayed' => false,
            ];
        }));
    }

    /**
     * Repongo el borrador completo (cabecera y líneas) porque RF-053 permite
     * agregar, editar o retirar líneas y la forma más segura de hacerlo es
     * reemplazar el conjunto entero en una transacción: nunca queda un
     * borrador a medias entre el borrado y la reinserción.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws InvalidDocumentStateException
     */
    public function update(InventoryDocument $documento, array $data): InventoryDocument
    {
        $this->assertBorrador($documento);
        $this->assertDominio($data);

        return DB::transaction(function () use ($documento, $data): InventoryDocument {
            $documento->update([
                'type' => $data['type'],
                'origin_warehouse_id' => $data['origin_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'] ?? null,
                'reason_code' => $data['reason_code'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Si el tipo cambió, el prefijo del número legible debe seguirlo:
            // lo regenero desde el mismo id para no dejar un ENT en un SAL.
            $documento->update([
                'doc_number' => $this->numeroLegible($data['type'], (int) $documento->id),
            ]);

            $documento->lines()->delete();
            $this->insertarLineas($documento, $data['lines'], $data['type']);

            return $documento->refresh()->load('lines');
        });
    }

    /**
     * @throws InvalidDocumentStateException
     */
    public function discard(InventoryDocument $documento): InventoryDocument
    {
        $this->assertBorrador($documento);

        // El descarte no genera movimientos ni toca existencias: solo cierra
        // el borrador como estado terminal (§3.1).
        $documento->update(['status' => InventoryDocumentStatus::Descartado->value]);

        return $documento->refresh()->load('lines');
    }

    /**
     * @throws InvalidDocumentStateException
     */
    private function assertBorrador(InventoryDocument $documento): void
    {
        if ($documento->status !== InventoryDocumentStatus::Borrador->value) {
            throw new InvalidDocumentStateException(
                'Solo un documento en borrador admite esta operación.'
            );
        }
    }

    // Aplico los efectos de un borrador ya bloqueado dentro de la transacción
    // en curso: revalido el fondo al momento de aplicar (el almacén o el
    // producto pudieron desactivarse tras crear el borrador, RF-074),
    // construyo los efectos, los ejecuto y marco confirmado con responsable,
    // fecha y auditoría. Lo uso tanto en la confirmación diferida como en el
    // atajo confirm:true, así ambos pasan por el mismo servicio (RF-075).
    /**
     * @throws ValidationException
     * @throws InvalidDocumentStateException
     * @throws InsufficientStockException
     */
    private function confirmarBorrador(InventoryDocument $documento, int $autorId, ?Request $solicitud): InventoryDocument
    {
        $this->assertBorrador($documento);
        $this->revalidarActivos($documento);
        $this->validarDecimalesPorUnidad($documento);
        $this->assertTransferenciaDistinta($documento);

        $this->ejecutarEfectos((int) $documento->id, $this->efectosDeConfirmacion($documento), $autorId);

        $valoresViejos = $documento->toArray();

        $documento->update([
            'status' => InventoryDocumentStatus::Confirmado->value,
            'confirmed_by' => $autorId,
            'confirmed_at' => now(),
        ]);

        $this->auditoria->record(
            'confirmar_documento',
            'inventory_document',
            (int) $documento->id,
            $valoresViejos,
            $documento->refresh()->load('lines')->toArray(),
            $this->solicitudParaAuditoria($solicitud, $autorId),
        );

        return $documento->load('lines');
    }

    // Construyo un efecto por pata de almacén: la entrada y la transferencia
    // generan una pata de entrada y una de salida por línea, el resto una
    // sola. La cantidad viaja siempre positiva; la dirección fija el signo
    // (RN-INV-01). Ordeno por (almacén, producto) para que el bloqueo posterior
    // siga el orden global y dos operaciones cruzadas no se interbloqueen.
    /**
     * @return list<array{warehouse_id: int, product_id: int, line_id: int, linea: int, quantity: string, direction: string, costo: ?string, viaje_desde: ?int, ajuste: bool}>
     */
    private function efectosDeConfirmacion(InventoryDocument $documento): array
    {
        $efectos = [];
        $indice = 0;

        foreach ($documento->lines as $linea) {
            $cantidad = bcadd((string) $linea->quantity, '0', 3);
            $productoId = (int) $linea->product_id;

            if ($documento->type === InventoryDocumentType::Entrada->value) {
                $efectos[] = $this->efecto(
                    (int) $documento->origin_warehouse_id, $productoId, (int) $linea->id, $indice,
                    $cantidad, 'entrada', bcadd((string) $linea->unit_cost, '0', 6), null, false,
                );
            } elseif ($documento->type === InventoryDocumentType::Salida->value) {
                $efectos[] = $this->efecto(
                    (int) $documento->origin_warehouse_id, $productoId, (int) $linea->id, $indice,
                    $cantidad, 'salida', null, null, false,
                );
            } elseif ($documento->type === InventoryDocumentType::Ajuste->value) {
                // El signo del ajuste vive en la dirección de la línea
                // (normalizada a `entrada` al insertar): el motivo no lo
                // distingue y la cantidad siempre es positiva.
                $direccion = $linea->direction === 'salida' ? 'salida' : 'entrada';

                $efectos[] = $this->efecto(
                    (int) $documento->origin_warehouse_id, $productoId, (int) $linea->id, $indice,
                    $cantidad, $direccion,
                    $direccion === 'entrada' ? bcadd((string) $linea->unit_cost, '0', 6) : null,
                    null, true,
                );
            } else {
                // La transferencia descuenta en origen y acredita en destino:
                // el costo que viaja es el promedio vigente en origen, que
                // resuelvo tras bloquear (viaje_desde) para leerlo bloqueado.
                $efectos[] = $this->efecto(
                    (int) $documento->origin_warehouse_id, $productoId, (int) $linea->id, $indice,
                    $cantidad, 'salida', null, null, false,
                );
                $efectos[] = $this->efecto(
                    (int) $documento->destination_warehouse_id, $productoId, (int) $linea->id, $indice,
                    $cantidad, 'entrada', null, (int) $documento->origin_warehouse_id, false,
                );
            }

            $indice++;
        }

        usort($efectos, static fn (array $a, array $b): int => [$a['warehouse_id'], $a['product_id']] <=> [$b['warehouse_id'], $b['product_id']]);

        return $efectos;
    }

    /**
     * @return array{warehouse_id: int, product_id: int, line_id: int, linea: int, quantity: string, direction: string, costo: ?string, viaje_desde: ?int, ajuste: bool}
     */
    private function efecto(int $almacenId, int $productoId, int $lineaId, int $indice, string $cantidad, string $direccion, ?string $costo, ?int $viajeDesde, bool $ajuste): array
    {
        return [
            'warehouse_id' => $almacenId,
            'product_id' => $productoId,
            'line_id' => $lineaId,
            'linea' => $indice,
            'quantity' => $cantidad,
            'direction' => $direccion,
            'costo' => $costo,
            'viaje_desde' => $viajeDesde,
            'ajuste' => $ajuste,
        ];
    }

    // Ejecuto los efectos dentro de la transacción en curso: bloqueo los
    // saldos en orden (almacén, producto), valido toda la disponibilidad antes
    // de aplicar el primer delta (ningún efecto parcial observable aunque el
    // ROLLBACK ya lo garantizaría), aplico cantidades y promedios, y escribo
    // un movimiento append-only por pata con su saldo resultante (RF-076).
    /**
     * @param  list<array{warehouse_id: int, product_id: int, line_id: int, linea: int, quantity: string, direction: string, costo: ?string, viaje_desde: ?int, ajuste: bool}>  $efectos
     *
     * @throws InsufficientStockException
     */
    private function ejecutarEfectos(int $documentoId, array $efectos, int $autorId): void
    {
        $filas = $this->bloquearSaldos($efectos);

        foreach ($efectos as $indice => $efecto) {
            if ($efecto['viaje_desde'] !== null) {
                // Resuelvo el costo viajero tras bloquear: es el promedio
                // vigente en el origen en el momento de aplicar (§4.4).
                $origen = $filas[$efecto['viaje_desde'].':'.$efecto['product_id']] ?? null;

                $efectos[$indice]['costo'] = $origen === null
                    ? '0.000000'
                    : bcadd((string) $origen->average_cost, '0', 6);
            }
        }

        // Valido todas las patas de salida antes de aplicar ninguna: así el
        // 409 informa cada línea corta y ningún saldo se toca en vano.
        $faltantes = [];

        foreach ($efectos as $efecto) {
            if ($efecto['direction'] !== 'salida') {
                continue;
            }

            $fila = $filas[$efecto['warehouse_id'].':'.$efecto['product_id']] ?? null;
            $disponible = $fila === null ? '0.000' : bcadd((string) $fila->quantity, '0', 3);

            if (bccomp($disponible, $efecto['quantity'], 3) < 0) {
                $faltantes[] = [
                    'field' => 'lines.'.$efecto['linea'].'.quantity',
                    'code' => 'STOCK_INSUFICIENTE',
                    'message' => 'Disponible: '.$disponible.', solicitado: '.$efecto['quantity'].'.',
                ];
            }
        }

        if ($faltantes !== []) {
            throw new InsufficientStockException($faltantes);
        }

        foreach ($efectos as $efecto) {
            $clave = $efecto['warehouse_id'].':'.$efecto['product_id'];
            $fila = $filas[$clave];

            // La fila siempre existe aquí: la pata de entrada la creó el
            // bloqueo y la de salida pasó la validación de disponibilidad.
            $existencia = bcadd((string) $fila->quantity, '0', 3);
            $promedio = bcadd((string) $fila->average_cost, '0', 6);

            if ($efecto['direction'] === 'entrada') {
                $costo = $efecto['costo'] ?? '0.000000';
                $nueva = bcadd($existencia, $efecto['quantity'], 3);

                if ($efecto['ajuste'] && bccomp($existencia, '0', 3) > 0) {
                    // El ajuste de cantidad conserva la valorización: solo
                    // redistribuye cantidades sin tocar el promedio (§4.3). El
                    // movimiento se valora al promedio vigente, no al costo
                    // capturado.
                    $nuevoPromedio = $promedio;
                    $costoMovimiento = $promedio;
                } else {
                    // Promedio ponderado móvil (§8.1): calculo con nueve
                    // decimales intermedios y trunco a seis, siempre igual.
                    $nuevoPromedio = bcdiv(
                        bcadd(bcmul($existencia, $promedio, 9), bcmul($efecto['quantity'], $costo, 9), 9),
                        $nueva,
                        6,
                    );
                    $costoMovimiento = $costo;
                }

                $fila->update([
                    'quantity' => $nueva,
                    'average_cost' => $nuevoPromedio,
                    'last_movement_at' => now(),
                ]);

                $this->insertarMovimiento($documentoId, $efecto, bcadd($efecto['quantity'], '0', 3), $costoMovimiento, $nueva, $autorId);
            } else {
                $nueva = bcsub($existencia, $efecto['quantity'], 3);

                // La salida y el ajuste negativo no alteran el promedio: salen
                // valorados al vigente (§8.1).
                $fila->update([
                    'quantity' => $nueva,
                    'last_movement_at' => now(),
                ]);

                $this->insertarMovimiento($documentoId, $efecto, bcsub('0', $efecto['quantity'], 3), $promedio, $nueva, $autorId);
            }

            $filas[$clave] = $fila->refresh();
        }
    }

    // Bloqueo cada saldo afectado con SELECT ... FOR UPDATE en orden
    // ascendente por (almacén, producto) (§6.2): dos operaciones cruzadas
    // toman los mismos candados en el mismo orden y no se interbloquean por
    // suerte. La fila inexistente con pata de entrada se crea (el §6.3
    // resuelve la carrera con la UNIQUE y relectura); sin pata de entrada la
    // existencia es cero y la validación la rechazará.
    /**
     * @param  list<array{warehouse_id: int, product_id: int, line_id: int, linea: int, quantity: string, direction: string, costo: ?string, viaje_desde: ?int, ajuste: bool}>  $efectos
     * @return array<string, ?Inventory>
     */
    private function bloquearSaldos(array $efectos): array
    {
        $pares = [];

        foreach ($efectos as $efecto) {
            $clave = $efecto['warehouse_id'].':'.$efecto['product_id'];

            $pares[$clave] ??= [
                'warehouse_id' => $efecto['warehouse_id'],
                'product_id' => $efecto['product_id'],
                'con_entrada' => false,
            ];

            if ($efecto['direction'] === 'entrada') {
                $pares[$clave]['con_entrada'] = true;
            }
        }

        $ordenados = array_values($pares);

        usort($ordenados, static fn (array $a, array $b): int => [$a['warehouse_id'], $a['product_id']] <=> [$b['warehouse_id'], $b['product_id']]);

        $filas = [];

        foreach ($ordenados as $par) {
            $clave = $par['warehouse_id'].':'.$par['product_id'];

            $fila = Inventory::query()
                ->where('warehouse_id', $par['warehouse_id'])
                ->where('product_id', $par['product_id'])
                ->lockForUpdate()
                ->first();

            if ($fila === null && $par['con_entrada']) {
                try {
                    Inventory::create([
                        'product_id' => $par['product_id'],
                        'warehouse_id' => $par['warehouse_id'],
                        'quantity' => '0.000',
                        'reserved_quantity' => '0.000',
                        'average_cost' => '0.000000',
                    ]);
                } catch (QueryException $e) {
                    // Otra confirmación creó la fila entre mi lectura y mi
                    // inserción (§6.3): la UNIQUE me detiene y releo la suya.
                    if (! $this->esConflictoDeSaldo($e)) {
                        throw $e;
                    }
                }

                $fila = Inventory::query()
                    ->where('warehouse_id', $par['warehouse_id'])
                    ->where('product_id', $par['product_id'])
                    ->lockForUpdate()
                    ->first();
            }

            $filas[$clave] = $fila;
        }

        return $filas;
    }

    /**
     * @param  array{warehouse_id: int, product_id: int, line_id: int, linea: int, quantity: string, direction: string, costo: ?string, viaje_desde: ?int, ajuste: bool}  $efecto
     */
    private function insertarMovimiento(int $documentoId, array $efecto, string $delta, string $costo, string $saldo, int $autorId): void
    {
        // El movimiento es append-only: una vez insertado jamás se actualiza
        // ni se elimina (C-09); la corrección es otro movimiento.
        InventoryMovement::create([
            'document_id' => $documentoId,
            'document_line_id' => $efecto['line_id'],
            'product_id' => $efecto['product_id'],
            'warehouse_id' => $efecto['warehouse_id'],
            'direction' => $efecto['direction'],
            'quantity_delta' => $delta,
            'unit_cost' => $costo,
            'balance_after' => $saldo,
            'created_by' => $autorId,
        ]);
    }

    // Reintento la operación completa ante un interbloqueo (3 intentos con
    // retroceso exponencial y jitter): el deadlock es transitorio y se
    // resuelve repitiendo la transacción entera, nunca el delta parcial
    // (§6.4). El fallo de negocio (validación, estado, stock, idempotencia) no
    // se reintenta jamás: repetirlo solo repetiría el rechazo.
    private function reintentarAnteInterbloqueo(Closure $operacion): mixed
    {
        for ($intento = 1; $intento <= 3; $intento++) {
            try {
                return $operacion();
            } catch (QueryException $e) {
                if (! $this->esInterbloqueo($e) || $intento === 3) {
                    throw $e;
                }

                usleep(50000 * (2 ** ($intento - 1)) + random_int(0, 25000));
            }
        }

        // Inalcanzable: el bucle siempre devuelve o lanza en el intento 3.
        throw new \LogicException('Reintento de interbloqueo agotado sin resultado.');
    }

    private function esInterbloqueo(QueryException $e): bool
    {
        // 1213 es el interbloqueo de InnoDB y 40001 su SQLSTATE; el 1205
        // (espera de candado agotada) no lo reintento porque indica contención
        // sostenida, no un ciclo transitorio: serializará en el siguiente
        // intento del cliente en lugar de girar aquí.
        return ($e->errorInfo[1] ?? null) === 1213 || ($e->errorInfo[0] ?? '') === '40001';
    }

    // Recupero las patas originales de una línea para espejarlas: una por
    // almacén (la transferencia deja dos, salida en origen y entrada en
    // destino). Si falta alguna, el kardex está corrupto y prefiero un 409
    // explícito a una compensación a ciegas.
    /**
     * @param  Collection<int, Collection<int, InventoryMovement>>  $agrupados
     * @return Collection<int, InventoryMovement>
     *
     * @throws InvalidDocumentStateException
     */
    private function patasOriginales($agrupados, int $lineaId, InventoryDocument $documento, int $indice): object
    {
        $esperadas = $documento->type === InventoryDocumentType::Transferencia->value ? 2 : 1;

        $patas = $agrupados->get($lineaId, collect())->values();

        if ($patas->count() !== $esperadas) {
            throw new InvalidDocumentStateException(
                'La línea '.($indice + 1).' no tiene movimientos que revertir.'
            );
        }

        return $patas;
    }

    // El costo espejo es el costo aplicado en el movimiento original: así la
    // compensación deshace los importes exactos que la confirmación escribió.
    /**
     * @param  Collection<int, InventoryMovement>  $patas
     */
    private function costoEspejo($patas, string $tipo): string
    {
        if ($tipo === InventoryDocumentType::Transferencia->value) {
            $salida = $patas->firstWhere('direction', 'salida');

            return bcadd((string) ($salida?->unit_cost ?? '0'), '0', 6);
        }

        return bcadd((string) ($patas->first()?->unit_cost ?? '0'), '0', 6);
    }

    // Construyo los efectos compensatorios de una línea: espejan cada pata
    // original con signo opuesto en el mismo almacén (la transferencia ya trae
    // los almacenes intercambiados en origen/destino espejo).
    /**
     * @param  Collection<int, InventoryMovement>  $patas
     * @return list<array{warehouse_id: int, product_id: int, line_id: int, linea: int, quantity: string, direction: string, costo: ?string, viaje_desde: ?int, ajuste: bool}>
     */
    private function efectosDeLineaEspejo(
        InventoryDocument $original,
        $linea,
        $lineaReversion,
        $patas,
        int $origenEspejo,
        int $indice,
    ): array {
        $cantidad = bcadd((string) $linea->quantity, '0', 3);
        $productoId = (int) $linea->product_id;
        $lineaReversionId = (int) $lineaReversion->id;

        if ($original->type === InventoryDocumentType::Transferencia->value) {
            // La pata de retorno respeta la disponibilidad del almacén que
            // había recibido: ejecutarEfectos la valida y rechaza la
            // reversión completa si ya se consumió (RF-078).
            return [
                $this->efecto($origenEspejo, $productoId, $lineaReversionId, $indice, $cantidad, 'salida', null, null, false),
                $this->efecto((int) $original->origin_warehouse_id, $productoId, $lineaReversionId, $indice, $cantidad, 'entrada', $this->costoEspejo($patas, $original->type), null, false),
            ];
        }

        $direccion = match ($original->type) {
            InventoryDocumentType::Entrada->value => 'salida',
            InventoryDocumentType::Salida->value => 'entrada',
            default => ($linea->direction === 'salida' ? 'entrada' : 'salida'),
        };

        $esAjuste = $original->type === InventoryDocumentType::Ajuste->value;

        return [
            $this->efecto(
                $origenEspejo, $productoId, $lineaReversionId, $indice, $cantidad, $direccion,
                $direccion === 'entrada' ? $this->costoEspejo($patas, $original->type) : null,
                null, $esAjuste,
            ),
        ];
    }

    // Revalido al aplicar lo que el borrador verificó al crear: el producto y
    // los almacenes deben seguir activos y sin borrado, porque entre la
    // creación y la confirmación pudieron desactivarse (RF-074, RN-INV-09).
    /**
     * @throws ValidationException
     */
    private function revalidarActivos(InventoryDocument $documento): void
    {
        $this->almacenActivo((int) $documento->origin_warehouse_id, 'origin_warehouse_id');

        if ($documento->destination_warehouse_id !== null) {
            $this->almacenActivo((int) $documento->destination_warehouse_id, 'destination_warehouse_id');
        }

        foreach ($documento->lines as $indice => $linea) {
            $producto = Product::query()->withTrashed()->find((int) $linea->product_id);

            if ($producto === null || $producto->trashed() || ! $producto->is_active) {
                throw ValidationException::withMessages([
                    "lines.{$indice}.product_id" => ['El producto no está activo y no admite movimientos nuevos.'],
                ]);
            }
        }
    }

    // Rechazo en la confirmación la cantidad fraccionaria cuya unidad no
    // admite decimales (AC-014/015): el borrador la acepta en forma, pero el
    // fondo solo se decide al aplicar, cuando la unidad sigue siendo la
    // vigente.
    /**
     * @throws ValidationException
     */
    private function validarDecimalesPorUnidad(InventoryDocument $documento): void
    {
        $documento->loadMissing('lines.product.unitOfMeasure');

        foreach ($documento->lines as $indice => $linea) {
            $unidad = $linea->product?->unitOfMeasure;

            if ($unidad === null || (bool) $unidad->allows_decimals) {
                continue;
            }

            $cantidad = (string) $linea->quantity;

            if (bccomp($cantidad, bcadd($cantidad, '0', 0), 3) !== 0) {
                throw ValidationException::withMessages([
                    "lines.{$indice}.quantity" => ['La unidad de medida no admite decimales.'],
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertTransferenciaDistinta(InventoryDocument $documento): void
    {
        if ($documento->type === InventoryDocumentType::Transferencia->value
            && (int) $documento->origin_warehouse_id === (int) $documento->destination_warehouse_id
        ) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => ['El almacén de destino debe ser distinto del origen.'],
            ]);
        }
    }

    // Resuelvo la petición para la auditoría: en HTTP uso la real (con su
    // usuario, IP y agente); en llamadas de servicio sin petición construyo
    // una con el autor como usuario, porque la bitácora siempre exige
    // responsable (RF-106/RF-107) y el AuditLogger lo lee de la petición.
    private function solicitudParaAuditoria(?Request $solicitud, int $autorId): Request
    {
        if ($solicitud !== null) {
            return $solicitud;
        }

        $autor = User::query()->find($autorId);
        $anonima = new Request;
        $anonima->setUserResolver(fn (): ?User => $autor);

        return $anonima;
    }

    // Verifico el fondo que el Form Request no puede: actividad de almacenes y
    // productos al momento de operar (RN-INV-09), destino solo y distinto en
    // transferencias (RN-INV-03), motivo solo y obligatorio en ajustes
    // (RN-INV-08), dirección solo en ajustes (el signo vive por línea, §4.3)
    // y cantidades positivas con producto sin duplicar (RN-INV-01).
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertDominio(array $data): void
    {
        $tipo = (string) $data['type'];
        $esTransferencia = $tipo === InventoryDocumentType::Transferencia->value;
        $esAjuste = $tipo === InventoryDocumentType::Ajuste->value;

        $origen = $this->almacenActivo((int) $data['origin_warehouse_id'], 'origin_warehouse_id');

        $destinoId = $data['destination_warehouse_id'] ?? null;

        if ($esTransferencia) {
            if ($destinoId === null) {
                throw ValidationException::withMessages([
                    'destination_warehouse_id' => ['La transferencia exige almacén de destino.'],
                ]);
            }

            $destino = $this->almacenActivo((int) $destinoId, 'destination_warehouse_id');

            if ($origen->id === $destino->id) {
                throw ValidationException::withMessages([
                    'destination_warehouse_id' => ['El almacén de destino debe ser distinto del origen.'],
                ]);
            }
        } elseif ($destinoId !== null) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => ['Solo la transferencia admite almacén de destino.'],
            ]);
        }

        $motivo = $data['reason_code'] ?? null;

        if ($esAjuste && $motivo === null) {
            throw ValidationException::withMessages([
                'reason_code' => ['El ajuste exige un motivo.'],
            ]);
        }

        if (! $esAjuste && $motivo !== null) {
            throw ValidationException::withMessages([
                'reason_code' => ['Solo el ajuste admite motivo.'],
            ]);
        }

        $vistos = [];

        foreach ($data['lines'] as $indice => $linea) {
            $productoId = (int) $linea['product_id'];

            if (in_array($productoId, $vistos, true)) {
                throw ValidationException::withMessages([
                    "lines.{$indice}.product_id" => ['El producto no puede repetirse en las líneas.'],
                ]);
            }

            $vistos[] = $productoId;

            $producto = Product::query()->withTrashed()->find($productoId);

            if ($producto === null || $producto->trashed() || ! $producto->is_active) {
                throw ValidationException::withMessages([
                    "lines.{$indice}.product_id" => ['El producto no está activo y no admite documentos nuevos.'],
                ]);
            }

            // La cantidad viaja como cadena y la comparo con bccomp, nunca con
            // flotante: el proyecto lo prohíbe para cantidades sin excepciones
            // y el borde del cero exacto debe decidirse sin redondeo.
            if (! function_exists('bccomp')) {
                throw new \RuntimeException('Falta la extensión bcmath, obligatoria para comparar cantidades exactas.');
            }

            if (bccomp((string) $linea['quantity'], '0', 3) <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$indice}.quantity" => ['La cantidad debe ser mayor que cero.'],
                ]);
            }

            $costo = (string) ($linea['unit_cost'] ?? '0');

            if (bccomp($costo, '0', 6) < 0) {
                throw ValidationException::withMessages([
                    "lines.{$indice}.unit_cost" => ['El costo no puede ser negativo.'],
                ]);
            }

            $direccion = $linea['direction'] ?? null;

            if (! $esAjuste && $direccion !== null) {
                throw ValidationException::withMessages([
                    "lines.{$indice}.direction" => ['Solo el ajuste admite dirección por línea.'],
                ]);
            }

            if ($esAjuste && $direccion !== null && ! in_array($direccion, ['entrada', 'salida'], true)) {
                throw ValidationException::withMessages([
                    "lines.{$indice}.direction" => ['La dirección de la línea no es válida.'],
                ]);
            }
        }
    }

    // El Form Request ya exige que el almacén exista; aquí verifico que siga
    // activo y sin borrado lógico, porque entre la validación de forma y la
    // inserción el almacén pudo desactivarse y la FK con RESTRICT no distingue
    // un dato inválido de una carrera (RN-INV-09).
    /**
     * @throws ValidationException
     */
    private function almacenActivo(int $warehouseId, string $campo): Warehouse
    {
        $almacen = Warehouse::query()->withTrashed()->find($warehouseId);

        if ($almacen === null || $almacen->trashed() || ! $almacen->is_active) {
            throw ValidationException::withMessages([
                $campo => ['El almacén no está activo y no admite documentos nuevos.'],
            ]);
        }

        return $almacen;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insertarBorrador(array $data, string $idempotencyKey, int $authorId): InventoryDocument
    {
        // El número legible deriva del id, que solo existe tras insertar, y la
        // columna no admite nulos: inserto con un provisional único derivado
        // del uuid y lo sustituyo en la misma transacción, así que ningún
        // lector concurrente ve el provisional fuera de ella.
        $documento = InventoryDocument::create([
            'uuid' => (string) Str::uuid(),
            'doc_number' => 'TMP-'.(string) Str::uuid(),
            'type' => $data['type'],
            'status' => InventoryDocumentStatus::Borrador->value,
            'origin_warehouse_id' => $data['origin_warehouse_id'],
            'destination_warehouse_id' => $data['destination_warehouse_id'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'reason_code' => $data['reason_code'] ?? null,
            'created_by' => $authorId,
        ]);

        $documento->update([
            'doc_number' => $this->numeroLegible($data['type'], (int) $documento->id),
        ]);

        $this->insertarLineas($documento, $data['lines'], $data['type']);

        return $documento->refresh()->load('lines');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function insertarLineas(InventoryDocument $documento, array $lineas, string $tipo): void
    {
        if (! function_exists('bcmul')) {
            throw new \RuntimeException('Falta la extensión bcmath, obligatoria para calcular totales exactos.');
        }

        foreach ($lineas as $linea) {
            $cantidad = (string) $linea['quantity'];
            $costo = (string) ($linea['unit_cost'] ?? '0');

            // El total se calcula en el servidor con redondeo mitad hacia
            // arriba a dos decimales: bcmul trunca en lugar de redondear, así
            // que calculo con tres decimales, sumo media centésima y trunco a
            // dos (p. ej. 2.500 x 10.250000 = 25.625 -> 25.63). El cliente
            // nunca decide montos (§8.2 del dominio).
            $documento->lines()->create([
                'product_id' => $linea['product_id'],
                'quantity' => $cantidad,
                'unit_cost' => $costo,
                'line_total' => bcadd(bcmul($cantidad, $costo, 3), '0.005', 2),
                // La dirección solo vive en el ajuste; en los demás tipos la
                // fija el tipo de documento y persistirla duplicaría la
                // fuente de verdad (RN-INV-01).
                'direction' => $tipo === InventoryDocumentType::Ajuste->value
                    ? ($linea['direction'] ?? 'entrada')
                    : null,
            ]);
        }
    }

    // Derivo el número legible del tipo y del id interno (p. ej.
    // SAL-2026-000123): es consecutivo por construcción del autoincremento y
    // único por la restricción, sin tabla de secuencias que mantener.
    private function numeroLegible(string $tipo, int $id): string
    {
        $prefijo = InventoryDocumentType::from($tipo)->docPrefix();

        return sprintf('%s-%s-%06d', $prefijo, gmdate('Y'), $id);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws IdempotencyConflictException
     */
    private function replayOrConflict(InventoryDocument $existente, array $data): InventoryDocument
    {
        // Un reintento del mismo cuerpo devuelve el original sin efectos
        // nuevos; un cuerpo distinto con la misma clave es un conflicto
        // (C-05), no un segundo documento.
        if (! $this->mismoContenido($existente, $data)) {
            throw new IdempotencyConflictException;
        }

        return $existente;
    }

    // Comparo el contenido canónico (cabecera más líneas ordenadas por
    // producto con decimales normalizados a su escala) en lugar del JSON
    // crudo: así el orden de las líneas o un "12.5" frente a "12.500" no
    // convierten un reintento legítimo en un falso conflicto. La dirección
    // del ajuste se normaliza igual (ausente equivale a `entrada`).
    /**
     * @param  array<string, mixed>  $data
     */
    private function mismoContenido(InventoryDocument $existente, array $data): bool
    {
        $esperado = [
            'type' => $existente->type,
            'origin_warehouse_id' => (int) $existente->origin_warehouse_id,
            'destination_warehouse_id' => $existente->destination_warehouse_id === null
                ? null
                : (int) $existente->destination_warehouse_id,
            'reason_code' => $existente->reason_code,
            'reference' => $existente->reference,
            'notes' => $existente->notes,
            'lines' => $existente->lines
                ->map(fn ($linea): array => [
                    'product_id' => (int) $linea->product_id,
                    'quantity' => bcadd((string) $linea->quantity, '0', 3),
                    'unit_cost' => bcadd((string) $linea->unit_cost, '0', 6),
                    'direction' => $existente->type === InventoryDocumentType::Ajuste->value
                        ? ($linea->direction ?? 'entrada')
                        : null,
                ])
                ->sortBy('product_id')
                ->values()
                ->all(),
        ];

        $recibido = [
            'type' => $data['type'],
            'origin_warehouse_id' => (int) $data['origin_warehouse_id'],
            'destination_warehouse_id' => isset($data['destination_warehouse_id'])
                ? (int) $data['destination_warehouse_id']
                : null,
            'reason_code' => $data['reason_code'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'lines' => collect($data['lines'])
                ->map(fn ($linea): array => [
                    'product_id' => (int) $linea['product_id'],
                    'quantity' => bcadd((string) $linea['quantity'], '0', 3),
                    'unit_cost' => bcadd((string) ($linea['unit_cost'] ?? '0'), '0', 6),
                    'direction' => $data['type'] === InventoryDocumentType::Ajuste->value
                        ? ($linea['direction'] ?? 'entrada')
                        : null,
                ])
                ->sortBy('product_id')
                ->values()
                ->all(),
        ];

        return $esperado === $recibido;
    }

    // Distingo el choque de la clave de idempotencia de cualquier otra UNIQUE
    // (uuid o doc_number, imposibles en la práctica): solo la primera se
    // resuelve releyendo, las demás se relanzan porque indican un fallo real.
    private function esConflictoDeIdempotencia(QueryException $e): bool
    {
        $codigo = (string) ($e->errorInfo[1] ?? '');

        return $codigo === '1062'
            && str_contains($e->getMessage(), 'uq_inventory_documents_idempotency_key');
    }

    // Distingo el choque de la fila de saldo de cualquier otra UNIQUE: solo la
    // del par producto-almacén se resuelve releyendo (§6.3), las demás indican
    // un fallo real.
    private function esConflictoDeSaldo(QueryException $e): bool
    {
        $codigo = (string) ($e->errorInfo[1] ?? '');

        return $codigo === '1062'
            && str_contains($e->getMessage(), 'uq_inventories_product_warehouse');
    }
}
