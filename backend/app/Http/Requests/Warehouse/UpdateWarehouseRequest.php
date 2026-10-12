<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouse;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Acepto todos los campos como `sometimes`, incluido `code`, porque AC-008
// exige rechazar el renombrado a un código existente: sin este campo la
// unicidad al renombrar no sería comprobable por el contrato.
class UpdateWarehouseRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('warehouses', 'code')->ignore($this->almacenId())],
            'name' => ['sometimes', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
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
            'code.string' => 'El código no es válido.',
            'code.max' => 'El código no es válido.',
            'code.unique' => 'El código ya está en uso.',
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

    // Resuelvo el id del almacén de la ruta tanto si el binding ya entregó el
    // modelo como si solo viaja el identificador, para ignorarlo en la regla
    // de unicidad sin acoplar el Request al orden de los middlewares.
    private function almacenId(): mixed
    {
        $almacen = $this->route('warehouse');

        return $almacen instanceof Warehouse ? $almacen->getKey() : $almacen;
    }
}
