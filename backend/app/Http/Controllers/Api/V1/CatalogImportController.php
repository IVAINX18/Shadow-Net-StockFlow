<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Errors\ApiErrorResponse;
use App\Services\Catalog\CatalogImportRowException;
use App\Services\Catalog\CatalogImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Expongo la importación de catálogo en tres endpoints del §7.13: plantilla,
// dry-run y aplicación. El controlador solo traduce HTTP: la lectura, la
// validación compartida con la captura manual y la transacción viven en
// CatalogImportService, y el archivo jamás se guarda en disco.
class CatalogImportController extends Controller
{
    public function __construct(
        private readonly CatalogImportService $imports,
    ) {}

    // Descargo la plantilla en el formato pedido con las columnas del contrato
    // y una fila de ejemplo que no debe importarse; un formato distinto da 422.
    public function template(Request $request): StreamedResponse|JsonResponse
    {
        $formato = mb_strtolower(trim((string) $request->query('format', 'xlsx')));

        if (! in_array($formato, ['xlsx', 'csv'], true)) {
            return ApiErrorResponse::make(
                'VALIDACION',
                'Los datos enviados no son válidos.',
                422,
                [['field' => 'format', 'code' => 'VALIDACION', 'message' => 'El formato no es válido. Use xlsx o csv.']]
            );
        }

        $libro = $this->imports->buildTemplateSpreadsheet();

        if ($formato === 'csv') {
            return response()->streamDownload(function () use ($libro): void {
                // Añado la BOM para que Excel abra el CSV en UTF-8 sin
                // caracteres rotos; mi lector la retira al importar.
                echo "\xEF\xBB\xBF";
                (new CsvWriter($libro))->save('php://output');
                $libro->disconnectWorksheets();
            }, 'plantilla-catalogo.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($libro): void {
            (new XlsxWriter($libro))->save('php://output');
            $libro->disconnectWorksheets();
        }, 'plantilla-catalogo.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    // Valido en seco sin escribir nada (AC-062): el informe trae cada fila con
    // su campo y motivo, sin detenerse en la primera mala (RF-129).
    public function validate(Request $request): JsonResponse
    {
        $reporte = $this->imports->dryRun($this->uploadedFile($request));

        return response()->json(['data' => $reporte]);
    }

    // Aplico el archivo en una transacción idempotente por SKU (AC-064) y dejo
    // una entrada de auditoría con autor, archivo y conteos (AC-065). Si alguna
    // fila falla, no aplico nada y respondo 422 con el informe (AC-063).
    public function store(Request $request): JsonResponse
    {
        try {
            $resumen = $this->imports->apply($this->uploadedFile($request), $request);
        } catch (CatalogImportRowException $e) {
            $detalles = array_map(
                static fn (array $error): array => [
                    'fila' => $error['fila'],
                    'campo' => $error['campo'],
                    'codigo' => 'VALIDACION',
                    'motivo' => $error['motivo'],
                ],
                $e->rowErrors
            );

            return ApiErrorResponse::make(
                'VALIDACION',
                'El archivo contiene filas inválidas. No se aplicó ningún cambio.',
                422,
                $detalles
            );
        }

        return response()->json(['data' => $resumen], 201);
    }

    // Exijo el archivo en la petición: sin él respondo 422 con el sobre
    // uniforme en lugar de un 500 por ruta temporal inexistente.
    /**
     * @throws ValidationException
     */
    private function uploadedFile(Request $request): UploadedFile
    {
        $archivo = $request->file('archivo', $request->file('file'));

        if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo es obligatorio. Envíelo como multipart con el campo archivo.'],
            ]);
        }

        return $archivo;
    }
}
