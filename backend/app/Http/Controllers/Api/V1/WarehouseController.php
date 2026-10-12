<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Errors\ApiErrorResponse;
use App\Http\Requests\Warehouse\AttachWarehouseUsersRequest;
use App\Http\Requests\Warehouse\IndexWarehouseRequest;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\UserSummaryResource;
use App\Http\Resources\WarehouseResource;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        // Creo siempre activo porque RF-020 reserva la inactividad para la
        // desactivación posterior; el responsable no otorga acceso automático
        // al almacén, que viaja por warehouse_user (RF-023).
        $warehouse = Warehouse::create(array_merge($request->validated(), [
            'is_active' => true,
        ]));

        $warehouse->load('manager:id,name,email');

        return (new WarehouseResource($warehouse))->response()->setStatusCode(201);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('update', $warehouse);

        $warehouse->update($request->validated());

        $warehouse->load('manager:id,name,email');

        return new WarehouseResource($warehouse->refresh());
    }

    public function deactivate(Warehouse $warehouse): WarehouseResource|JsonResponse
    {
        $this->authorize('deactivate', $warehouse);

        // No compruebo existencias aquí porque la tabla `inventories` llega en
        // la Fase 3 y no existe nada que consultar todavía; esa guarda se añade
        // entonces sin tocar este flujo. RF-020 solo me exige hoy no borrar.
        if (! $warehouse->is_active) {
            return ApiErrorResponse::make(
                'ESTADO_INVALIDO',
                'El almacén ya se encuentra desactivado.',
                409
            );
        }

        // Desactivo de forma lógica y conservo el registro, porque RF-020 exige
        // preservar el kardex: nunca borro ni marco `deleted_at` en esta ruta.
        $warehouse->update(['is_active' => false]);

        return new WarehouseResource($warehouse->refresh());
    }

    public function activate(Warehouse $warehouse): WarehouseResource|JsonResponse
    {
        $this->authorize('activate', $warehouse);

        // La desactivación no tenía inverso en el contrato y un almacén
        // desactivado por error quedaba sin retorno; esta acción lo completa
        // con la misma guarda de estado que su inversa.
        if ($warehouse->is_active) {
            return ApiErrorResponse::make(
                'ESTADO_INVALIDO',
                'El almacén ya se encuentra activo.',
                409
            );
        }

        $warehouse->update(['is_active' => true]);

        return new WarehouseResource($warehouse->refresh());
    }

    public function users(Warehouse $warehouse): AnonymousResourceCollection
    {
        $this->authorize('view', $warehouse);

        $users = $warehouse->users()->with('roles:id,name,slug,description')->paginate(15)->withQueryString();

        return UserSummaryResource::collection($users);
    }

    /**
     * @return AnonymousResourceCollection<UserSummaryResource>
     */
    public function attachUsers(AttachWarehouseUsersRequest $request, Warehouse $warehouse): AnonymousResourceCollection
    {
        $this->authorize('manageUsers', $warehouse);

        // Adjunto sin retirar a los ya autorizados, porque revocar acceso en
        // silencio violaría RF-023; la retirada tiene su propio endpoint.
        $warehouse->users()->syncWithoutDetaching($request->validated()['user_ids']);

        $autorizados = $warehouse->users()->with('roles:id,name,slug,description')->orderBy('users.id')->get();

        return UserSummaryResource::collection($autorizados);
    }

    public function detachUser(Warehouse $warehouse, User $user): Response
    {
        $this->authorize('manageUsers', $warehouse);

        // Retirar a quien no estaba autorizado sigue en 204, porque la
        // operación es idempotente: el estado final es el mismo.
        $warehouse->users()->detach($user->id);

        return response()->noContent();
    }
}
