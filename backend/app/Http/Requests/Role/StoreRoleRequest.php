<?php

declare(strict_types=1);

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma de la creación porque la autorización ya la resolvieron
// el middleware `permission` y la Policy; aquí cierro unicidad del slug y
// existencia de los permisos (§7.3 de api-design.md).
class StoreRoleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:100', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'slug.required' => 'El identificador del rol es obligatorio.',
            'slug.unique' => 'El identificador del rol ya está en uso.',
            'permission_ids.*.exists' => 'Uno de los permisos indicados no existe.',
        ];
    }
}
