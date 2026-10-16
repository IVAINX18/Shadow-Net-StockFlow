<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Exceptions\OutsideWarehouseScopeException;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Aplico la carga inicial de existencias (RF-133 a RF-136) como documentos de
// entrada confirmados, nunca como escritura directa en `inventories.quantity`:
// solo así el saldo nace del kardex y la invariante I-02 se sostiene desde la
// primera fila. Reutilizo el motor de InventoryDocumentService (bloqueos
// ordenados, promedio ponderado e idempotencia por clave) en lugar de un camino
// paralelo, con la disciplina de plantilla, dry-run y aplicación atómica que
// ya estableció la importación de catálogo. El archivo jamás se guarda en
// disco: solo leo su contenido temporal.
final class InitialStockImportService
{
    // Columnas de la plantilla, en este orden y con estos encabezados exactos
    // (§7.13): el archivo que no las traiga tal cual se rechaza con 422.
    public const HEADERS = ['sku', 'almacen', 'cantidad', 'costo'];

    // Mismos topes que la importación de catálogo (§7.13): el contrato exige
    // límites sin dar la cifra y AC-066 pide rechazo uniforme con 422.
    public const MAX_FILE_BYTES = 5242880;

    public const MAX_ROWS = 1000;

    // Justifico cada documento de apertura con la misma nota fija: la
    // comparación canónica de idempotencia incluye las notas, así que una nota
    // variable convertiría una reaplicación legítima en un falso conflicto.
    private const NOTAS_APERTURA = 'Carga inicial de existencias';

    public function __construct(
        private readonly InventoryDocumentService $documentos,
        private readonly AuditLogger $auditoria,
    ) {}

    // Construyo la plantilla con las columnas del contrato y una fila de
    // ejemplo marcada como tal: es ilustrativa y no debe importarse.
    public function buildTemplateSpreadsheet(): Spreadsheet
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('saldo_inicial');

        $hoja->fromArray([self::HEADERS], null, 'A1');

        // Escribo la fila de ejemplo como cadenas explícitas porque el binder
        // por defecto convertiría "10.000" en número y la plantilla debe
        // mostrar los decimales como cadenas (§3 de api-design.md).
        $ejemplo = ['EXAMPLE-001', 'WH-001', '10.000', '5.250000'];

        foreach (array_values($ejemplo) as $indice => $valor) {
            $hoja->setCellValueExplicit([$indice + 1, 2], $valor, DataType::TYPE_STRING);
        }

        foreach (range('A', 'D') as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }

        return $libro;
    }

    // Informo en seco sin escribir nada (AC-062 vale para la carga inicial por
    // RF-134): los conteos antes y después son idénticos porque este camino
    // nunca toca la base.
    /**
     * @return array{archivo: string, total_filas: int, filas_validas: int, filas_invalidas: int, valida: bool, filas: list<array{fila: int, sku: ?string, almacen: ?string, valida: bool, errores: list<array{campo: string, motivo: string}>}>}
     *
     * @throws ValidationException
     */
    public function dryRun(UploadedFile $file, User $autor): array
    {
        $parsed = $this->parseUploadedFile($file);

        return $this->report($parsed['fileName'], $this->validateData($parsed['rows'], $autor));
    }

    // Aplico el archivo agrupando por almacén: el documento de entrada admite
    // un solo origen, así que cada almacén del archivo genera su propio
    // documento de apertura, todos confirmados por el mismo camino
    // transaccional que la confirmación manual (RF-075, RF-135). Envuelvo el
    // conjunto en una transacción externa para que la carga sea atómica aunque
    // toque varios almacenes: si un documento falla, no queda ninguno a
    // medias. La clave de idempotencia del archivo se deriva por almacén, así
    // que reenviar el mismo archivo con la misma clave reejecuta cada
    // documento sin duplicar existencias (RF-136).
    /**
     * @return array{archivo: string, filas_totales: int, documentos: list<array{uuid: string, doc_number: string, warehouse_id: int, lineas: int}>, auditoria_id: int, repetida: bool}
     *
     * @throws ValidationException
     * @throws InitialStockImportRowException
     */
    public function apply(UploadedFile $file, string $idempotencyKey, int $autorId, Request $solicitud): array
    {
        $autor = User::query()->findOrFail($autorId);

        $parsed = $this->parseUploadedFile($file);
        $rows = $this->validateData($parsed['rows'], $autor);

        $errores = [];

        foreach ($rows as $row) {
            foreach ($row['errores'] as $error) {
                $errores[] = ['fila' => $row['fila'], 'campo' => $error['campo'], 'motivo' => $error['motivo']];
            }
        }

        if ($errores !== []) {
            throw new InitialStockImportRowException($errores);
        }

        $porAlmacen = [];

        foreach ($rows as $row) {
            $porAlmacen[$row['valores']['warehouse_id']][] = $row;
        }

        // Proceso los almacenes en orden ascendente de id para que dos cargas
        // concurrentes tomen los mismos candados en el mismo orden (§6.2 del
        // dominio), igual que el motor ordena sus efectos por par.
        ksort($porAlmacen);

        return DB::transaction(function () use ($parsed, $porAlmacen, $rows, $idempotencyKey, $autorId, $autor, $solicitud): array {
            $documentos = [];
            $repetidos = 0;

            foreach ($porAlmacen as $almacenId => $filas) {
                // Reafirmo el alcance dentro de la transacción de aplicación:
                // la asignación del almacén pudo revocarse entre la validación
                // y este punto, y el servicio de documentos no conoce al
                // usuario peticionario (el alcance lo verifica el controlador
                // y, aquí, yo antes de crear).
                if (! $autor->canAccessWarehouse((int) $almacenId)) {
                    throw new OutsideWarehouseScopeException;
                }

                $lineas = collect($filas)
                    ->sortBy(static fn (array $fila): int => $fila['valores']['product_id'])
                    ->map(static fn (array $fila): array => [
                        'product_id' => $fila['valores']['product_id'],
                        'quantity' => $fila['valores']['cantidad'],
                        'unit_cost' => $fila['valores']['costo'],
                    ])
                    ->values()
                    ->all();

                $resultado = $this->documentos->create(
                    [
                        'type' => 'entrada',
                        'origin_warehouse_id' => (int) $almacenId,
                        'notes' => self::NOTAS_APERTURA,
                        'lines' => $lineas,
                    ],
                    trim($idempotencyKey).':wh-'.((int) $almacenId),
                    $autorId,
                    true,
                    $solicitud,
                );

                if ($resultado['replayed']) {
                    $repetidos++;
                }

                $documentos[] = $resultado['document']->refresh()->load('lines');
            }

            // Dejo una sola entrada por carga con autor, nombre del archivo,
            // conteos y documentos generados: nunca el contenido, las filas ni
            // secretos, igual que la importación de catálogo.
            $entrada = $this->auditoria->record('cargar_saldo_inicial', 'importacion_saldo_inicial', null, null, [
                'archivo' => $parsed['fileName'],
                'filas_totales' => count($rows),
                'almacenes' => array_map(intval(...), array_keys($porAlmacen)),
                'documentos' => array_map(static fn ($documento): string => (string) $documento->uuid, $documentos),
            ], $solicitud);

            return [
                'archivo' => $parsed['fileName'],
                'filas_totales' => count($rows),
                'documentos' => array_map(static fn ($documento): array => [
                    'uuid' => (string) $documento->uuid,
                    'doc_number' => (string) $documento->doc_number,
                    'warehouse_id' => (int) $documento->origin_warehouse_id,
                    'lineas' => count($documento->lines),
                ], $documentos),
                'auditoria_id' => $entrada->id,
                'repetida' => $repetidos === count($documentos),
            ];
        });
    }

    // Leo el archivo sin guardarlo en disco: trabajo sobre la ruta temporal de
    // la petición, que Laravel elimina al terminar (el contrato no define
    // staging y no invento tablas ni persistencia del fichero).
    /**
     * @return array{fileName: string, rows: list<array{fila: int, valores: array<string, ?string>}>}
     *
     * @throws ValidationException
     */
    private function parseUploadedFile(UploadedFile $file): array
    {
        $tamano = $file->getSize() ?? (int) @filesize((string) $file->getRealPath());

        if ($tamano > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo supera el tamaño máximo de 5 MB.'],
            ]);
        }

        $extension = mb_strtolower(trim((string) $file->getClientOriginalExtension()));

        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages([
                'archivo' => ['El tipo de archivo no es válido. Solo se aceptan archivos xlsx y csv.'],
            ]);
        }

        $crudas = $extension === 'csv'
            ? $this->readCsvRows($file)
            : $this->readXlsxRows($file);

        if ($crudas === []) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo no contiene encabezados. Descargue la plantilla e inténtelo de nuevo.'],
            ]);
        }

        $encabezados = array_map(
            static fn (mixed $h): string => mb_strtolower(trim((string) $h), 'UTF-8'),
            array_values($crudas[0])
        );

        if ($encabezados !== self::HEADERS) {
            throw ValidationException::withMessages([
                'archivo' => ['Las columnas del archivo no coinciden con la plantilla esperada (sku, almacen, cantidad, costo).'],
            ]);
        }

        $filas = [];
        $linea = 1;

        foreach (array_slice($crudas, 1) as $cruda) {
            $linea++;
            $valores = [];

            foreach (self::HEADERS as $indice => $columna) {
                $valores[$columna] = $this->cellToStringOrNull($cruda[$indice] ?? null);
            }

            // Omito las filas totalmente vacías: no cuentan como datos ni como
            // errores, igual que una hoja con renglones en blanco al final.
            $conDatos = false;

            foreach ($valores as $v) {
                if ($v !== null && $v !== '') {
                    $conDatos = true;
                    break;
                }
            }

            if (! $conDatos) {
                continue;
            }

            foreach ($valores as $columna => $v) {
                $valores[$columna] = $v === '' ? null : $v;
            }

            $filas[] = ['fila' => $linea, 'valores' => $valores];
        }

        // Un archivo sin filas de datos se rechaza: aplicar el vacío con un
        // 201 y una auditoría de cero filas falsearía la trazabilidad.
        if ($filas === []) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo no contiene filas de datos.'],
            ]);
        }

        if (count($filas) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo supera el límite de 1000 filas.'],
            ]);
        }

        return ['fileName' => $file->getClientOriginalName(), 'rows' => $filas];
    }

    // Leo el CSV con funciones nativas (sin dependencia extra): exijo UTF-8
    // decodificable y rechazo el resto con 422 (RF-132).
    /**
     * @return list<list<mixed>>
     *
     * @throws ValidationException
     */
    private function readCsvRows(UploadedFile $file): array
    {
        $contenido = (string) file_get_contents((string) $file->getRealPath());

        if (! mb_check_encoding($contenido, 'UTF-8')) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo no está codificado en UTF-8. Guárdelo en UTF-8 e inténtelo de nuevo.'],
            ]);
        }

        // Retiro la marca BOM para que el primer encabezado no llegue con un
        // carácter invisible que rompería la comparación exacta de columnas.
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;

        $filas = [];

        foreach (preg_split('/\r\n|\r|\n/', $contenido) ?: [] as $linea) {
            if (trim($linea) === '') {
                continue;
            }

            $filas[] = str_getcsv($linea, ',', '"', '\\');
        }

        return $filas;
    }

    // Leo el XLSX en modo solo datos y sin fórmulas calculadas de más: con el
    // tope de 1000 filas el consumo cabe en la RAM disponible (riesgo R-10).
    /**
     * @return list<list<mixed>>
     */
    private function readXlsxRows(UploadedFile $file): array
    {
        $lector = IOFactory::createReader('Xlsx');
        $lector->setReadDataOnly(true);
        $libro = $lector->load((string) $file->getRealPath());

        $filas = $libro->getActiveSheet()->toArray(null, true, true, false);

        $libro->disconnectWorksheets();

        /** @var list<list<mixed>> $filas */
        return is_array($filas) ? array_values($filas) : [];
    }

    // Normalizo la celda a cadena o null sin usar float para dinero: los
    // numéricos de Excel llegan como int/float y los convierto a su forma
    // decimal exacta antes de validar, para no perder precisión en el borde.
    private function cellToStringOrNull(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }

        if (is_int($valor)) {
            return (string) $valor;
        }

        if (is_float($valor)) {
            // Evito la notación científica de los enteros grandes formateando
            // con decimales exactos y recorte.
            $texto = sprintf('%.10F', $valor);
            $texto = rtrim(rtrim($texto, '0'), '.');

            return $texto === '' ? '0' : $texto;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    // Valido cada fila contra el fondo que la confirmación exigiría al aplicar
    // (producto y almacén activos, cantidad positiva, costo no negativo,
    // decimales según la unidad y alcance del usuario) y reporto TODAS las
    // filas malas sin detenerme en la primera (RF-129): este método no escribe
    // nada, así sirve igual al dry-run y al apply previo. La forma decimal
    // (hasta 3 decimales en cantidad y 6 en costo) la verifico con las mismas
    // reglas `decimal` que InventoryRules usa para las líneas manuales, para
    // que el archivo nunca relaje el formulario (RN-IMP-02).
    /**
     * @param  list<array{fila: int, valores: array<string, ?string>}>  $rows
     * @return list<array{fila: int, valores: array<string, mixed>, sku: ?string, almacen: ?string, valida: bool, errores: list<array{campo: string, motivo: string}>}>
     */
    private function validateData(array $rows, User $autor): array
    {
        $skus = [];
        $codigos = [];

        foreach ($rows as $row) {
            if ($row['valores']['sku'] !== null) {
                $skus[] = $row['valores']['sku'];
            }

            if ($row['valores']['almacen'] !== null) {
                $codigos[] = $row['valores']['almacen'];
            }
        }

        $porSku = Product::query()
            ->with('unitOfMeasure')
            ->withTrashed()
            ->whereIn('sku', array_values(array_unique($skus)))
            ->get()
            ->keyBy(static fn (Product $p): string => mb_strtolower($p->sku, 'UTF-8'));

        $porAlmacen = Warehouse::query()
            ->withTrashed()
            ->whereIn('code', array_values(array_unique($codigos)))
            ->get()
            ->keyBy(static fn (Warehouse $a): string => mb_strtolower($a->code, 'UTF-8'));

        // El par producto-almacén duplicado dentro del archivo es fila
        // inválida: sin esta guarda, dos renglones del mismo par generarían
        // dos líneas del mismo producto en un documento que las prohíbe
        // duplicadas, y el apply fallaría a mitad de la transacción.
        $pares = [];

        foreach ($rows as $row) {
            $sku = $row['valores']['sku'];
            $codigo = $row['valores']['almacen'];

            if ($sku !== null && $codigo !== null) {
                $pares[mb_strtolower($sku, 'UTF-8')."\0".mb_strtolower($codigo, 'UTF-8')][] = $row['fila'];
            }
        }

        $resultado = [];

        foreach ($rows as $row) {
            $valores = $row['valores'];
            $errores = [];

            $agregar = static function (string $campo, string $motivo) use (&$errores): void {
                $errores[] = ['campo' => $campo, 'motivo' => $motivo];
            };

            $producto = $valores['sku'] !== null
                ? $porSku->get(mb_strtolower($valores['sku'], 'UTF-8'))
                : null;

            $almacen = $valores['almacen'] !== null
                ? $porAlmacen->get(mb_strtolower($valores['almacen'], 'UTF-8'))
                : null;

            if ($valores['sku'] === null) {
                $agregar('sku', 'El SKU es obligatorio.');
            } elseif ($producto === null) {
                $agregar('sku', 'El producto no existe.');
            } elseif ($producto->trashed() || ! $producto->is_active) {
                $agregar('sku', 'El producto no está activo y no admite movimientos nuevos.');
            } elseif (count($pares[mb_strtolower($valores['sku'], 'UTF-8')."\0".mb_strtolower((string) $valores['almacen'], 'UTF-8')] ?? []) > 1) {
                $agregar('sku', 'El par producto-almacén está duplicado dentro del archivo.');
            }

            if ($valores['almacen'] === null) {
                $agregar('almacen', 'El almacén es obligatorio.');
            } elseif ($almacen === null) {
                $agregar('almacen', 'El almacén no existe.');
            } elseif ($almacen->trashed() || ! $almacen->is_active) {
                $agregar('almacen', 'El almacén no está activo y no admite documentos nuevos.');
            } elseif (! $autor->canAccessWarehouse((int) $almacen->id)) {
                $agregar('almacen', 'El almacén está fuera de su alcance.');
            }

            // Verifico la forma decimal con las reglas compartidas del
            // inventario: cantidad hasta 3 decimales y costo hasta 6, como
            // cadenas y nunca como flotante (§3 de api-design.md).
            $fallos = Validator::make(
                ['cantidad' => $valores['cantidad'], 'costo' => $valores['costo']],
                [
                    'cantidad' => ['required', 'string', 'decimal:0,3'],
                    'costo' => ['required', 'string', 'decimal:0,6'],
                ],
                [
                    'cantidad.required' => 'La cantidad es obligatoria.',
                    'cantidad.string' => 'La cantidad debe enviarse como cadena decimal.',
                    'cantidad.decimal' => 'La cantidad no es válida. Use un decimal positivo con hasta 3 decimales.',
                    'costo.required' => 'El costo es obligatorio.',
                    'costo.string' => 'El costo debe enviarse como cadena decimal.',
                    'costo.decimal' => 'El costo no es válido. Use un decimal no negativo con hasta 6 decimales.',
                ]
            )->errors();

            foreach ($fallos->toArray() as $campo => $mensajes) {
                foreach ((array) $mensajes as $mensaje) {
                    $agregar($campo, (string) $mensaje);
                }
            }

            $cantidadOk = ! $this->tieneError($errores, 'cantidad');
            $costoOk = ! $this->tieneError($errores, 'costo');

            // La cantidad viaja como cadena y la comparo con bccomp, nunca con
            // flotante: el proyecto lo prohíbe para cantidades sin excepciones.
            if ($cantidadOk && bccomp((string) $valores['cantidad'], '0', 3) <= 0) {
                $agregar('cantidad', 'La cantidad debe ser mayor que cero.');
                $cantidadOk = false;
            }

            if ($costoOk && bccomp((string) $valores['costo'], '0', 6) < 0) {
                $agregar('costo', 'El costo no puede ser negativo.');
                $costoOk = false;
            }

            // Rechazo la cantidad fraccionaria cuya unidad no admite decimales
            // (RF-134): es la misma guarda que la confirmación aplica al fondo
            // (AC-014), anticipada aquí para informar la fila exacta.
            $unidad = $producto?->unitOfMeasure;

            if ($cantidadOk && $unidad !== null && ! (bool) $unidad->allows_decimals) {
                $cantidad = (string) $valores['cantidad'];

                if (bccomp($cantidad, bcadd($cantidad, '0', 0), 3) !== 0) {
                    $agregar('cantidad', 'La unidad de medida no admite decimales.');
                    $cantidadOk = false;
                }
            }

            $valida = $errores === [];

            $resultado[] = [
                'fila' => $row['fila'],
                'valores' => [
                    'sku' => $valores['sku'],
                    'almacen' => $valores['almacen'],
                    'product_id' => $producto?->id,
                    'warehouse_id' => $almacen?->id,
                    // Normalizo a la escala de la columna para que la
                    // comparación canónica de idempotencia no distinga "12.5"
                    // de "12.500" en una reaplicación legítima.
                    'cantidad' => $cantidadOk ? bcadd((string) $valores['cantidad'], '0', 3) : $valores['cantidad'],
                    'costo' => $costoOk ? bcadd((string) $valores['costo'], '0', 6) : $valores['costo'],
                ],
                'sku' => $valores['sku'],
                'almacen' => $valores['almacen'],
                'valida' => $valida,
                'errores' => $errores,
            ];
        }

        return $resultado;
    }

    /**
     * @param  list<array{campo: string, motivo: string}>  $errores
     */
    private function tieneError(array $errores, string $campo): bool
    {
        foreach ($errores as $error) {
            if ($error['campo'] === $campo) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{fila: int, sku: ?string, almacen: ?string, valida: bool, errores: list<array{campo: string, motivo: string}>}>  $rows
     * @return array{archivo: string, total_filas: int, filas_validas: int, filas_invalidas: int, valida: bool, filas: list<array{fila: int, sku: ?string, almacen: ?string, valida: bool, errores: list<array{campo: string, motivo: string}>}>}
     */
    private function report(string $fileName, array $rows): array
    {
        $validas = 0;

        $filas = array_map(static function (array $row) use (&$validas): array {
            if ($row['valida']) {
                $validas++;
            }

            return [
                'fila' => $row['fila'],
                'sku' => $row['sku'],
                'almacen' => $row['almacen'],
                'valida' => $row['valida'],
                'errores' => $row['errores'],
            ];
        }, $rows);

        return [
            'archivo' => $fileName,
            'total_filas' => count($rows),
            'filas_validas' => $validas,
            'filas_invalidas' => count($rows) - $validas,
            'valida' => $validas === count($rows),
            'filas' => $filas,
        ];
    }
}
