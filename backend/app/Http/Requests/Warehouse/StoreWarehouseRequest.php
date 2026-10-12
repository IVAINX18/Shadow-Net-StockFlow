<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma de la creación porque la autorización ya la resolvieron
// el middleware `permission` y la Policy; aquí cierro unicidad y existencia.
class StoreWarehouseRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'unique:warehouses,code'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            // Exijo responsable activo porque RF-022 solo permite designar un
            // usuario activo; un inactivo o inexistente cae en 422 VALIDACION.
            'manager_user_id' => [
                'nullable',
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
            'code.required' => 'El código es obligatorio.',
            'code.string' => 'El código no es válido.',
            'code.max' => 'El código no es válido.',
            'code.unique' => 'El código ya está en uso.',
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre no es válido.',
            'name.max' => 'El nombre no es válido.',
            'address.string' => 'La dirección no es válida.',
            'address.max' => 'La dirección no es válida.',
            'city.string' => 'La ciudad no es válida.',
            'city.max' => 'La ciudad no es válida.',
            'manager_user_id.integer' => 'El responsable no es válido.',
            'manager_user_id.exists' => 'El responsable no es válido.',
        ];
    }
}
