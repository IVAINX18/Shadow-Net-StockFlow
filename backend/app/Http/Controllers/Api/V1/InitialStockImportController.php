<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Errors\ApiErrorResponse;
use App\Services\Inventory\InitialStockImportRowException;
use App\Services\Inventory\InitialStockImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Expongo la carga inicial de existencias en tres endpoints del §7.13:
// plantilla, dry-run y aplicación. El controlador solo traduce HTTP: la
// lectura, la validación por fila con alcance y la aplicación atómica como
// documentos de entrada confirmados viven en InitialStockImportService, y el
// archivo jamás se guarda en disco.
class InitialStockImportController extends Controller
{
    public function __construct(
        private readonly InitialStockImportService $cargas,
    ) {}

    // Descargo la plantilla con las columnas del contrato y una fila de ejemplo
    // que no debe importarse. Acepto el formato opcional por paridad con la
    // plantilla de catálogo (xlsx por defecto); un formato distinto da 422.
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

        $libro = $this->cargas->buildTemplateSpreadsheet();

        if ($formato === 'csv') {
            return response()->streamDownload(function () use ($libro): void {
                // Añado la BOM para que Excel abra el CSV en UTF-8 sin
                // caracteres rotos; mi lector la retira al importar.
                echo "\xEF\xBB\xBF";
                (new CsvWriter($libro))->save('php://output');
                $libro->disconnectWorksheets();
            }, 'plantilla-carga-inicial.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($libro): void {
            (new XlsxWriter($libro))->save('php://output');
            $libro->disconnectWorksheets();
        }, 'plantilla-carga-inicial.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    // Valido en seco sin escribir nada (RF-134): el informe trae cada fila con
    // su campo y motivo, sin detenerse en la primera mala (RF-129). La fila
    // con producto inexistente o almacén fuera de alcance se rechaza aquí,
    // como fila inválida y sin efectos (AC-068).
    public function validate(Request $request): JsonResponse
    {
        $reporte = $this->cargas->dryRun($this->uploadedFile($request), $request->user());

        return response()->json(['data' => $reporte]);
    }

    // Aplico el archivo como documentos de entrada confirmados (RF-135) en una
    // transacción atómica e idempotente por la clave de idempotencia (RF-136).
    // Si alguna fila falla, no aplico nada y respondo 422 con el informe. La
    // reaplicación con la misma clave devuelve el resumen sin efectos nuevos.
    public function store(Request $request): JsonResponse
    {
        $clave = $request->header('Idempotency-Key');

        if (! is_string($clave) || trim($clave) === '') {
            return ApiErrorResponse::make(
                'VALIDACION',
                'Los datos enviados no son válidos.',
                422,
                [[
                    'field' => 'Idempotency-Key',
                    'code' => 'VALIDACION',
                    'message' => 'La cabecera Idempotency-Key es obligatoria.',
                ]]
            );
        }

        try {
            $resumen = $this->cargas->apply(
                $this->uploadedFile($request),
                trim($clave),
                (int) $request->user()->id,
                $request,
            );
        } catch (InitialStockImportRowException $e) {
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

        // La reaplicación con la misma clave devuelve el resumen con 200 y la
        // carga nueva con 201: así el cliente distingue si su petición fue la
        // que ganó la carrera de la red, igual que en los documentos.
        return response()->json(['data' => $resumen], $resumen['repetida'] ? 200 : 201);
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
