<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Acepto los campos editables como `sometimes` porque el PUT actualiza el
// recurso administrativo. El cambio de contraseña es RF-014 y pertenece a la
// unidad de cuenta propia (V1), no a este CRUD; por eso `password` viaja como
// `prohibited` y no simplemente ignorado: responder 200 sin cambiarla haría
// creer al cliente que la contraseña se modificó. Un valor vacío sí se tolera,
// porque un formulario puede enviar el campo sin contenido.
class UpdateUserRequest extends FormRequest
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
            // Ignoro el propio registro en la unicidad para que conservar el
            // correo actual no se rechace como duplicado.
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->usuarioId())],
            // El §7.2 excluye el cambio de contraseña de este endpoint.
            'password' => ['prohibited'],
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
            'email.email' => 'El correo no es válido.',
            'email.unique' => 'El correo ya está en uso.',
            'password.prohibited' => 'La contraseña no se cambia por este endpoint.',
            'role_ids.*.exists' => 'Uno de los roles indicados no existe.',
            'warehouse_ids.*.exists' => 'Uno de los almacenes indicados no existe.',
        ];
    }

    // Resuelvo el id del usuario de la ruta tanto si el binding ya entregó el
    // modelo como si solo viaja el identificador, para ignorarlo en la regla
    // de unicidad sin acoplar el Request al orden de los middlewares.
    private function usuarioId(): mixed
    {
        $usuario = $this->route('user');

        return $usuario instanceof User ? $usuario->getKey() : $usuario;
    }
}
