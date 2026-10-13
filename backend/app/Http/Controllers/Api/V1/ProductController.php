<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OutsideWarehouseScopeException;
use App\Http\Controllers\Controller;
use App\Http\Errors\ApiErrorResponse;
use App\Http\Requests\Catalog\IndexProductRequest;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Requests\Catalog\UpdateProductWarehouseRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductWarehouseResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Mantengo el controlador fino: valida en el Form Request, verifica las reglas
// de dominio en ProductService, resuelve el alcance por almacén en la consulta
// con accessibleWarehouseIds y deja constancia en AuditLogger, sin reglas de
// negocio en la capa HTTP (§2 de backend-architecture.md).
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
        private readonly AuditLogger $audit,
    ) {}

    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        $query = Product::query()->with(['category', 'unitOfMeasure']);

        // Expongo solo la configuración de los almacenes que el llamante puede
        // ver: el alcance se resuelve en la consulta, no filtrando en PHP.
        $query->with(['configurations' => function ($configurations) use ($request): void {
            $configurations->whereIn('warehouse_id', $request->user()->accessibleWarehouseIds());
        }]);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('warehouse_id')) {
            $warehouseId = (int) $request->input('warehouse_id');
            $query->whereHas('configurations', function ($configurations) use ($warehouseId): void {
                $configurations->where('warehouse_id', $warehouseId);
            });
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($search) use ($term): void {
                $search->where('sku', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            });
        }

        // El orden ya viene validado contra la lista cerrada del §7.7, así que
        // puedo interpolarlo como columna sin riesgo de inyección.
        $sort = (string) $request->input('sort', 'created_at');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $query->orderBy($column, $descending ? 'desc' : 'asc')
            ->orderBy('products.id');

        $perPage = (int) $request->input('per_page', 15);

        return ProductResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        // Verifico la categoría activa antes de insertar porque RF-035 prohíbe
        // productos nuevos en categorías inactivas (los existentes la conservan).
        $product = $this->products->create($request->validated());

        $product->load(['category', 'unitOfMeasure', 'configurations']);

        // Dejo constancia con los valores nuevos porque es un alta: no hay
        // valores viejos que comparar y el responsable viaja en la petición.
        $this->audit->record('crear_producto', 'product', (int) $product->id, null, $product->toArray(), $request);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        // Cargo la configuración solo de los almacenes visibles porque la
        // ficha resume mínimos por cada almacén autorizado (RF-050): omito
        // cantidades y costo promedio porque la tabla inventories llega con el
        // kardex en una fase posterior y no invento ceros.
        $product->load([
            'category',
            'unitOfMeasure',
            'configurations' => function ($configurations) use ($request): void {
                $configurations->whereIn('warehouse_id', $request->user()->accessibleWarehouseIds());
            },
        ]);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $oldValues = $product->toArray();

        $this->products->update($product, $request->validated());

        $product->load(['category', 'unitOfMeasure', 'configurations']);

        // Registro el antes y el después porque la edición necesita ambos lados
        // para reconstruir qué cambió y quién lo cambió.
        $this->audit->record('actualizar_producto', 'product', (int) $product->id, $oldValues, $product->refresh()->toArray(), $request);

        return new ProductResource($product);
    }

    public function deactivate(Request $request, Product $product): ProductResource|JsonResponse
    {
        if (! $product->is_active) {
            return ApiErrorResponse::make(
                'ESTADO_INVALIDO',
                'El producto ya se encuentra desactivado.',
                409
            );
        }

        $oldValues = $product->toArray();

        // Desactivo de forma lógica y conservo el registro, porque el §4 exige
        // preservar el historial y la valorización: nunca borro un producto.
        // El contrato prevé rechazar con movimientos pendientes de cierre, pero
        // la tabla de movimientos no existe hasta la fase de inventario, así
        // que hoy no hay nada pendiente que detectar; la comprobación se añade
        // cuando esos documentos existan (registrado en api-design.md §7.7).
        $product->update(['is_active' => false]);

        $this->audit->record('desactivar_producto', 'product', (int) $product->id, $oldValues, $product->refresh()->toArray(), $request);

        return new ProductResource($product);
    }

    public function warehouses(Request $request, Product $product): AnonymousResourceCollection
    {
        $configurations = $product->configurations()
            ->whereIn('warehouse_id', $request->user()->accessibleWarehouseIds())
            ->orderBy('warehouse_id')
            ->get();

        return ProductWarehouseResource::collection($configurations);
    }

    public function updateWarehouse(UpdateProductWarehouseRequest $request, Product $product, int $warehouseId): JsonResponse
    {
        $warehouse = Warehouse::query()->find($warehouseId);

        if ($warehouse === null) {
            return ApiErrorResponse::make(
                'NO_ENCONTRADO',
                'El almacén solicitado no existe.',
                404
            );
        }

        // Exijo el almacén dentro del alcance del llamante aunque ya tenga el
        // permiso catalog.update: tener el permiso no basta si el almacén no
        // está en su warehouse_user (§7.7, 403 FUERA_DE_ALCANCE).
        if (! $request->user()->canAccessWarehouse($warehouseId)) {
            throw new OutsideWarehouseScopeException;
        }

        $configuration = $this->products->setWarehouseConfiguration($product, $warehouseId, $request->validated());

        $this->audit->record('configurar_producto_almacen', 'product_warehouse', (int) $configuration->id, null, $configuration->toArray(), $request);

        // Fijo el 200 en ambos casos (creación y actualización) porque el
        // contrato responde la "configuración actualizada": si devolviera el
        // recurso tal cual, Laravel respondería 201 cuando la fila se acaba
        // de crear (wasRecentlyCreated) y 200 cuando ya existía.
        return (new ProductWarehouseResource($configuration))->response()->setStatusCode(200);
    }
}
