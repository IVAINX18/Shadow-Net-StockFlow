<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

// Valido solo la forma de la creación de unidades: el código es el
// identificador estable y único (RF-031) y la unicidad vive en el motor con
// uq_units_of_measure_code, no solo en esta regla.
class StoreUnitOfMeasureRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', 'unique:units_of_measure,code'],
            'name' => ['required', 'string', 'max:80'],
            'allows_decimals' => ['sometimes', 'boolean'],
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
            'allows_decimals.boolean' => 'El permiso de decimales no es válido.',
        ];
    }
}
