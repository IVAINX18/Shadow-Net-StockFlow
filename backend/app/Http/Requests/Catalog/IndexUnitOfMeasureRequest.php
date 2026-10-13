<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valido solo la forma del listado de unidades: el `sort` cae en 422 ante
// cualquier campo fuera de la lista cerrada del §7.6 y el resto de parámetros
// no documentados se ignoran por no tener regla.
class IndexUnitOfMeasureRequest extends FormRequest
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
            'sort' => ['sometimes', 'string', Rule::in([
                'code', '-code',
                'name', '-name',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
