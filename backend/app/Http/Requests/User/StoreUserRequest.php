<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma del alta porque la autorización ya la resolvieron el
// middleware `permission` y la Policy; aquí cierro unicidad, longitud mínima
// de clave y existencia de roles y almacenes (§7.2 de api-design.md).
class StoreUserRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'warehouse_ids' => ['sometimes', 'array'],
            'warehouse_ids.*' => ['integer', Rule::exists('warehouses', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no es válido.',
            'email.unique' => 'El correo ya está en uso.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'role_ids.*.exists' => 'Uno de los roles indicados no existe.',
            'warehouse_ids.*.exists' => 'Uno de los almacenes indicados no existe.',
        ];
    }
}
