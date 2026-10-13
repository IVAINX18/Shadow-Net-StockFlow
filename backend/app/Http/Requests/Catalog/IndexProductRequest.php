<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma del listado de productos: los filtros no documentados
// se ignoran por no tener regla, y el `sort` cae en 422 ante cualquier campo
// fuera de la lista cerrada del §7.7.
class IndexProductRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
            'category_id' => ['sometimes', 'integer'],
            'warehouse_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'string', 'max:180'],
            // Acepto cada columna permitida con y sin el prefijo `-` descendente
            // para que cualquier otro valor caiga en 422 VALIDACION.
            'sort' => ['sometimes', 'string', Rule::in([
                'sku', '-sku',
                'name', '-name',
                'created_at', '-created_at',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
