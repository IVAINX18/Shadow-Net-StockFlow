<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Services\Inventory\InventoryRules;
use Illuminate\Foundation\Http\FormRequest;

// Valido la edición con las mismas reglas que la creación porque el PUT
// repone el borrador completo (cabecera y líneas): relajar aquí colaría
// borradores que la creación rechaza.
class UpdateInventoryDocumentRequest extends FormRequest
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
        return InventoryRules::documentUpdateRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return InventoryRules::messages();
    }
}
