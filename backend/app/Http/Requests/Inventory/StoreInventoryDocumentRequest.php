<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Services\Inventory\InventoryRules;
use Illuminate\Foundation\Http\FormRequest;

// Valido solo la forma de la creación: las reglas viven en InventoryRules
// para compartirlas con las puertas futuras y el fondo (almacenes y
// productos activos, coherencia por tipo) lo verifica el servicio de
// dominio, igual que ProductService con la categoría activa.
class StoreInventoryDocumentRequest extends FormRequest
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
        return InventoryRules::documentRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return InventoryRules::messages();
    }
}
