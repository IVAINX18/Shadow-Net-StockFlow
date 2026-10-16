<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Category;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Pruebo el catálogo contra MySQL real porque la unicidad del slug, la FK
// jerárquica con RESTRICT y la bitácora viven en el motor definitivo.
uses(RefreshDatabase::class);

// El ayudante administradorCatalogo vive en tests/Pest.php porque ProductTest y
// CatalogImportTest también lo usan y debe existir al correr un módulo aislado.

// RF-026: crear una categoría raíz responde 201 y la deja consultable.
test('crear categoría raíz responde 201 y queda consultable', function (): void {
    actuarComo(administradorCatalogo());

    $respuesta = $this->postJson('/api/v1/categories', [
        'name' => 'Electrónica',
        'slug' => 'electronica',
    ]);

    $respuesta->assertCreated()
        ->assertJsonPath('data.name', 'Electrónica')
        ->assertJsonPath('data.slug', 'electronica')
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.is_active', true);

    $id = $respuesta->json('data.id');

    $this->getJson("/api/v1/categories/{$id}")->assertOk()
        ->assertJsonPath('data.name', 'Electrónica');
});

// AC-012: la subcategoría creada bajo un padre aparece en su posición.
test('AC-012: la subcategoría aparece como hija en el detalle del padre', function (): void {
    actuarComo(administradorCatalogo());

    $padre = $this->postJson('/api/v1/categories', ['name' => 'Herramientas'])->json('data');

    $hija = $this->postJson('/api/v1/categories', [
        'name' => 'Manuales',
        'parent_id' => $padre['id'],
    ])->assertCreated()->json('data');

    expect($hija['parent_id'])->toBe($padre['id']);

    $detalle = $this->getJson("/api/v1/categories/{$padre['id']}")->assertOk()->json('data');

    expect(collect($detalle['children'])->pluck('id')->all())->toContain($hija['id']);
});

// RF-029: actualizar nombre y posición jerárquica de una categoría.
test('actualizar nombre y padre mueve la categoría', function (): void {
    actuarComo(administradorCatalogo());

    $origen = Category::factory()->create(['name' => 'Origen']);
    $destino = Category::factory()->create(['name' => 'Destino']);
    $nodo = Category::factory()->create(['name' => 'Nodo', 'parent_id' => $origen->id]);

    $this->putJson("/api/v1/categories/{$nodo->id}", [
        'name' => 'Nodo Renombrado',
        'parent_id' => $destino->id,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Nodo Renombrado')
        ->assertJsonPath('data.parent_id', $destino->id);

    expect($nodo->fresh()->parent_id)->toBe($destino->id);
});

// El ciclo directo (ser su propio padre) se rechaza con 422 por campo.
test('asignarse como propio padre responde 422', function (): void {
    actuarComo(administradorCatalogo());

    $nodo = Category::factory()->create();

    $this->putJson("/api/v1/categories/{$nodo->id}", ['parent_id' => $nodo->id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.field', 'parent_id');
});

// El ciclo indirecto (padre descendiente) se rechaza con 422 por campo.
test('asignar como padre a un descendiente responde 422', function (): void {
    actuarComo(administradorCatalogo());

    $raiz = Category::factory()->create();
    $hija = Category::factory()->create(['parent_id' => $raiz->id]);
    $nieta = Category::factory()->create(['parent_id' => $hija->id]);

    $this->putJson("/api/v1/categories/{$raiz->id}", ['parent_id' => $nieta->id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    expect($raiz->fresh()->parent_id)->toBeNull();
});

// El árbol se acota a 3 niveles: colgar de una nieta (nivel 3) da 422.
test('crear bajo una categoría de nivel 3 responde 422 por profundidad', function (): void {
    actuarComo(administradorCatalogo());

    $raiz = Category::factory()->create();
    $hija = Category::factory()->create(['parent_id' => $raiz->id]);
    $nieta = Category::factory()->create(['parent_id' => $hija->id]);

    $this->postJson('/api/v1/categories', [
        'name' => 'Bisnieta',
        'parent_id' => $nieta->id,
    ])->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION')
        ->assertJsonPath('error.details.0.field', 'parent_id');
});

// Mover una rama entera que desbordaría el tope también da 422.
test('mover una rama que desborda los 3 niveles responde 422', function (): void {
    actuarComo(administradorCatalogo());

    $rama = Category::factory()->create();
    $ramaHija = Category::factory()->create(['parent_id' => $rama->id]);

    $otra = Category::factory()->create();
    $otraHija = Category::factory()->create(['parent_id' => $otra->id]);

    // La rama (altura 2) bajo otraHija (nivel 2) llegaría al nivel 4.
    $this->putJson("/api/v1/categories/{$rama->id}", ['parent_id' => $otraHija->id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    expect($rama->fresh()->parent_id)->toBeNull();
    expect($ramaHija->fresh()->parent_id)->toBe($rama->id);
});

// El slug es único cuando viene informado, pero varios NULL conviven.
test('slug duplicado da 422 y varios slug nulos conviven', function (): void {
    actuarComo(administradorCatalogo());

    $this->postJson('/api/v1/categories', ['name' => 'A', 'slug' => 'duplicado'])->assertCreated();
    $this->postJson('/api/v1/categories', ['name' => 'B', 'slug' => 'duplicado'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $this->postJson('/api/v1/categories', ['name' => 'C'])->assertCreated();
    $this->postJson('/api/v1/categories', ['name' => 'D'])->assertCreated();

    $this->assertDatabaseCount('categories', 3);
});

// RF-030: desactivar con hijas activas da 409 ESTADO_INVALIDO.
test('desactivar con subcategorías activas responde 409 ESTADO_INVALIDO', function (): void {
    actuarComo(administradorCatalogo());

    $padre = Category::factory()->create(['is_active' => true]);
    Category::factory()->create(['parent_id' => $padre->id, 'is_active' => true]);

    $this->postJson("/api/v1/categories/{$padre->id}/deactivate")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');

    expect($padre->fresh()->is_active)->toBeTrue();
});

// RF-030: desactivar una hoja la apaga y la segunda vez da 409.
test('desactivar una hoja la apaga y repetir da 409', function (): void {
    actuarComo(administradorCatalogo());

    $hoja = Category::factory()->create(['is_active' => true]);

    $this->postJson("/api/v1/categories/{$hoja->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($hoja->fresh()->is_active)->toBeFalse();

    $this->postJson("/api/v1/categories/{$hoja->id}/deactivate")
        ->assertConflict()
        ->assertJsonPath('error.code', 'ESTADO_INVALIDO');
});

// El listado pagina, filtra, busca y ordena según el §7.5.
test('listar pagina con filtros de estado y padre, búsqueda y orden', function (): void {
    actuarComo(administradorCatalogo());

    $padre = Category::factory()->create(['name' => 'Pinturas']);
    Category::factory()->create(['name' => 'Pintura acrílica', 'parent_id' => $padre->id]);
    Category::factory()->create(['name' => 'Pinceles', 'parent_id' => $padre->id]);
    Category::factory()->create(['name' => 'Tornillos', 'is_active' => false]);

    $pagina = $this->getJson('/api/v1/categories?per_page=2')->assertOk()->json();
    expect($pagina['data'])->toHaveCount(2);
    expect($pagina['meta'])->toHaveKeys(['current_page', 'per_page', 'total', 'last_page']);
    expect($pagina['meta']['total'])->toBe(4);
    expect($pagina)->toHaveKey('links');

    $inactivas = collect($this->getJson('/api/v1/categories?is_active=0')->assertOk()->json('data'))
        ->pluck('name')->all();
    expect($inactivas)->toBe(['Tornillos']);

    $hijas = collect($this->getJson("/api/v1/categories?parent_id={$padre->id}")->assertOk()->json('data'))
        ->pluck('name')->all();
    expect($hijas)->toContain('Pintura acrílica', 'Pinceles');

    $busqueda = collect($this->getJson('/api/v1/categories?q=pintu')->assertOk()->json('data'))
        ->pluck('name')->all();
    expect($busqueda)->toContain('Pinturas', 'Pintura acrílica');

    $orden = collect($this->getJson('/api/v1/categories?sort=-name&per_page=100')->assertOk()->json('data'))
        ->pluck('name')->all();
    $esperado = $orden;
    rsort($esperado);
    expect($orden)->toBe($esperado);
});

// Un `sort` fuera de la lista cerrada responde 422, no 500.
test('orden no documentado responde 422 VALIDACION', function (): void {
    actuarComo(administradorCatalogo());

    $this->getJson('/api/v1/categories?sort=slug')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');
});

// Matriz: sin sesión hay 401; con sesión pero sin permiso hay 403.
test('matriz: lecturas anónimas dan 401 y escrituras sin permiso dan 403', function (): void {
    $nodo = Category::factory()->create();

    $this->getJson('/api/v1/categories')->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->getJson("/api/v1/categories/{$nodo->id}")->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');
    $this->postJson('/api/v1/categories', ['name' => 'X'])->assertUnauthorized()->assertJsonPath('error.code', 'NO_AUTENTICADO');

    $lector = usuarioConPermisos([]);
    actuarComo($lector);

    $this->postJson('/api/v1/categories', ['name' => 'X'])
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');
    $this->putJson("/api/v1/categories/{$nodo->id}", ['name' => 'Y'])
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');
    $this->postJson("/api/v1/categories/{$nodo->id}/deactivate")
        ->assertForbidden()->assertJsonPath('error.code', 'SIN_PERMISO');

    // Las lecturas con sesión valen sin permiso de catálogo.
    $this->getJson('/api/v1/categories')->assertOk();
    $this->getJson("/api/v1/categories/{$nodo->id}")->assertOk();
});

// La bitácora registra el alta con acción, entidad y valores nuevos.
test('auditoría: el alta deja entrada con acción, entidad y valores', function (): void {
    $admin = administradorCatalogo();
    actuarComo($admin);

    $id = $this->postJson('/api/v1/categories', ['name' => 'Auditada'])->assertCreated()->json('data.id');

    $entrada = AuditLog::query()->where('entity_type', 'category')->where('entity_id', $id)->firstOrFail();

    expect($entrada->action)->toBe('crear_categoria')
        ->and($entrada->user_id)->toBe($admin->id)
        ->and($entrada->new_values['name'] ?? null)->toBe('Auditada')
        ->and($entrada->old_values)->toBeNull();
});

// La bitácora registra la edición con el antes y el después.
test('auditoría: la edición deja valores viejos y nuevos', function (): void {
    actuarComo(administradorCatalogo());

    $nodo = Category::factory()->create(['name' => 'Antes']);
    AuditLog::query()->delete();

    $this->putJson("/api/v1/categories/{$nodo->id}", ['name' => 'Después'])->assertOk();

    $entrada = AuditLog::query()->where('action', 'actualizar_categoria')->firstOrFail();

    expect($entrada->entity_id)->toBe($nodo->id)
        ->and($entrada->old_values['name'] ?? null)->toBe('Antes')
        ->and($entrada->new_values['name'] ?? null)->toBe('Después');
});

// La bitácora registra la desactivación con el cambio de estado.
test('auditoría: la desactivación deja el cambio de estado', function (): void {
    actuarComo(administradorCatalogo());

    $hoja = Category::factory()->create(['is_active' => true]);
    AuditLog::query()->delete();

    $this->postJson("/api/v1/categories/{$hoja->id}/deactivate")->assertOk();

    $entrada = AuditLog::query()->where('action', 'desactivar_categoria')->firstOrFail();

    expect($entrada->entity_id)->toBe($hoja->id)
        ->and($entrada->old_values['is_active'] ?? null)->toBeTrue()
        ->and($entrada->new_values['is_active'] ?? null)->toBeFalse();
});

// C-10: la bitácora jamás persiste secretos aunque el llamante los incluya.
test('auditoría: los secretos nunca quedan en old_values ni new_values', function (): void {
    actuarComo(administradorCatalogo());

    $logger = app(AuditLogger::class);

    $entrada = $logger->record(
        'crear_categoria',
        'category',
        1,
        ['name' => 'Vieja', 'password' => 'secreto', 'token' => 'abc'],
        ['name' => 'Nueva', 'password' => 'secreto', 'remember_token' => 'xyz'],
    );

    $plano = json_encode(['old' => $entrada->old_values, 'new' => $entrada->new_values]);

    expect($plano)->not->toContain('secreto', 'abc', 'xyz', 'password', 'remember_token', 'token');
    expect($entrada->new_values['name'] ?? null)->toBe('Nueva');
});
