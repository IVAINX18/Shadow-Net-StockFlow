<?php

declare(strict_types=1);

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma de los parámetros de consulta del listado porque la
// autorización ya la resolvió el middleware `permission`; aquí cierro la lista
// de campos de orden para que un `sort` arbitrario nunca llegue al SQL (§4).
class IndexRoleRequest extends FormRequest
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
            'sort' => ['sometimes', 'string', Rule::in([
                'name', '-name',
                'created_at', '-created_at',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
