<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

// Acepto solo los campos del contrato del PUT (§7.5: nombre, padre y estado):
// el slug es inmutable tras la creación porque es el identificador estable de
// la categoría y el contrato no contempla su edición en esta unidad.
class UpdateCategoryRequest extends FormRequest
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
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.string' => 'El nombre no es válido.',
            'name.max' => 'El nombre no es válido.',
            'parent_id.integer' => 'La categoría padre no es válida.',
            'parent_id.exists' => 'La categoría padre no es válida.',
            'is_active.boolean' => 'El estado no es válido.',
        ];
    }
}
