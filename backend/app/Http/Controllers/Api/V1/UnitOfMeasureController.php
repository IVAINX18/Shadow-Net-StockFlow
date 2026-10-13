<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\IndexUnitOfMeasureRequest;
use App\Http\Requests\Catalog\StoreUnitOfMeasureRequest;
use App\Http\Resources\UnitOfMeasureResource;
use App\Models\UnitOfMeasure;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\UnitOfMeasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Mantengo el controlador fino: valida en el Form Request, crea en el servicio
// y deja constancia en AuditLogger; el contrato no define edición ni borrado de
// unidades, así que no existen esas acciones (pendiente en api-design.md §7.6).
class UnitOfMeasureController extends Controller
{
    public function __construct(
        private readonly UnitOfMeasureService $units,
        private readonly AuditLogger $audit,
    ) {}

    public function index(IndexUnitOfMeasureRequest $request): AnonymousResourceCollection
    {
        // El orden ya viene validado contra la lista cerrada del §7.6, así que
        // puedo interpolarlo como columna sin riesgo de inyección.
        $sort = (string) $request->input('sort', 'code');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $query = UnitOfMeasure::query()
            ->orderBy($column, $descending ? 'desc' : 'asc')
            ->orderBy('units_of_measure.id');

        $perPage = (int) $request->input('per_page', 15);

        return UnitOfMeasureResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreUnitOfMeasureRequest $request): JsonResponse
    {
        $unit = $this->units->create($request->validated());

        // Dejo constancia con los valores nuevos porque es un alta: no hay
        // valores viejos que comparar y el responsable viaja en la petición.
        $this->audit->record('crear_unidad', 'unit_of_measure', (int) $unit->id, null, $unit->toArray(), $request);

        return (new UnitOfMeasureResource($unit))->response()->setStatusCode(201);
    }

    public function show(UnitOfMeasure $unit): UnitOfMeasureResource
    {
        return new UnitOfMeasureResource($unit);
    }
}
