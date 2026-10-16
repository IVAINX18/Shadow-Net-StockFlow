<?php

declare(strict_types=1);

namespace App\Domain\Enums;

// Enumero los estados del documento del §3.1 porque la máquina de estados es
// el corazón del dominio: con literales tipados, una transición a un estado
// inexistente falla en compilación en lugar de colarse como cadena libre.
// En esta unidad solo se transita borrador -> descartado; confirmado y
// revertido los gobierna la Unidad 2 sin reabrir este enum.
enum InventoryDocumentStatus: string
{
    case Borrador = 'borrador';
    case Confirmado = 'confirmado';
    case Revertido = 'revertido';
    case Descartado = 'descartado';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
