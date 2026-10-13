<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use Illuminate\Validation\ValidationException;

// Concentro aquí las reglas de dominio del producto (categoría activa, SKU
// inmutable y coherencia min/max por almacén) porque son reglas de fondo que
// deben aplicarse igual desde cualquier entrada: el Form Request valida la
// forma y yo el fondo, como hace CategoryService con el árbol.
final class ProductService
{
    // Rechazo crear o mover un producto a una categoría inactiva porque RF-035
    // y AC-013 lo exigen: los productos existentes la conservan, pero ningún
    // producto nuevo puede usarla.
    /**
     * @throws ValidationException
     */
    public function assertActiveCategory(int $categoryId): void
    {
        $category = Category::query()->find($categoryId);

        // El Form Request ya exige que la categoría exista; si no la encuentro
        // aquí es una carrera y dejo que la FK con RESTRICT decida en la base.
        if ($category === null) {
            return;
        }

        if (! $category->is_active) {
            throw ValidationException::withMessages([
                'category_id' => ['La categoría indicada no está activa y no admite productos nuevos.'],
            ]);
        }
    }

    // Rechazo cualquier intento de cambiar el SKU porque el contrato del PUT
    // lo declara inmutable (lectura conservadora del §7.7): ni siquiera un
    // valor único distinto se acepta, y responde 422, no 409.
    /**
     * @throws ValidationException
     */
    public function assertSkuImmutable(Product $product, ?string $sku): void
    {
        if ($sku !== null && $sku !== $product->sku) {
            throw ValidationException::withMessages([
                'sku' => ['El SKU no puede modificarse una vez creado el producto.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        $this->assertActiveCategory((int) $data['category_id']);

        $product = Product::create($data);

        // Recargo la fila porque los valores por defecto de la base (método de
        // costeo, precios en cero) no están en el modelo recién creado: sin
        // esto la respuesta 201 expondría null donde la base ya guardó el
        // valor real.
        return $product->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        $this->assertSkuImmutable($product, isset($data['sku']) ? (string) $data['sku'] : null);

        unset($data['sku']);

        if (array_key_exists('category_id', $data)) {
            $this->assertActiveCategory((int) $data['category_id']);
        }

        $product->update($data);

        return $product;
    }

    // Creo la fila de configuración si no existe y la actualizo si ya existe,
    // porque la primera configuración de un producto en un almacén nace con
    // este mismo PUT (§7.7): no hay endpoint separado para el alta inicial.
    // Verifico la coherencia sobre los valores resultantes (no solo sobre los
    // enviados) porque un parcial que deje max por debajo de min rompería la
    // regla `max_stock >= min_stock` igual que enviar ambos incoherentes.
    /**
     * @param  array<string, mixed>  $data
     */
    public function setWarehouseConfiguration(Product $product, int $warehouseId, array $data): ProductWarehouse
    {
        $configuration = ProductWarehouse::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $minimo = array_key_exists('min_stock', $data)
            ? (string) $data['min_stock']
            : ($configuration !== null ? (string) $configuration->min_stock : '0.000');

        $maximo = array_key_exists('max_stock', $data)
            ? ($data['max_stock'] === null ? null : (string) $data['max_stock'])
            : ($configuration !== null && $configuration->max_stock !== null ? (string) $configuration->max_stock : null);

        // Comparo con bccomp y nunca con float porque son decimales de hasta
        // 15 enteros y 3 decimales: el flotante perdería exactitud en el
        // borde y podría aceptar o rechazar un límite válido por redondeo
        // (el proyecto prohíbe el flotante para dinero y cantidades sin
        // excepciones). bcmath es garantía de plataforma (figura en el
        // Dockerfile y en el contenedor), así que si algún día falta lanzo
        // una excepción clara en lugar de degradar a una comparación inexacta.
        // Calculo la comparación solo cuando hay máximo resultante, porque
        // bccomp no admite null y sin máximo no hay nada que verificar.
        if (! function_exists('bccomp')) {
            throw new \RuntimeException('Falta la extensión bcmath, obligatoria para comparar cantidades exactas.');
        }

        $maxMenor = $maximo !== null && bccomp($maximo, $minimo, 3) < 0;

        if ($maxMenor) {
            throw ValidationException::withMessages([
                'max_stock' => ['El stock máximo no puede ser menor que el stock mínimo.'],
            ]);
        }

        if ($configuration === null) {
            return ProductWarehouse::create(array_merge($data, [
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
            ]));
        }

        $configuration->update($data);

        return $configuration;
    }

    // Expongo si la categoría conserva productos activos para que el
    // controlador de categorías responda 409 ESTADO_INVALIDO (RF-030): vive
    // aquí y no en CategoryService porque la tabla products pertenece a esta
    // unidad y CategoryService no debe conocer modelos posteriores.
    public function categoryHasActiveProducts(Category $category): bool
    {
        return Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->exists();
    }
}
