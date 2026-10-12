<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\IndexWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Mantengo el controlador fino: valida en el Form Request, autoriza con la
// Policy y delega la forma JSON en el Resource, sin reglas de negocio (§2 de
// backend-architecture.md).
class WarehouseController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexWarehouseRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Warehouse::class);

        $user = $request->user();
        $query = Warehouse::query();

        // Aplico el alcance en la propia consulta y nunca filtrando en PHP
        // después de cargar todo, porque AC-009 exige que la lista salga ya
        // acotada a los almacenes asignados. `warehouses.view_all` salta el
        // filtro y ve todos.
        if (! $user->hasPermission(PermissionSlug::WarehousesViewAll->value)) {
            $query->whereIn('warehouses.id', $user->accessibleWarehouseIds());
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('city')) {
            $query->where('city', $request->string('city')->toString());
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($sub) use ($term): void {
                $sub->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            });
        }

        // El orden ya viene validado contra la lista cerrada del §4, así que
        // puedo interpolarlo como columna sin riesgo de inyección.
        $sort = (string) $request->input('sort', 'created_at');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $query->orderBy($column, $descending ? 'desc' : 'asc')
            ->orderBy('warehouses.id');

        $perPage = (int) $request->input('per_page', 15);

        return WarehouseResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        // La Policy distingue SIN_PERMISO de FUERA_DE_ALCANCE; aquí solo la
        // invoco y dejo que la excepción dedicada se mapee a 403 en bootstrap.
        $this->authorize('view', $warehouse);

        // Cargo el responsable y los autorizados solo en el detalle, no en el
        // listado, para no pagar joins ni provocar N+1.
        $warehouse->load([
            'manager:id,name,email',
            'users:id,name,email',
        ]);

        return new WarehouseResource($warehouse);
    }
}
