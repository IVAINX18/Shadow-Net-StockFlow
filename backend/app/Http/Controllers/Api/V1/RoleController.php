<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Mantengo el controlador fino: valida en el Form Request, autoriza con la
// Policy y delega la forma JSON en el Resource. Los roles son globales y no
// llevan alcance por almacén; no existe borrado de roles en el contrato.
class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexRoleRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        // Precargo los permisos porque el contrato (§7.3) exige cada rol con
        // los suyos; viajan como detalle mínimo en RoleResource.
        $query = Role::query()->with('permissions:id,name,slug,module');

        // El orden ya viene validado contra la lista cerrada del §4, así que
        // puedo interpolarlo como columna sin riesgo de inyección.
        $sort = (string) $request->input('sort', 'created_at');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $query->orderBy('roles.'.$column, $descending ? 'desc' : 'asc')
            ->orderBy('roles.id');

        $perPage = (int) $request->input('per_page', 15);

        return RoleResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $datos = $request->validated();

        $role = Role::create(collect($datos)->only(['name', 'slug', 'description'])->all());

        if (! empty($datos['permission_ids'])) {
            $role->permissions()->sync($datos['permission_ids']);
        }

        $role->load('permissions:id,name,slug,module');

        return (new RoleResource($role))->response()->setStatusCode(201);
    }

    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        $role->load('permissions:id,name,slug,module');

        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $this->authorize('update', $role);

        $datos = $request->validated();

        $role->update(collect($datos)->only(['name', 'description'])->all());

        // Aplico permisos con `sync` porque el PUT sustituye la asignación
        // completa del rol; enviar la lista vacía los retira todos.
        if (array_key_exists('permission_ids', $datos)) {
            $role->permissions()->sync($datos['permission_ids'] ?? []);
        }

        $role->load('permissions:id,name,slug,module');

        return new RoleResource($role->refresh());
    }
}
