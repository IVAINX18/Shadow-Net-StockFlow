<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Services\Inventory\InventoryRules;
use Illuminate\Foundation\Http\FormRequest;

// Valido solo la forma del reporte de existencias (§7.11): los tres filtros
// documentados más la paginación. El alcance por almacén no se valida aquí
// sino en el controlador, que distingue el almacén inexistente (422 por la
// regla exists) del almacén fuera de alcance (403 FUERA_DE_ALCANCE).
class StockReportRequest extends FormRequest
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
        return InventoryRules::stockReportRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return InventoryRules::messages();
    }
}
