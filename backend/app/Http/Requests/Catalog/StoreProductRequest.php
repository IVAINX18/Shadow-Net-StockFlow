<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Services\Catalog\CatalogRules;
use Illuminate\Foundation\Http\FormRequest;

// Valido solo la forma de la creación: las reglas viven en CatalogRules para
// compartirlas con la importación (RN-IMP-02) y la categoría activa la verifica
// ProductService porque es regla de dominio (RF-035), no de forma, y la FK con
// RESTRICT queda como última red ante una carrera entre validación e inserción.
class StoreProductRequest extends FormRequest
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
        // Delego en la capa compartida para que la captura manual y la
        // importación validen con las mismas reglas (RN-IMP-02).
        return CatalogRules::productRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CatalogRules::messages();
    }
}
