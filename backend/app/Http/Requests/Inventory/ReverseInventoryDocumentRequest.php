<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

// Valido solo la forma de la reversión: el motivo es obligatorio (RF-067) y
// viaja como `reason` según el §7.8; el servicio verifica el fondo (estado
// confirmado, disponibilidad para compensar) dentro de la transacción.
class ReverseInventoryDocumentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'La reversión exige un motivo.',
            'reason.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }
}
