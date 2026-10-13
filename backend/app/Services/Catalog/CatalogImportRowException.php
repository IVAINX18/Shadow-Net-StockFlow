<?php

declare(strict_types=1);

namespace App\Services\Catalog;

// Transporto los errores por fila de la importación para que el controlador
// responda 422 con el informe (fila, campo y motivo) sin aplicar nada: no uso
// ValidationException aquí porque su envoltura solo admite pares campo/mensaje
// y yo necesito el número de fila en cada detalle (AC-063).
final class CatalogImportRowException extends \RuntimeException
{
    /**
     * @param  list<array{fila: int, campo: string, motivo: string}>  $rowErrors
     */
    public function __construct(public readonly array $rowErrors)
    {
        parent::__construct('El archivo contiene filas inválidas. No se aplicó ningún cambio.');
    }
}
