<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\UnitOfMeasure;

// Concentro aquí la creación de unidades porque la importación de catálogo
// (EP-012, Unidad posterior) reutilizará este mismo punto de entrada: el
// controlador valida la forma y yo garantizo que toda alta pase por aquí.
final class UnitOfMeasureService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): UnitOfMeasure
    {
        return UnitOfMeasure::create($data);
    }

    // Busco la unidad por su código para la importación de catálogo (EP-012):
    // la importación nunca crea unidades (una errata crearía silenciosamente
    // una unidad de medida y corrompería el catálogo), así que el código
    // desconocido es fila inválida y el llamante lo reporta como tal.
    public function findByCode(string $code): ?UnitOfMeasure
    {
        return UnitOfMeasure::query()
            ->where('code', trim($code))
            ->first();
    }
}
