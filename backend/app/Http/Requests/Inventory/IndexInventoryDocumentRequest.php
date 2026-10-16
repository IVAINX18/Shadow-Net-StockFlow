<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Services\Inventory\InventoryRules;
use Illuminate\Foundation\Http\FormRequest;

// Valido solo la forma del listado: los filtros no documentados se ignoran
// por no tener regla y el `sort` ajeno cae en 422, igual que el catálogo.
class IndexInventoryDocumentRequest extends FormRequest
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
        return InventoryRules::documentIndexRules();
    }
}
