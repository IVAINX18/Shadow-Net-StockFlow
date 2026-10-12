<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma de los parámetros de consulta del listado porque la
// autorización ya la resolvió el middleware `permission`; aquí cierro la lista
// de campos de orden para que un `sort` arbitrario nunca llegue al SQL (§4).
class IndexWarehouseRequest extends FormRequest
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
            'city' => ['sometimes', 'string', 'max:100'],
            'q' => ['sometimes', 'string', 'max:150'],
            // Acepto cada columna permitida con y sin el prefijo `-` descendente
            // para que cualquier otro valor caiga en 422 VALIDACION.
            'sort' => ['sometimes', 'string', Rule::in([
                'code', '-code',
                'name', '-name',
                'created_at', '-created_at',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
