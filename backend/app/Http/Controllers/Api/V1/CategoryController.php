<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Errors\ApiErrorResponse;
use App\Http\Requests\Catalog\IndexCategoryRequest;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\CategoryService;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Mantengo el controlador fino: valida en el Form Request, verifica las reglas
// del árbol en CategoryService y deja constancia en AuditLogger, sin reglas de
// negocio en la capa HTTP (§2 de backend-architecture.md).
class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly ProductService $products,
        private readonly AuditLogger $audit,
    ) {}

    public function index(IndexCategoryRequest $request): AnonymousResourceCollection
    {
        $query = Category::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->input('parent_id'));
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where('name', 'like', "%{$term}%");
        }

        // El orden ya viene validado contra la lista cerrada del §7.5, así que
        // puedo interpolarlo como columna sin riesgo de inyección.
        $sort = (string) $request->input('sort', 'created_at');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $query->orderBy($column, $descending ? 'desc' : 'asc')
            ->orderBy('categories.id');

        $perPage = (int) $request->input('per_page', 15);

        return CategoryResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        // Verifico el tope de profundidad antes de insertar porque un padre en
        // el nivel 3 no admite más hijas (el árbol se acota a 3 niveles).
        $this->categories->assertValidParent(null, $request->input('parent_id'));

        $category = Category::create(array_merge($request->validated(), [
            'is_active' => true,
        ]));

        // Dejo constancia con los valores nuevos porque es un alta: no hay
        // valores viejos que comparar y el responsable viaja en la petición.
        $this->audit->record('crear_categoria', 'category', (int) $category->id, null, $category->toArray(), $request);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        // Cargo las hijas y el conteo de productos solo en el detalle porque
        // el contrato los exige aquí y el listado debe mantenerse ligero.
        $category->load('children');
        $category->loadCount('products');

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        if ($request->has('parent_id')) {
            // Verifico ciclos y profundidad antes de mover el nodo porque un
            // traslado inválido dejaría el árbol inconsistente.
            $this->categories->assertValidParent($category, $request->input('parent_id'));
        }

        $oldValues = $category->toArray();

        $category->update($request->validated());

        // Registro el antes y el después porque la edición necesita ambos lados
        // para reconstruir qué cambió y quién lo cambió.
        $this->audit->record('actualizar_categoria', 'category', (int) $category->id, $oldValues, $category->refresh()->toArray(), $request);

        return new CategoryResource($category);
    }

    public function deactivate(Request $request, Category $category): CategoryResource|JsonResponse
    {
        if (! $category->is_active) {
            return ApiErrorResponse::make(
                'ESTADO_INVALIDO',
                'La categoría ya se encuentra desactivada.',
                409
            );
        }

        // Bloqueo la desactivación con hijas activas porque RF-030 exige no
        // dejar ramas huérfanas en uso.
        if ($this->categories->hasActiveChildren($category)) {
            return ApiErrorResponse::make(
                'ESTADO_INVALIDO',
                'La categoría tiene subcategorías activas y no puede desactivarse.',
                409,
                [['field' => 'parent_id', 'code' => 'ESTADO_INVALIDO', 'message' => 'Retire o desactive primero las subcategorías.']]
            );
        }

        // Bloqueo la desactivación con productos activos porque el contrato
        // del §7.5 la rechaza con 409: la comprobación vive en ProductService
        // porque la tabla products pertenece a la Unidad 2 (cierre pendiente
        // de la Unidad 1).
        if ($this->products->categoryHasActiveProducts($category)) {
            return ApiErrorResponse::make(
                'ESTADO_INVALIDO',
                'La categoría tiene productos activos y no puede desactivarse.',
                409,
                [['field' => 'category_id', 'code' => 'ESTADO_INVALIDO', 'message' => 'Retire o desactive primero los productos.']]
            );
        }

        $oldValues = $category->toArray();

        // Desactivo de forma lógica y conservo el registro, porque el §4 exige
        // preservar la historia: nunca borro una categoría.
        $category->update(['is_active' => false]);

        $this->audit->record('desactivar_categoria', 'category', (int) $category->id, $oldValues, $category->refresh()->toArray(), $request);

        return new CategoryResource($category);
    }
}
