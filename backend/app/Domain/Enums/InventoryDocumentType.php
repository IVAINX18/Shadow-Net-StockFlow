<?php

declare(strict_types=1);

namespace App\Domain\Enums;

// Enumero los tipos de documento del §3 porque así dejan de ser cadenas
// sueltas: el compilador detecta una errata y el prefijo del número legible
// sale de una única fuente en lugar de repartirse por el servicio.
enum InventoryDocumentType: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case Ajuste = 'ajuste';
    case Transferencia = 'transferencia';

    // Expongo los literales para validarlos en el servicio y en las reglas de
    // forma sin duplicar la lista en cada llamada.
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    // Derivo el prefijo del número legible del propio tipo para que el
    // formato PREFIX-AAAA-###### viva junto al literal que lo origina: si un
    // día cambia un prefijo, cambia en un solo sitio.
    public function docPrefix(): string
    {
        return match ($this) {
            self::Entrada => 'ENT',
            self::Salida => 'SAL',
            self::Ajuste => 'AJU',
            self::Transferencia => 'TRA',
        };
    }
}
