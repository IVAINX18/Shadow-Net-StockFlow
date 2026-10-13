<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Validation\Rule;

// Reúno aquí las reglas de forma del producto porque la importación de catálogo
// (EP-012) debe aplicar exactamente las mismas que la captura manual (RN-IMP-02):
// si vivieran copiadas en cada Form Request y en el importador, divergirían con
// el tiempo y una regla relajada colaría datos que el formulario rechaza. Los
// Form Request delegan en mí y el importador valida cada fila contra estas
// mismas reglas, así que el cambio de una regla ocurre en un solo sitio.
final class CatalogRules
{
    public const SKU_MAX = 60;

    public const NAME_MAX = 180;

    public const BARCODE_MAX = 80;

    // Valores de verdad que acepto en la columna `activo` del archivo, en
    // español o inglés e insensibles a mayúsculas (documentado en api-design
    // §7.13): la celda vacía equivale a verdadero y cualquier otro texto es
    // inválido en lugar de adivinarse.
    private const ACTIVO_VERDADERO = ['1', 'true', 't', 'si', 'sí', 's', 'yes', 'y', 'verdadero', 'v', 'activo', 'activa'];

    private const ACTIVO_FALSO = ['0', 'false', 'f', 'no', 'n', 'falso', 'falsa', 'inactivo', 'inactiva'];

    /**
     * Reglas de creación: las mismas que antes vivían en StoreProductRequest,
     * movidas aquí sin cambiar ni un literal para no alterar el contrato.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function productRules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:'.self::SKU_MAX, 'unique:products,sku'],
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'description' => ['nullable', 'string'],
            // El código de barras es único solo cuando viene informado: la
            // UNIQUE admite varios NULL en MySQL, igual que el slug de Unidad 1.
            'barcode' => ['nullable', 'string', 'max:'.self::BARCODE_MAX, 'unique:products,barcode'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            // Acepto los montos como cadena decimal y rechazo negativos: viajan
            // como string por el §3 y nunca como flotante.
            'cost_price' => ['sometimes', 'string', 'decimal:0,2', 'gte:0'],
            'sale_price' => ['sometimes', 'string', 'decimal:0,2', 'gte:0'],
            'costing_method' => ['sometimes', 'string', Rule::in(['promedio_ponderado', 'fifo'])],
        ];
    }

    /**
     * Reglas de edición: idénticas a las que vivían en UpdateProductRequest,
     * con la unicidad del código ignorando la fila propia porque reenviar el
     * mismo código del producto no es un duplicado.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function productUpdateRules(mixed $ignoreProductId = null): array
    {
        return [
            'sku' => ['sometimes', 'string', 'max:'.self::SKU_MAX],
            'name' => ['sometimes', 'string', 'max:'.self::NAME_MAX],
            'description' => ['sometimes', 'nullable', 'string'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:'.self::BARCODE_MAX, Rule::unique('products', 'barcode')->ignore($ignoreProductId)],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'unit_of_measure_id' => ['sometimes', 'integer', 'exists:units_of_measure,id'],
            'is_active' => ['sometimes', 'boolean'],
            'cost_price' => ['sometimes', 'string', 'decimal:0,2', 'gte:0'],
            'sale_price' => ['sometimes', 'string', 'decimal:0,2', 'gte:0'],
            'costing_method' => ['sometimes', 'string', Rule::in(['promedio_ponderado', 'fifo'])],
        ];
    }

    /**
     * Mensajes en español compartidos por la captura manual y la importación,
     * para que el mismo fallo suene igual en ambos caminos.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'sku.required' => 'El SKU es obligatorio.',
            'sku.string' => 'El SKU no es válido.',
            'sku.max' => 'El SKU no es válido.',
            'sku.unique' => 'El SKU ya está en uso.',
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre no es válido.',
            'name.max' => 'El nombre no es válido.',
            'barcode.string' => 'El código de barras no es válido.',
            'barcode.max' => 'El código de barras no es válido.',
            'barcode.unique' => 'El código de barras ya está en uso.',
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.integer' => 'La categoría no es válida.',
            'category_id.exists' => 'La categoría no es válida.',
            'unit_of_measure_id.required' => 'La unidad de medida es obligatoria.',
            'unit_of_measure_id.integer' => 'La unidad de medida no es válida.',
            'unit_of_measure_id.exists' => 'La unidad de medida no es válida.',
            'is_active.boolean' => 'El estado no es válido.',
            'cost_price.string' => 'El costo debe enviarse como cadena decimal.',
            'cost_price.decimal' => 'El costo no es válido.',
            'cost_price.gte' => 'El costo no puede ser negativo.',
            'sale_price.string' => 'El precio debe enviarse como cadena decimal.',
            'sale_price.decimal' => 'El precio no es válido.',
            'sale_price.gte' => 'El precio no puede ser negativo.',
            'costing_method.in' => 'El método de costeo no es válido.',
        ];
    }

    // Interpreto la columna `activo` del archivo: devuelvo null cuando el texto
    // no es un valor de verdad reconocible, para que el llamante lo reporte
    // como fila inválida en lugar de adivinar la intención del usuario.
    public static function parseActivo(mixed $value): ?bool
    {
        if ($value === null) {
            return true;
        }

        $texto = mb_strtolower(trim((string) $value), 'UTF-8');

        if ($texto === '') {
            return true;
        }

        if (in_array($texto, self::ACTIVO_VERDADERO, true)) {
            return true;
        }

        if (in_array($texto, self::ACTIVO_FALSO, true)) {
            return false;
        }

        return null;
    }
}
