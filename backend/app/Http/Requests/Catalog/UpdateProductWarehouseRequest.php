<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

// Valido solo la forma de la configuración por almacén: las cantidades viajan
// como cadena decimal (§3) y la coherencia min/max la verifica el controlador
// contra los valores resultantes, no solo contra los enviados.
class UpdateProductWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'min_stock' => ['sometimes', 'string', 'decimal:0,3', 'gte:0'],
            'max_stock' => ['sometimes', 'nullable', 'string', 'decimal:0,3', 'gte:0'],
            'reorder_point' => ['sometimes', 'nullable', 'string', 'decimal:0,3', 'gte:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'min_stock.string' => 'El stock mínimo debe enviarse como cadena decimal.',
            'min_stock.decimal' => 'El stock mínimo no es válido.',
            'min_stock.gte' => 'El stock mínimo no puede ser negativo.',
            'max_stock.string' => 'El stock máximo debe enviarse como cadena decimal.',
            'max_stock.decimal' => 'El stock máximo no es válido.',
            'max_stock.gte' => 'El stock máximo no puede ser negativo.',
            'reorder_point.string' => 'El punto de reorden debe enviarse como cadena decimal.',
            'reorder_point.decimal' => 'El punto de reorden no es válido.',
            'reorder_point.gte' => 'El punto de reorden no puede ser negativo.',
        ];
    }
}
