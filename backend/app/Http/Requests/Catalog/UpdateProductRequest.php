<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Services\Catalog\CatalogRules;
use Illuminate\Foundation\Http\FormRequest;

// Acepto los mismos campos de la creación más el estado, incluido `sku` para
// poder detectar el intento de cambio: el SKU es inmutable y ProductService lo
// rechaza con 422 aunque el valor nuevo sea único (lectura conservadora del
// contrato del §7.7). Si no aceptara el campo, el rechazo sería silencioso.
class UpdateProductRequest extends FormRequest
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
        return CatalogRules::productUpdateRules($this->route('product'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CatalogRules::messages();
    }
}
