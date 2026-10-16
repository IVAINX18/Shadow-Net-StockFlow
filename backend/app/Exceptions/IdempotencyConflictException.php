<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// Represento el reintento con una clave ya usada cuyo cuerpo difiere del
// original: no creo un segundo documento y respondo 409 con el código
// IDEMPOTENCIA_REPETIDA del §5 (C-05). Lo separo de ValidationException para
// que el cliente distinga el conflicto de idempotencia de un 422 de forma.
final class IdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        // No describo el documento existente porque su uuid podría estar fuera
        // del alcance visible del llamante y C-02 prohíbe filtrar referencias
        // ajenas en los errores.
        parent::__construct('La clave de idempotencia ya fue usada con datos distintos.');
    }
}
