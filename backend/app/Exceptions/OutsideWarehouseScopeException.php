<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// Represento el caso en que el usuario SÍ tiene el permiso RBAC pero el
// almacén pedido cae fuera de su alcance (warehouse_user). Lo separo de
// AuthorizationException para poder responder FUERA_DE_ALCANCE en lugar del
// SIN_PERMISO genérico, tal como exige la tabla de errores del §5.
final class OutsideWarehouseScopeException extends RuntimeException
{
    public function __construct()
    {
        // Mantengo un mensaje claro pero sin filtrar la identidad del almacén
        // ajeno, porque C-02 prohíbe revelar datos de almacenes fuera de alcance.
        parent::__construct('El almacén solicitado está fuera de su alcance autorizado.');
    }
}
