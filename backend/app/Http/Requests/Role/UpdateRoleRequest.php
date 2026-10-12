<?php

declare(strict_types=1);

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Acepto los campos editables como `sometimes`. El `slug` no se edita: es el
// identificador estable del rol (las semillas y las Policies lo usan como
// clave) y el contrato del §7.3 no lo lista entre los campos actualizables;
// por eso ni lo valido ni lo aplico aunque viaje en el cuerpo.
class UpdateRoleRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:150'],
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
            'permission_ids.*.exists' => 'Uno de los permisos indicados no existe.',
        ];
    }
}
