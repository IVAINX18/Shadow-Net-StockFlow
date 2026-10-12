<?php

declare(strict_types=1);

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma de los parámetros del catálogo porque la autorización
// ya la resolvió el middleware `permission`; aquí cierro el filtro y la lista
// de campos de orden (§4 de api-design.md).
class IndexPermissionRequest extends FormRequest
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
            'module' => ['sometimes', 'string', 'max:50'],
            'sort' => ['sometimes', 'string', Rule::in([
                'module', '-module',
                'name', '-name',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
