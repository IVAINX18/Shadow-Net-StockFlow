<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Errors\ApiErrorResponse;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Mantengo el controlador fino: valida en el Form Request, autoriza con la
// Policy y delega la forma JSON en el Resource, sin reglas de negocio (§2 de
// backend-architecture.md). Las cuentas son globales: no hay alcance por
// almacén aquí, solo permisos RBAC.
class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        // Precargo solo los roles porque el listado usa UserSummaryResource, el
        // contrato ligero para terceros: UserResource (permisos y almacenes
        // agregados) es el del propio autenticado y aquí cargaría de más.
        $query = User::query()->with('roles:id,name,slug,description');

        if ($request->has('is_active')) {
            $query->where('users.is_active', $request->boolean('is_active'));
        }

        if ($request->filled('role')) {
            $slug = (string) $request->input('role');
            $query->whereHas('roles', static function ($sub) use ($slug): void {
                $sub->where('slug', $slug);
            });
        }

        if ($request->filled('warehouse_id')) {
            $almacenId = (int) $request->input('warehouse_id');
            $query->whereHas('warehouses', static function ($sub) use ($almacenId): void {
                $sub->where('warehouses.id', $almacenId);
            });
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($sub) use ($term): void {
                $sub->where('users.name', 'like', "%{$term}%")
                    ->orWhere('users.email', 'like', "%{$term}%");
            });
        }

        // El orden ya viene validado contra la lista cerrada del §4, así que
        // puedo interpolarlo como columna sin riesgo de inyección.
        $sort = (string) $request->input('sort', 'created_at');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $query->orderBy('users.'.$column, $descending ? 'desc' : 'asc')
            ->orderBy('users.id');

        $perPage = (int) $request->input('per_page', 15);

        return UserSummaryResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $datos = $request->validated();

        // Asigno por constructor solo los campos de `fillable` e `is_active`
        // por propiedad: no está en masa a propósito y el alta siempre crea
        // cuentas activas (RF-005), sin dejar que el cliente decida el estado.
        $user = new User([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);
        $user->is_active = true;
        $user->save();

        if (! empty($datos['role_ids'])) {
            $user->roles()->sync($datos['role_ids']);
        }

        if (! empty($datos['warehouse_ids'])) {
            $user->warehouses()->sync($datos['warehouse_ids']);
        }

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        // No precargo nada aquí: UserResource ya trae sus `loadMissing` para
        // roles, permisos y almacenes del usuario consultado.
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $datos = $request->validated();

        $user->fill(collect($datos)->only(['name', 'email'])->all());
        $user->save();

        // Aplico roles y almacenes con `sync` porque el PUT es la actualización
        // completa del recurso: lo enviado sustituye lo anterior. El endpoint
        // de autorizados por almacén, en cambio, es acumulativo a propósito.
        if (array_key_exists('role_ids', $datos)) {
            $user->roles()->sync($datos['role_ids'] ?? []);
        }

        if (array_key_exists('warehouse_ids', $datos)) {
            $user->warehouses()->sync($datos['warehouse_ids'] ?? []);
        }

        return new UserResource($user->refresh());
    }

    public function activate(User $user): UserResource|JsonResponse
    {
        $this->authorize('activate', $user);

        if ($user->is_active) {
            return ApiErrorResponse::make(
                'ESTADO_INVALIDO',
                'El usuario ya se encuentra activo.',
                409
            );
        }

        $user->is_active = true;
        $user->save();

        return new UserResource($user->refresh());
    }

    public function deactivate(User $user): UserResource|JsonResponse
    {
        $this->authorize('deactivate', $user);

        if (! $user->is_active) {
            return ApiErrorResponse::make(
                'ESTADO_INVALIDO',
                'El usuario ya se encuentra desactivado.',
                409
            );
        }

        // El contrato (§7.2) prohíbe dejar al sistema sin administradores, así
        // que cuento los activos con el rol `administrador` y bloqueo si este
        // es el último. La cuenta desactivada pierde acceso en la petición
        // siguiente gracias al middleware `account.active`, que ya existe; no
        // duplico esa lógica aquí.
        $esAdministrador = $user->roles()->where('slug', 'administrador')->exists();

        if ($esAdministrador) {
            $otrosAdministradores = User::query()
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->whereHas('roles', static function ($sub): void {
                    $sub->where('slug', 'administrador');
                })
                ->count();

            if ($otrosAdministradores === 0) {
                return ApiErrorResponse::make(
                    'ESTADO_INVALIDO',
                    'No se puede desactivar al último administrador activo.',
                    409
                );
            }
        }

        $user->is_active = false;
        $user->save();

        return new UserResource($user->refresh());
    }
}
