<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\PermissionSlug;
use App\Exceptions\OutsideWarehouseScopeException;
use App\Http\Controllers\Controller;
use App\Http\Errors\ApiErrorResponse;
use App\Http\Requests\Inventory\IndexInventoryDocumentRequest;
use App\Http\Requests\Inventory\ReverseInventoryDocumentRequest;
use App\Http\Requests\Inventory\StoreInventoryDocumentRequest;
use App\Http\Requests\Inventory\UpdateInventoryDocumentRequest;
use App\Http\Resources\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\InventoryDocumentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Mantengo el controlador fino: valida en el Form Request, exige la clave de
// idempotencia en la creación, resuelve el alcance por almacén en la consulta
// con accessibleWarehouseIds y deja constancia en AuditLogger. Las reglas de
// fondo viven en InventoryDocumentService, nunca aquí (§5.2 del dominio).
class InventoryDocumentController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $documentos,
        private readonly AuditLogger $audit,
    ) {}

    public function index(IndexInventoryDocumentRequest $request): AnonymousResourceCollection
    {
        $query = InventoryDocument::query()->with('lines');

        // Expongo solo los documentos que tocan mi alcance: el origen o el
        // destino están entre mis almacenes. El alcance se resuelve en la
        // consulta, nunca filtrando en PHP.
        $alcance = $request->user()->accessibleWarehouseIds();

        $query->where(function ($documentos) use ($alcance): void {
            $documentos->whereIn('origin_warehouse_id', $alcance)
                ->orWhereIn('destination_warehouse_id', $alcance);
        });

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('origin_warehouse_id')) {
            $query->where('origin_warehouse_id', $request->input('origin_warehouse_id'));
        }

        if ($request->has('destination_warehouse_id')) {
            $query->where('destination_warehouse_id', $request->input('destination_warehouse_id'));
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        // El orden ya viene validado contra la lista cerrada del §7.8, así que
        // puedo interpolarlo como columna sin riesgo de inyección.
        $sort = (string) $request->input('sort', '-created_at');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $query->orderBy($column, $descending ? 'desc' : 'asc')
            ->orderBy('inventory_documents.id');

        $perPage = (int) $request->input('per_page', 15);

        return InventoryDocumentResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Request $request, InventoryDocument $documento): InventoryDocumentResource
    {
        $this->assertAlcance($request, (int) $documento->origin_warehouse_id, $documento->destination_warehouse_id);

        $documento->load('lines');

        return new InventoryDocumentResource($documento);
    }

    public function store(StoreInventoryDocumentRequest $request): JsonResponse
    {
        // La clave de idempotencia es obligatoria en la creación (C-05): sin
        // ella no puedo garantizar el no-duplicado ante reintentos de red.
        $clave = $request->header('Idempotency-Key');

        if (! is_string($clave) || trim($clave) === '') {
            return ApiErrorResponse::make(
                'VALIDACION',
                'Los datos enviados no son válidos.',
                422,
                [[
                    'field' => 'Idempotency-Key',
                    'code' => 'VALIDACION',
                    'message' => 'La cabecera Idempotency-Key es obligatoria.',
                ]]
            );
        }

        $datos = $request->validated();

        // La confirmación inmediata aplica efectos sobre existencias, así que
        // exige además el permiso de confirmación: sin esta guarda, el atajo
        // confirm:true evadiría el permiso de la ruta /confirm (C-02).
        if ((bool) ($datos['confirm'] ?? false) && ! $request->user()->hasPermission(PermissionSlug::InventoryConfirm->value)) {
            throw new AuthorizationException('SIN_PERMISO');
        }

        // Exijo alcance sobre el origen y, en transferencias, también sobre el
        // destino: tener el permiso no basta si el almacén no está en mi
        // warehouse_user (403 FUERA_DE_ALCANCE fuera de alcance).
        $this->assertAlcance(
            $request,
            (int) $datos['origin_warehouse_id'],
            $datos['destination_warehouse_id'] ?? null,
            true
        );

        $resultado = $this->documentos->create(
            $datos,
            trim($clave),
            (int) $request->user()->id,
            (bool) ($datos['confirm'] ?? false),
            $request,
        );

        /** @var InventoryDocument $documento */
        $documento = $resultado['document'];
        $documento->load('lines');

        // Dejo constancia solo cuando creo: el reintento idempotente no es una
        // operación nueva y no debe ensuciar la bitácora con un duplicado. La
        // confirmación inmediata ya audita creación y confirmación dentro de
        // su transacción, así que aquí no repito ninguna de las dos.
        if (! $resultado['replayed'] && ! $resultado['confirmed']) {
            $this->audit->record('crear_documento', 'inventory_document', (int) $documento->id, null, $documento->toArray(), $request);
        }

        if ($resultado['confirmed']) {
            $documento->load('movements');
        }

        // El reintento con el mismo cuerpo devuelve el original con 200 y la
        // creación nueva con 201: así el cliente distingue si su petición fue
        // la que ganó la carrera de la red.
        return (new InventoryDocumentResource($documento))->response()
            ->setStatusCode($resultado['replayed'] ? 200 : 201);
    }

    public function update(UpdateInventoryDocumentRequest $request, InventoryDocument $documento): InventoryDocumentResource
    {
        $this->assertAlcance($request, (int) $documento->origin_warehouse_id, $documento->destination_warehouse_id);

        $datos = $request->validated();

        // El borrador puede moverse de almacén con la edición, así que exijo
        // alcance también sobre el destino entrante: si no lo tiene, ni
        // siquiera llega al servicio.
        $this->assertAlcance(
            $request,
            (int) $datos['origin_warehouse_id'],
            $datos['destination_warehouse_id'] ?? null,
            true
        );

        $valoresViejos = $documento->load('lines')->toArray();

        $documento = $this->documentos->update($documento, $datos);

        $this->audit->record('actualizar_documento', 'inventory_document', (int) $documento->id, $valoresViejos, $documento->toArray(), $request);

        return new InventoryDocumentResource($documento);
    }

    public function cancel(Request $request, InventoryDocument $documento): InventoryDocumentResource
    {
        $this->assertAlcance($request, (int) $documento->origin_warehouse_id, $documento->destination_warehouse_id);

        $valoresViejos = $documento->load('lines')->toArray();

        // Solo el borrador se descarta: el servicio rechaza cualquier otro
        // estado con 409 ESTADO_INVALIDO y ningún efecto parcial.
        $documento = $this->documentos->discard($documento);

        $this->audit->record('descartar_documento', 'inventory_document', (int) $documento->id, $valoresViejos, $documento->toArray(), $request);

        return new InventoryDocumentResource($documento);
    }

    // Confirmo el borrador por el servicio transaccional: la clave de
    // idempotencia es obligatoria (§7.8) y el reintento con la misma clave
    // sobre el documento ya confirmado devuelve el original sin efectos
    // nuevos. La auditoría vive dentro de la transacción del servicio, así
    // que aquí no registro nada.
    public function confirm(Request $request, InventoryDocument $documento): InventoryDocumentResource|JsonResponse
    {
        $clave = $request->header('Idempotency-Key');

        if (! is_string($clave) || trim($clave) === '') {
            return ApiErrorResponse::make(
                'VALIDACION',
                'Los datos enviados no son válidos.',
                422,
                [[
                    'field' => 'Idempotency-Key',
                    'code' => 'VALIDACION',
                    'message' => 'La cabecera Idempotency-Key es obligatoria.',
                ]]
            );
        }

        // En escritura exijo todos los almacenes implicados: confirmar hacia
        // un destino ajeno sería una evasión del alcance.
        $this->assertAlcance($request, (int) $documento->origin_warehouse_id, $documento->destination_warehouse_id, true);

        $resultado = $this->documentos->confirm(
            $documento,
            (int) $request->user()->id,
            trim($clave),
            $request,
        );

        /** @var InventoryDocument $confirmado */
        $confirmado = $resultado['document'];

        return new InventoryDocumentResource($confirmado->load('lines', 'movements'));
    }

    // Revierto el documento confirmado generando sus movimientos
    // compensatorios: el servicio crea la reversión, aplica sus efectos,
    // marca el original y audita, todo en una transacción. No pido clave de
    // idempotencia porque la reversión deriva la suya del original
    // (`REV-<uuid>`) y el reintento devuelve el par sin efectos nuevos.
    public function reverse(ReverseInventoryDocumentRequest $request, InventoryDocument $documento): InventoryDocumentResource
    {
        $this->assertAlcance($request, (int) $documento->origin_warehouse_id, $documento->destination_warehouse_id, true);

        $resultado = $this->documentos->reverse(
            $documento,
            (int) $request->user()->id,
            (string) $request->validated()['reason'],
            $request,
        );

        /** @var InventoryDocument $original */
        $original = $resultado['original'];
        $original->load('lines', 'movements');

        if ($resultado['reversal'] !== null) {
            $original->setRelation('reversion', $resultado['reversal']);
        }

        return new InventoryDocumentResource($original);
    }

    // Verifico el alcance sobre cada almacén implicado: en lectura basta uno
    // de los dos (el documento toca mi almacén por cualquier pata), pero en
    // escritura exijo todos, porque crear o mover un documento hacia un
    // almacén ajeno sería una evasión del alcance (C-02, IDOR). Un destino
    // nulo no concede nada: solo cuenta cuando el documento trae destino y
    // el usuario lo alcanza.
    private function assertAlcance(Request $request, int $origenId, mixed $destinoId, bool $exigirTodos = false): void
    {
        $usuario = $request->user();
        $puedeOrigen = $usuario->canAccessWarehouse($origenId);
        $tieneDestino = $destinoId !== null;
        $puedeDestino = $tieneDestino && $usuario->canAccessWarehouse((int) $destinoId);

        $autorizado = $exigirTodos
            ? ($puedeOrigen && (! $tieneDestino || $puedeDestino))
            : ($puedeOrigen || $puedeDestino);

        if (! $autorizado) {
            throw new OutsideWarehouseScopeException;
        }
    }
}
