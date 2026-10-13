<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

// Valido solo la forma de la creación: el ciclo y el tope de 3 niveles los
// verifica CategoryService porque son reglas de dominio, no de forma (la FK con
// RESTRICT queda como última red ante una carrera entre validación e inserción).
class StoreCategoryRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            // El slug es opcional y único solo cuando viene informado: MySQL
            // admite varios NULL en la UNIQUE, así que varias categorías sin
            // slug conviven sin violar uq_categories_slug.
            'slug' => ['nullable', 'string', 'max:180', 'unique:categories,slug'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre no es válido.',
            'name.max' => 'El nombre no es válido.',
            'parent_id.integer' => 'La categoría padre no es válida.',
            'parent_id.exists' => 'La categoría padre no es válida.',
            'slug.string' => 'El slug no es válido.',
            'slug.max' => 'El slug no es válido.',
            'slug.unique' => 'El slug ya está en uso.',
        ];
    }
}
