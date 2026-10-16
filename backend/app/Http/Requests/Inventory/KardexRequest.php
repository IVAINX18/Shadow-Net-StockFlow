<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Services\Inventory\InventoryRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// Valido solo la forma del kardex (§7.9): producto y almacén obligatorios,
// rango opcional y paginación. El rango inválido (desde posterior al hasta)
// cae en 422 aquí para no llegar siquiera a la consulta.
class KardexRequest extends FormRequest
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
        return InventoryRules::kardexRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return InventoryRules::messages();
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        // Comparo los límites ya expandidos (fecha sola abarca su día
        // completo) porque validar las cadenas crudas rechazaría un rango
        // válido como `from=2026-10-10` con `to=2026-10-10T08:00:00Z`.
        $validator->after(function ($validator): void {
            $from = $this->rangeBound($this->input('from'), false);
            $to = $this->rangeBound($this->input('to'), true);

            if ($from !== null && $to !== null && $from->greaterThan($to)) {
                $validator->errors()->add('to', 'El rango de fechas no es válido.');
            }
        });
    }

    // Expando el límite a su instante inclusivo: una fecha sola abarca su día
    // completo (desde las 00:00:00, hasta las 23:59:59) para que el filtro por
    // día no excluya silenciosamente los movimientos de ese día; una fecha
    // con hora se respeta tal cual. Ambos extremos son inclusivos.
    public function rangeBound(mixed $value, bool $isEnd): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $parsed = CarbonImmutable::parse(trim($value));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1) {
            return $isEnd ? $parsed->endOfDay() : $parsed->startOfDay();
        }

        return $parsed;
    }
}
