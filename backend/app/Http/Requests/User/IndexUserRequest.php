<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma de los parámetros de consulta del listado porque la
// autorización ya la resolvió el middleware `permission`; aquí cierro la lista
// de campos de orden y la existencia del rol (por slug) y del almacén para que
// un valor arbitrario nunca llegue al SQL (§4 de api-design.md).
class IndexUserRequest extends FormRequest
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
            // El filtro `role` recibe el slug del rol, no su id, porque el slug
            // es el identificador canónico y estable del contrato (§7.2).
            'role' => ['sometimes', 'string', Rule::exists('roles', 'slug')],
            'warehouse_id' => ['sometimes', 'integer', Rule::exists('warehouses', 'id')],
            'q' => ['sometimes', 'string', 'max:150'],
            // Acepto cada columna permitida con y sin el prefijo `-`
            // descendente para que cualquier otro valor caiga en 422.
            'sort' => ['sometimes', 'string', Rule::in([
                'name', '-name',
                'email', '-email',
                'created_at', '-created_at',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.exists' => 'El rol indicado no existe.',
            'warehouse_id.exists' => 'El almacén indicado no existe.',
        ];
    }
}
