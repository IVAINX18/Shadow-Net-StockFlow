<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

// Pruebo la importación contra MySQL real porque la unicidad del SKU y del
// código, las FK con RESTRICT y la transacción atómica viven en el motor
// definitivo; SQLite no reproduciría el comportamiento transaccional.
uses(RefreshDatabase::class);

if (! function_exists('csvCatalogo')) {
    // Armo el CSV con los encabezados exactos del contrato porque el lector
    // exige las columnas en ese orden y con esos nombres.
    function csvCatalogo(array $filas): string
    {
        $lineas = ['sku,nombre,categoria,unidad,descripcion,codigo_barras,costo,precio_venta,activo'];

        foreach ($filas as $fila) {
            $celdas = [];

            foreach (['sku', 'nombre', 'categoria', 'unidad', 'descripcion', 'codigo_barras', 'costo', 'precio_venta', 'activo'] as $columna) {
                $valor = $fila[$columna] ?? '';
                $valor = str_replace('"', '""', (string) $valor);
                $celdas[] = str_contains($valor, ',') || str_contains($valor, '"') ? '"'.$valor.'"' : $valor;
            }

            $lineas[] = implode(',', $celdas);
        }

        return implode("\n", $lineas)."\n";
    }
}

if (! function_exists('archivoCsv')) {
    function archivoCsv(string $contenido, string $nombre = 'catalogo.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }
}

if (! function_exists('archivoXlsx')) {
    // Genero un XLSX real en un temporal porque el lector del servicio exige
    // un binario válido, no texto con otra extensión.
    function archivoXlsx(array $filas, string $nombre = 'catalogo.xlsx'): UploadedFile
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->fromArray([['sku', 'nombre', 'categoria', 'unidad', 'descripcion', 'codigo_barras', 'costo', 'precio_venta', 'activo']], null, 'A1');

        $linea = 2;

        foreach ($filas as $fila) {
            $hoja->fromArray([[
                $fila['sku'] ?? null,
                $fila['nombre'] ?? null,
                $fila['categoria'] ?? null,
                $fila['unidad'] ?? null,
                $fila['descripcion'] ?? null,
                $fila['codigo_barras'] ?? null,
                $fila['costo'] ?? null,
                $fila['precio_venta'] ?? null,
                $fila['activo'] ?? null,
            ]], null, "A{$linea}");
            $linea++;
        }

        $ruta = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new XlsxWriter($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return new UploadedFile($ruta, $nombre, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}

if (! function_exists('filaValida')) {
    // Armo una fila válida porque casi todas las pruebas parten del mismo alta
    // y solo varían un campo para provocar cada error.
    function filaValida(string $sku, string $unidad = 'UND', string $categoria = 'Ferretería'): array
    {
        return [
            'sku' => $sku,
            'nombre' => "Producto {$sku}",
            'categoria' => $categoria,
            'unidad' => $unidad,
            'descripcion' => "Descripción de {$sku}",
            'codigo_barras' => null,
            'costo' => '10.00',
            'precio_venta' => '15.00',
            'activo' => 'true',
        ];
    }
}

if (! function_exists('unidadBase')) {
    function unidadBase(): UnitOfMeasure
    {
        return UnitOfMeasure::factory()->create(['code' => 'UND', 'name' => 'Unidad']);
    }
}

// Criterio de salida de la fase (EP-012): 200 productos se validan, se aplican,
// quedan auditados y la reimportación no duplica por SKU (AC-062, AC-064, AC-065).
test('importación de 200 productos se valida, aplica, audita y es idempotente', function (): void {
    $admin = administradorCatalogo();
    actuarComo($admin);
    unidadBase();

    $filas = [];

    for ($i = 1; $i <= 200; $i++) {
        $filas[] = filaValida(sprintf('IMP-%04d', $i));
    }

    $contenido = csvCatalogo($filas);

    $validacion = $this->post('/api/v1/imports/catalog/validate', ['archivo' => archivoCsv($contenido)])
        ->assertOk()->json('data');

    expect($validacion['total_filas'])->toBe(200)
        ->and($validacion['filas_validas'])->toBe(200)
        ->and($validacion['valida'])->toBeTrue();

    $resumen = $this->post('/api/v1/imports/catalog', ['archivo' => archivoCsv($contenido)])
        ->assertCreated()->json('data');

    expect($resumen['filas_creadas'])->toBe(200)
        ->and($resumen['filas_actualizadas'])->toBe(0)
        ->and($resumen['filas_totales'])->toBe(200);

    expect(Product::count())->toBe(200);

    $reaplicacion = $this->post('/api/v1/imports/catalog', ['archivo' => archivoCsv($contenido)])
        ->assertCreated()->json('data');

    expect($reaplicacion['filas_creadas'])->toBe(0)
        ->and($reaplicacion['filas_actualizadas'])->toBe(200);
    expect(Product::count())->toBe(200);

    $entrada = AuditLog::query()
        ->where('action', 'importar_catalogo')
        ->where('entity_type', 'importacion_catalogo')
        ->firstOrFail();

    expect($entrada->user_id)->toBe($admin->id)
        ->and($entrada->entity_id)->toBeNull()
        ->and($entrada->new_values['archivo'] ?? null)->toBe('catalogo.csv')
        ->and($entrada->new_values['filas_creadas'] ?? null)->toBe(200)
        ->and($entrada->new_values['filas_actualizadas'] ?? null)->toBe(0);
});

// AC-062: el dry-run no modifica datos y los conteos antes y después coinciden.
test('AC-062: el dry-run deja los conteos idénticos', function (): void {
    actuarComo(administradorCatalogo());
    unidadBase();

    $antesProductos = Product::count();
    $antesCategorias = Category::count();

    $this->post('/api/v1/imports/catalog/validate', [
        'archivo' => archivoCsv(csvCatalogo([filaValida('DRY-001'), filaValida('DRY-002', 'UND', 'Categoría Nueva')])),
    ])->assertOk()->assertJsonPath('data.valida', true);

    expect(Product::count())->toBe($antesProductos);
    expect(Category::count())->toBe($antesCategorias);
});

// AC-063: una fila inválida impide toda la aplicación e informa fila, campo y motivo.
test('AC-063: una fila inválida impide aplicar e informa fila, campo y motivo', function (): void {
    actuarComo(administradorCatalogo());
    unidadBase();

    $filas = [filaValida('OK-001'), filaValida('MAL-001'), filaValida('OK-002')];
    $filas[1]['nombre'] = '';

    $respuesta = $this->post('/api/v1/imports/catalog', ['archivo' => archivoCsv(csvCatalogo($filas))]);

    $respuesta->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    $detalles = $respuesta->json('error.details');
    expect($detalles)->toHaveCount(1);
    expect($detalles[0]['fila'])->toBe(3);
    expect($detalles[0]['campo'])->toBe('nombre');
    expect($detalles[0]['motivo'])->toBeString()->not->toBe('');

    // El dry-run del mismo archivo también reporta la fila mala sin escribir.
    $reporte = $this->post('/api/v1/imports/catalog/validate', ['archivo' => archivoCsv(csvCatalogo($filas))])
        ->assertOk()->json('data');

    expect($reporte['valida'])->toBeFalse();
    expect($reporte['filas_invalidas'])->toBe(1);

    expect(Product::count())->toBe(0);
    expect(Category::count())->toBe(0);
});

// La importación nunca crea unidades: el código desconocido es fila inválida.
test('código de unidad desconocido es fila inválida y no crea la unidad', function (): void {
    actuarComo(administradorCatalogo());
    unidadBase();

    $unidadesAntes = UnitOfMeasure::count();

    $fila = filaValida('UNI-001', 'XXX');

    $respuesta = $this->post('/api/v1/imports/catalog', ['archivo' => archivoCsv(csvCatalogo([$fila]))]);

    $respuesta->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');
    expect($respuesta->json('error.details.0.campo'))->toBe('unidad');

    expect(UnitOfMeasure::count())->toBe($unidadesAntes);
    expect(Product::count())->toBe(0);
});

// El SKU duplicado dentro del mismo archivo es fila inválida.
test('SKU duplicado dentro del archivo es fila inválida', function (): void {
    actuarComo(administradorCatalogo());
    unidadBase();

    $respuesta = $this->post('/api/v1/imports/catalog', [
        'archivo' => archivoCsv(csvCatalogo([filaValida('DUP-001'), filaValida('DUP-001')])),
    ]);

    $respuesta->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    $campos = collect($respuesta->json('error.details'))->pluck('campo')->all();
    expect($campos)->toContain('sku');
    expect(Product::count())->toBe(0);
});

// La categoría inexistente se crea como raíz al aplicar.
test('categoría inexistente se crea como raíz al aplicar', function (): void {
    actuarComo(administradorCatalogo());
    unidadBase();

    $this->post('/api/v1/imports/catalog', [
        'archivo' => archivoCsv(csvCatalogo([filaValida('CAT-001', 'UND', 'Categoría Imp')])),
    ])->assertCreated();

    $categoria = Category::query()->where('name', 'Categoría Imp')->firstOrFail();

    expect($categoria->parent_id)->toBeNull();
    expect(Product::query()->where('sku', 'CAT-001')->firstOrFail()->category_id)->toBe($categoria->id);
});

// AC-066: el archivo sobre el límite de filas se rechaza con 422 y sin efectos.
test('AC-066: archivo sobre 1000 filas se rechaza con 422 y sin efectos', function (): void {
    actuarComo(administradorCatalogo());
    unidadBase();

    $filas = [];

    for ($i = 1; $i <= 1001; $i++) {
        $filas[] = filaValida(sprintf('LIM-%04d', $i));
    }

    $this->post('/api/v1/imports/catalog', ['archivo' => archivoCsv(csvCatalogo($filas))])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    expect(Product::count())->toBe(0);
    expect(Category::count())->toBe(0);
});

// Formato no soportado o codificación inválida se rechazan con 422.
test('formato no soportado o codificación inválida se rechazan con 422', function (): void {
    actuarComo(administradorCatalogo());
    unidadBase();

    $this->post('/api/v1/imports/catalog/validate', [
        'archivo' => UploadedFile::fake()->createWithContent('catalogo.txt', "sku,nombre\nA,B\n"),
    ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    // Construyo bytes que no son UTF-8 válido porque un texto latino ajeno
    // debe rechazarse antes de interpretar ninguna fila (RF-132).
    $this->post('/api/v1/imports/catalog/validate', [
        'archivo' => archivoCsv("\xff\xfe sku,nombre,categoria,unidad\n"),
    ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    expect(Product::count())->toBe(0);
});

// AC-061: la plantilla trae las columnas y la fila de ejemplo en ambos formatos.
test('AC-061: la plantilla csv y xlsx trae columnas y fila de ejemplo', function (): void {
    actuarComo(administradorCatalogo());

    $csv = $this->get('/api/v1/imports/catalog/template?format=csv')->assertOk();
    expect($csv->headers->get('Content-Type'))->toContain('text/csv');

    $lineas = preg_split('/\r\n|\r|\n/', (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv->streamedContent() ?: $csv->getContent()));
    // Comparo la cabecera parseada y no el texto crudo porque el escritor CSV
    // puede entrecomillar los campos sin cambiar su significado.
    expect(str_getcsv(trim((string) $lineas[0])))->toBe(['sku', 'nombre', 'categoria', 'unidad', 'descripcion', 'codigo_barras', 'costo', 'precio_venta', 'activo']);
    expect((string) $lineas[1])->toContain('EXAMPLE-001');

    $xlsx = $this->get('/api/v1/imports/catalog/template?format=xlsx')->assertOk();
    expect($xlsx->headers->get('Content-Type'))->toContain('spreadsheetml');

    $temporal = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($temporal, $xlsx->streamedContent() ?: $xlsx->getContent());

    $lector = IOFactory::createReader('Xlsx');
    $lector->setReadDataOnly(true);
    $datos = $lector->load($temporal)->getActiveSheet()->toArray(null, true, true, false);
    unlink($temporal);

    expect(array_values($datos[0]))->toBe(['sku', 'nombre', 'categoria', 'unidad', 'descripcion', 'codigo_barras', 'costo', 'precio_venta', 'activo']);
    expect((string) $datos[1][0])->toBe('EXAMPLE-001');

    $this->get('/api/v1/imports/catalog/template?format=pdf')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// El XLSX válido se valida y se aplica igual que el CSV.
test('archivo xlsx válido se valida y aplica', function (): void {
    actuarComo(administradorCatalogo());
    unidadBase();

    $filas = [filaValida('XLS-001'), filaValida('XLS-002')];

    $this->post('/api/v1/imports/catalog/validate', ['archivo' => archivoXlsx($filas)])
        ->assertOk()->assertJsonPath('data.valida', true);

    $this->post('/api/v1/imports/catalog', ['archivo' => archivoXlsx($filas)])
        ->assertCreated()->assertJsonPath('data.filas_creadas', 2);

    expect(Product::count())->toBe(2);
});

// AC-070: sin sesión hay 401 y sin catalog.create hay 403 SIN_PERMISO.
test('AC-070: importar sin sesión da 401 y sin permiso da 403', function (): void {
    unidadBase();
    $contenido = csvCatalogo([filaValida('SEG-001')]);

    $this->app['auth']->forgetGuards();

    $this->post('/api/v1/imports/catalog/validate', ['archivo' => archivoCsv($contenido)])
        ->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->post('/api/v1/imports/catalog', ['archivo' => archivoCsv($contenido)])
        ->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->get('/api/v1/imports/catalog/template?format=csv')
        ->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');

    actuarComo(usuarioConPermisos([]));

    $this->post('/api/v1/imports/catalog/validate', ['archivo' => archivoCsv($contenido)])
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');
    $this->post('/api/v1/imports/catalog', ['archivo' => archivoCsv($contenido)])
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');
    $this->get('/api/v1/imports/catalog/template?format=csv')
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');

    expect(Product::count())->toBe(0);
});

// La importación respeta las reglas de la captura manual: categoría inactiva y
// código de barras duplicado en base son filas inválidas (RN-IMP-02).
test('RN-IMP-02: categoría inactiva y barras duplicadas son filas inválidas', function (): void {
    actuarComo(administradorCatalogo());
    $unidad = unidadBase();

    $inactiva = Category::factory()->create(['is_active' => false, 'name' => 'Inactiva Imp']);

    $respuesta = $this->post('/api/v1/imports/catalog', [
        'archivo' => archivoCsv(csvCatalogo([filaValida('RN-001', 'UND', 'Inactiva Imp')])),
    ]);

    $respuesta->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');
    expect(collect($respuesta->json('error.details'))->pluck('campo')->all())->toContain('categoria');

    $this->postJson('/api/v1/products', [
        'sku' => 'BASE-001',
        'name' => 'Producto base',
        'barcode' => '7700000000001',
        'category_id' => Category::factory()->create()->id,
        'unit_of_measure_id' => $unidad->id,
    ])->assertCreated();

    $duplicada = filaValida('RN-002');
    $duplicada['codigo_barras'] = '7700000000001';

    $this->post('/api/v1/imports/catalog', ['archivo' => archivoCsv(csvCatalogo([$duplicada]))])
        ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    // La reimportación actualiza los datos del SKU existente sin duplicarlo.
    $cambio = filaValida('BASE-001');
    $cambio['nombre'] = 'Producto base renombrado';
    $cambio['categoria'] = Category::factory()->create()->name;

    $this->post('/api/v1/imports/catalog', ['archivo' => archivoCsv(csvCatalogo([$cambio]))])
        ->assertCreated()
        ->assertJsonPath('data.filas_creadas', 0)
        ->assertJsonPath('data.filas_actualizadas', 1);

    expect(Product::query()->where('sku', 'BASE-001')->firstOrFail()->name)->toBe('Producto base renombrado');
});
