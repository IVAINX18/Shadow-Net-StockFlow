<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma de la asociación porque la autorización ya la
// resolvieron el middleware `permission` y la Policy; aquí cierro que cada
// usuario exista y esté activo, como exige el §7.4.
class AttachWarehouseUsersRequest extends FormRequest
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
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'Debe enviar al menos un usuario.',
            'user_ids.array' => 'Los usuarios no son válidos.',
            'user_ids.min' => 'Debe enviar al menos un usuario.',
            'user_ids.*.integer' => 'Los usuarios no son válidos.',
            'user_ids.*.exists' => 'Los usuarios no son válidos.',
        ];
    }
}
