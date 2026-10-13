<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\UnitOfMeasure;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Pruebo las unidades contra MySQL real porque la unicidad del código vive en
// el motor con uq_units_of_measure_code (estrategia del §7).
uses(RefreshDatabase::class);

// RF-031: crear una unidad con código único responde 201.
test('crear unidad con código nuevo responde 201', function (): void {
    actuarComo(administradorCatalogo());

    $this->postJson('/api/v1/units', [
        'code' => 'CJ',
        'name' => 'Caja',
        'allows_decimals' => false,
    ])->assertCreated()
        ->assertJsonPath('data.code', 'CJ')
        ->assertJsonPath('data.name', 'Caja')
        ->assertJsonPath('data.allows_decimals', false);

    $this->assertDatabaseHas('units_of_measure', ['code' => 'CJ']);
});

// El código duplicado se rechaza con 422.
test('crear con código duplicado responde 422', function (): void {
    actuarComo(administradorCatalogo());
    UnitOfMeasure::factory()->create(['code' => 'UND']);

    $this->postJson('/api/v1/units', ['code' => 'UND', 'name' => 'Otra unidad'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// El código es obligatorio y de máximo 20 caracteres; el nombre es obligatorio.
test('código ausente o largo y nombre ausente responden 422', function (): void {
    actuarComo(administradorCatalogo());

    $this->postJson('/api/v1/units', ['name' => 'Sin código'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    $this->postJson('/api/v1/units', ['code' => str_repeat('X', 21), 'name' => 'Largo'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');

    $this->postJson('/api/v1/units', ['code' => 'SC'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDACION');
});

// El listado pagina y ordena por los campos del §7.6.
test('listar pagina y ordena por código y nombre', function (): void {
    actuarComo(administradorCatalogo());

    UnitOfMeasure::factory()->create(['code' => 'ZZZ', 'name' => 'Zeta']);
    UnitOfMeasure::factory()->create(['code' => 'AAA', 'name' => 'Alfa']);
    UnitOfMeasure::factory()->create(['code' => 'MMM', 'name' => 'Medio']);

    $pagina = $this->getJson('/api/v1/units?per_page=2')->assertOk()->json();
    expect($pagina['data'])->toHaveCount(2);
    expect($pagina['meta'])->toHaveKeys(['current_page', 'per_page', 'total', 'last_page']);
    expect($pagina['meta']['total'])->toBe(3);

    $porCodigo = collect($this->getJson('/api/v1/units?sort=code&per_page=100')->assertOk()->json('data'))
        ->pluck('code')->all();
    expect($porCodigo)->toBe(['AAA', 'MMM', 'ZZZ']);

    $porNombreDesc = collect($this->getJson('/api/v1/units?sort=-name&per_page=100')->assertOk()->json('data'))
        ->pluck('name')->all();
    expect($porNombreDesc)->toBe(['Zeta', 'Medio', 'Alfa']);
});

// Un `sort` fuera de la lista cerrada responde 422.
test('orden no documentado en unidades responde 422', function (): void {
    actuarComo(administradorCatalogo());

    $this->getJson('/api/v1/units?sort=allows_decimals')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// El detalle responde la unidad; el id inexistente da 404.
test('detalle responde la unidad y el inexistente da 404', function (): void {
    actuarComo(administradorCatalogo());

    $unidad = UnitOfMeasure::factory()->create(['code' => 'KG', 'name' => 'Kilogramo', 'allows_decimals' => true]);

    $this->getJson("/api/v1/units/{$unidad->id}")->assertOk()
        ->assertJsonPath('data.code', 'KG')
        ->assertJsonPath('data.allows_decimals', true);

    $this->getJson('/api/v1/units/999999')->assertNotFound()
        ->assertJsonPath('error.code', 'NO_ENCONTRADO');
});

// Matriz: sin sesión hay 401; con sesión pero sin permiso hay 403 en el alta.
test('matriz de unidades: anónimo 401 y sin permiso 403 en el alta', function (): void {
    $unidad = UnitOfMeasure::factory()->create();

    $this->getJson('/api/v1/units')->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->getJson("/api/v1/units/{$unidad->id}")->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->postJson('/api/v1/units', ['code' => 'X1', 'name' => 'X'])
        ->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');

    $lector = usuarioConPermisos([]);
    actuarComo($lector);

    $this->postJson('/api/v1/units', ['code' => 'X1', 'name' => 'X'])
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');

    // Las lecturas con sesión valen sin permiso de catálogo.
    $this->getJson('/api/v1/units')->assertOk();
    $this->getJson("/api/v1/units/{$unidad->id}")->assertOk();
});

// La bitácora registra el alta de la unidad con su entidad.
test('auditoría: el alta de unidad deja acción y entidad', function (): void {
    $admin = administradorCatalogo();
    actuarComo($admin);

    $id = $this->postJson('/api/v1/units', ['code' => 'LT', 'name' => 'Litro'])->assertCreated()->json('data.id');

    $entrada = AuditLog::query()->where('entity_type', 'unit_of_measure')->where('entity_id', $id)->firstOrFail();

    expect($entrada->action)->toBe('crear_unidad')
        ->and($entrada->user_id)->toBe($admin->id)
        ->and($entrada->new_values['code'] ?? null)->toBe('LT');
});

// RF-032: las tres unidades base se siembran de forma idempotente.
test('semilla de unidades base es idempotente', function (): void {
    $this->seed(UnitOfMeasureSeeder::class);
    $this->seed(UnitOfMeasureSeeder::class);

    expect(UnitOfMeasure::query()->whereIn('code', ['UND', 'KG', 'LT'])->count())->toBe(3);
    expect(UnitOfMeasure::query()->count())->toBe(3);
});
