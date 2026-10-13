<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Aplico la importación de catálogo (EP-012) sobre las mismas reglas que la
// captura manual (RN-IMP-02): cada fila se valida contra CatalogRules y las
// guardas de dominio de ProductService, así que el archivo nunca relaja una
// regla del formulario. No guardo el archivo en disco: solo leo su contenido
// temporal y la auditoría conserva el nombre y los conteos (sin staging).
final class CatalogImportService
{
    // Columnas de la plantilla, en este orden y con estos encabezados exactos
    // (§7.13): el archivo que no las traiga tal cual se rechaza con 422.
    public const HEADERS = ['sku', 'nombre', 'categoria', 'unidad', 'descripcion', 'codigo_barras', 'costo', 'precio_venta', 'activo'];

    // Columnas obligatorias: el resto son opcionales.
    private const REQUIRED = ['sku', 'nombre', 'categoria', 'unidad'];

    // Límite de 5 MB [DECISIÓN de la Unidad 3]: el contrato exige un límite sin
    // dar la cifra y AC-066 pide rechazo uniforme con 422 (nunca invento un 413).
    public const MAX_FILE_BYTES = 5242880;

    // Límite de 1000 filas [DECISIÓN de la Unidad 3]: cabe en la poca RAM de la
    // máquina de referencia (riesgo R-10 del acta) y el CSV se lee nativo.
    public const MAX_ROWS = 1000;

    // Traduzco el campo del validador a la columna del archivo para que el
    // informe hable en los términos de la plantilla, no en los internos.
    private const FIELD_TO_COLUMN = [
        'sku' => 'sku',
        'name' => 'nombre',
        'description' => 'descripcion',
        'barcode' => 'codigo_barras',
        'category_id' => 'categoria',
        'unit_of_measure_id' => 'unidad',
        'cost_price' => 'costo',
        'sale_price' => 'precio_venta',
        'is_active' => 'activo',
    ];

    public function __construct(
        private readonly ProductService $products,
        private readonly CategoryService $categories,
        private readonly UnitOfMeasureService $units,
        private readonly AuditLogger $audit,
    ) {}

    // Construyo la plantilla con las columnas del contrato y una fila de
    // ejemplo marcada como tal: es ilustrativa y no debe importarse.
    public function buildTemplateSpreadsheet(): Spreadsheet
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('catalogo');

        $hoja->fromArray([self::HEADERS], null, 'A1');

        // Escribo la fila de ejemplo como cadenas explícitas porque el
        // binder por defecto convertiría "10.00" en el número 10 y la
        // plantilla debe mostrar los decimales como cadenas (§3).
        $ejemplo = [
            'EXAMPLE-001',
            'Producto de ejemplo (fila ilustrativa, no importar)',
            'Categoria de ejemplo',
            'UND',
            'Descripcion de ejemplo del producto',
            '7700000000000',
            '10.00',
            '15.00',
            'true',
        ];

        foreach (array_values($ejemplo) as $indice => $valor) {
            $hoja->setCellValueExplicit([$indice + 1, 2], $valor, DataType::TYPE_STRING);
        }

        foreach (range('A', 'I') as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }

        return $libro;
    }

    // Informo en seco sin escribir nada (AC-062): los conteos antes y después
    // son idénticos porque este camino nunca toca la base.
    /**
     * @return array{archivo: string, total_filas: int, filas_validas: int, filas_invalidas: int, valida: bool, filas: list<array{fila: int, sku: ?string, valida: bool, errores: list<array{campo: string, motivo: string}>}>}
     *
     * @throws ValidationException
     */
    public function dryRun(UploadedFile $file): array
    {
        $parsed = $this->parseUploadedFile($file);

        return $this->report($parsed['fileName'], $this->validateData($parsed['rows']));
    }

    // Aplico el archivo en UNA transacción (RN-IMP-01): si alguna fila falla,
    // lanzo la excepción con el informe y no se persiste nada (AC-063). El SKU
    // es la clave de idempotencia: el SKU existente se actualiza con las
    // columnas del archivo y nunca se duplica ni se renombra (AC-064).
    /**
     * @return array{archivo: string, filas_creadas: int, filas_actualizadas: int, filas_totales: int, auditoria_id: int}
     *
     * @throws ValidationException
     * @throws CatalogImportRowException
     */
    public function apply(UploadedFile $file, Request $request): array
    {
        $parsed = $this->parseUploadedFile($file);
        $rows = $this->validateData($parsed['rows']);

        $errores = [];

        foreach ($rows as $row) {
            foreach ($row['errores'] as $error) {
                $errores[] = ['fila' => $row['fila'], 'campo' => $error['campo'], 'motivo' => $error['motivo']];
            }
        }

        if ($errores !== []) {
            throw new CatalogImportRowException($errores);
        }

        return DB::transaction(function () use ($parsed, $rows, $request): array {
            $creadas = 0;
            $actualizadas = 0;

            foreach ($rows as $row) {
                $valores = $row['valores'];

                // La categoría se resuelve por nombre y se crea como raíz si no
                // existe: el archivo no trae jerarquía y la creación vive aquí,
                // dentro de la transacción, no en la validación.
                $categoria = $this->categories->resolveOrCreateRootByName($valores['categoria']);
                $unidad = $this->units->findByCode($valores['unidad']);

                // La unidad existe seguro aquí porque la validación ya la
                // exigió; si una carrera la borrara, la FK con RESTRICT decide.
                $datos = array_filter([
                    'name' => $valores['nombre'],
                    'description' => $valores['descripcion'],
                    'barcode' => $valores['codigo_barras'],
                    'category_id' => $categoria->id,
                    'unit_of_measure_id' => $unidad?->id,
                    'cost_price' => $valores['costo'],
                    'sale_price' => $valores['precio_venta'],
                    'is_active' => $valores['activo'],
                ], static fn (mixed $v): bool => $v !== null);

                $existente = Product::query()->where('sku', $valores['sku'])->first();

                if ($existente === null) {
                    $this->products->create(array_merge($datos, ['sku' => $valores['sku']]));
                    $creadas++;
                } else {
                    // Escribo solo las columnas mapeadas del archivo: el SKU es
                    // la clave de coincidencia y jamás se modifica.
                    $this->products->update($existente, $datos);
                    $actualizadas++;
                }
            }

            // Dejo una sola entrada por importación con autor, nombre del
            // archivo y conteos: nunca el contenido, las filas ni secretos.
            $entrada = $this->audit->record('importar_catalogo', 'importacion_catalogo', null, null, [
                'archivo' => $parsed['fileName'],
                'filas_totales' => count($rows),
                'filas_creadas' => $creadas,
                'filas_actualizadas' => $actualizadas,
            ], $request);

            return [
                'archivo' => $parsed['fileName'],
                'filas_creadas' => $creadas,
                'filas_actualizadas' => $actualizadas,
                'filas_totales' => count($rows),
                'auditoria_id' => $entrada->id,
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
                'archivo' => ['Las columnas del archivo no coinciden con la plantilla esperada (sku, nombre, categoria, unidad, descripcion, codigo_barras, costo, precio_venta, activo).'],
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
            // Evito la notación científica de los enteros grandes (p. ej. el
            // código de barras) formateando con decimales exactos y recorte.
            $texto = sprintf('%.10F', $valor);
            $texto = rtrim(rtrim($texto, '0'), '.');

            return $texto === '' ? '0' : $texto;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    // Valido cada fila contra las mismas reglas de la captura manual y reporto
    // TODAS las filas malas sin detenerme en la primera (RF-129): este método
    // no escribe nada, así sirve igual al dry-run y al apply previo.
    /**
     * @param  list<array{fila: int, valores: array<string, ?string>}>  $rows
     * @return list<array{fila: int, valores: array<string, mixed>, sku: ?string, valida: bool, errores: list<array{campo: string, motivo: string}>}>
     */
    private function validateData(array $rows): array
    {
        $skus = [];

        foreach ($rows as $row) {
            $sku = $row['valores']['sku'];
            $skus[mb_strtolower((string) $sku, 'UTF-8')][] = $row['fila'];
        }

        $porSku = Product::query()
            ->whereIn('sku', array_values(array_filter(array_map(static fn (array $r): ?string => $r['valores']['sku'], $rows))))
            ->get()
            ->keyBy('sku');

        $barrasArchivo = [];

        foreach ($rows as $row) {
            $barra = $row['valores']['codigo_barras'];

            if ($barra !== null) {
                $barrasArchivo[mb_strtolower($barra, 'UTF-8')][] = $row['fila'];
            }
        }

        $barrasBase = Product::query()
            ->whereNotNull('barcode')
            ->whereIn('barcode', array_values(array_filter(array_map(static fn (array $r): ?string => $r['valores']['codigo_barras'], $rows))))
            ->get(['id', 'sku', 'barcode'])
            ->keyBy(static fn (Product $p): string => mb_strtolower((string) $p->barcode, 'UTF-8'));

        $categorias = [];

        foreach ($rows as $row) {
            $nombre = $row['valores']['categoria'];

            if ($nombre !== null && ! array_key_exists(mb_strtolower($nombre, 'UTF-8'), $categorias)) {
                $categorias[mb_strtolower($nombre, 'UTF-8')] = Category::query()
                    ->where('name', $nombre)
                    ->orderBy('id')
                    ->first();
            }
        }

        $resultado = [];

        foreach ($rows as $row) {
            $valores = $row['valores'];
            $errores = [];

            $agregar = static function (string $campo, string $motivo) use (&$errores): void {
                $errores[] = ['campo' => $campo, 'motivo' => $motivo];
            };

            // El SKU duplicado dentro del mismo archivo es fila inválida: sin
            // esta guarda, la segunda aparición pisaría silenciosamente a la
            // primera dentro de la misma transacción.
            if ($valores['sku'] === null) {
                $agregar('sku', 'El SKU es obligatorio.');
            } elseif (count($skus[mb_strtolower($valores['sku'], 'UTF-8')] ?? []) > 1) {
                $agregar('sku', 'El SKU está duplicado dentro del archivo.');
            }

            if ($valores['nombre'] === null) {
                $agregar('nombre', 'El nombre es obligatorio.');
            }

            if ($valores['categoria'] === null) {
                $agregar('categoria', 'La categoría es obligatoria.');
            }

            $unidad = $valores['unidad'] !== null ? $this->units->findByCode($valores['unidad']) : null;

            if ($valores['unidad'] === null) {
                $agregar('unidad', 'La unidad de medida es obligatoria.');
            } elseif ($unidad === null) {
                // No creo unidades desde la importación: una errata crearía
                // silenciosamente una unidad y corrompería el catálogo.
                $agregar('unidad', 'La unidad de medida no existe. La importación no crea unidades de medida.');
            }

            // El código de barras es único cuando viene informado: duplicado en
            // el archivo o perteneciente a otro SKU es fila inválida.
            if ($valores['codigo_barras'] !== null) {
                if (count($barrasArchivo[mb_strtolower($valores['codigo_barras'], 'UTF-8')] ?? []) > 1) {
                    $agregar('codigo_barras', 'El código de barras está duplicado dentro del archivo.');
                } else {
                    $duena = $barrasBase[mb_strtolower($valores['codigo_barras'], 'UTF-8')] ?? null;

                    if ($duena !== null && $duena->sku !== $valores['sku']) {
                        $agregar('codigo_barras', 'El código de barras ya está en uso.');
                    }
                }
            }

            $activo = CatalogRules::parseActivo($valores['activo']);

            if ($activo === null) {
                $agregar('activo', 'El valor de activo no es válido. Use verdadero o falso.');
            }

            // Reutilizo las reglas de la captura manual sobre la carga
            // mapeada: el SKU existente es actualización (sin unique) y la
            // categoría aún inexistente se excluye porque nacerá como raíz en
            // el apply; todo lo demás valida idéntico al formulario.
            $existente = $valores['sku'] !== null ? $porSku->get($valores['sku']) : null;
            $categoriaDb = $valores['categoria'] !== null ? ($categorias[mb_strtolower($valores['categoria'], 'UTF-8')] ?? null) : null;

            $reglas = CatalogRules::productRules();
            $reglas['sku'] = ['required', 'string', 'max:'.CatalogRules::SKU_MAX];

            if ($existente !== null) {
                $reglas['barcode'] = ['nullable', 'string', 'max:'.CatalogRules::BARCODE_MAX, Rule::unique('products', 'barcode')->ignore($existente->id)];
            }

            if ($categoriaDb === null) {
                unset($reglas['category_id']);
            }

            if ($unidad === null) {
                unset($reglas['unit_of_measure_id']);
            }

            $carga = array_filter([
                'sku' => $valores['sku'],
                'name' => $valores['nombre'],
                'description' => $valores['descripcion'],
                'barcode' => $valores['codigo_barras'],
                'category_id' => $categoriaDb?->id,
                'unit_of_measure_id' => $unidad?->id,
                'cost_price' => $valores['costo'],
                'sale_price' => $valores['precio_venta'],
            ], static fn (mixed $v): bool => $v !== null);

            $fallos = Validator::make($carga, $reglas, CatalogRules::messages())->errors();

            foreach ($fallos->toArray() as $campo => $mensajes) {
                // Omito el duplicado de mensajes que ya reporté con texto
                // propio (sku y sus variantes), para no duplicar el motivo.
                if (in_array($campo, ['sku', 'name', 'barcode'], true)) {
                    $yaReportado = false;

                    foreach ($errores as $previo) {
                        if ($previo['campo'] === (self::FIELD_TO_COLUMN[$campo] ?? $campo)) {
                            $yaReportado = true;
                            break;
                        }
                    }

                    if ($yaReportado) {
                        continue;
                    }
                }

                foreach ((array) $mensajes as $mensaje) {
                    $agregar(self::FIELD_TO_COLUMN[$campo] ?? $campo, (string) $mensaje);
                }
            }

            // La categoría existente e inactiva rechaza igual que en la captura
            // manual: la guarda vive en ProductService y aquí la reutilizo.
            if ($categoriaDb !== null) {
                try {
                    $this->products->assertActiveCategory($categoriaDb->id);
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $mensajes) {
                        foreach ((array) $mensajes as $mensaje) {
                            $agregar('categoria', (string) $mensaje);
                        }
                    }
                }
            }

            $resultado[] = [
                'fila' => $row['fila'],
                'valores' => array_merge($valores, ['activo' => $activo]),
                'sku' => $valores['sku'],
                'valida' => $errores === [],
                'errores' => $errores,
            ];
        }

        return $resultado;
    }

    /**
     * @param  list<array{fila: int, sku: ?string, valida: bool, errores: list<array{campo: string, motivo: string}>}>  $rows
     * @return array{archivo: string, total_filas: int, filas_validas: int, filas_invalidas: int, valida: bool, filas: list<array{fila: int, sku: ?string, valida: bool, errores: list<array{campo: string, motivo: string}>}>}
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
