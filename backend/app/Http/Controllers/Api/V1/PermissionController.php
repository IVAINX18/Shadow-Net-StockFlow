<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\IndexPermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Expongo el catálogo de permisos que el cliente necesita para construir el
// formulario de roles (§7.3). Se gobierna con el mismo permiso que los roles
// (`roles.view`) porque no es un recurso administrable por sí mismo.
class PermissionController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexPermissionRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        $query = Permission::query();

        if ($request->filled('module')) {
            $query->where('permissions.module', (string) $request->input('module'));
        }

        // El orden ya viene validado contra la lista cerrada del §4, así que
        // puedo interpolarlo como columna sin riesgo de inyección.
        $sort = (string) $request->input('sort', 'module');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $query->orderBy('permissions.'.$column, $descending ? 'desc' : 'asc')
            ->orderBy('permissions.name')
            ->orderBy('permissions.id');

        $perPage = (int) $request->input('per_page', 15);

        return PermissionResource::collection($query->paginate($perPage)->withQueryString());
    }
}
