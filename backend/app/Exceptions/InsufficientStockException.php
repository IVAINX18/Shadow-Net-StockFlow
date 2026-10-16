<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// Represento el rechazo de una operación sin disponibilidad suficiente: la
// salida, el ajuste negativo o la pata de origen que excede la existencia se
// rechazan con 409 STOCK_INSUFICIENTE y ningún efecto parcial, como ordena el
// §5 de api-design.md. Llevo el detalle por línea porque el cliente necesita
// saber el disponible frente al solicitado sin adivinarlo.
final class InsufficientStockException extends RuntimeException
{
    /** @var list<array{field: string, code: string, message: string}> */
    private array $detalles;

    /**
     * @param  list<array{field: string, code: string, message: string}>  $detalles
     */
    public function __construct(array $detalles, string $message = 'No hay existencias suficientes para completar la operación.')
    {
        parent::__construct($message);

        $this->detalles = $detalles;
    }

    /**
     * @return list<array{field: string, code: string, message: string}>
     */
    public function details(): array
    {
        return $this->detalles;
    }
}
