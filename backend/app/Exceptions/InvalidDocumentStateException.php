<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// Represento la transición de estado no permitida de un documento (editar o
// descartar fuera de `borrador`): respondo 409 con el código ESTADO_INVALIDO
// del §5. La confirmación y la reversión, con sus propios estados, las
// gobiernan las Unidades 2 y 3 sin reabrir esta excepción.
final class InvalidDocumentStateException extends RuntimeException
{
    public function __construct(string $message = 'La operación no está permitida en el estado actual del documento.')
    {
        parent::__construct($message);
    }
}
