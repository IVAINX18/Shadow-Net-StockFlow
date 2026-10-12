<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Uso RefreshDatabase contra MySQL real porque la autenticación por sesión de
// esta unidad debe probarse sobre el motor definitivo, no en memoria.
uses(RefreshDatabase::class);

// Simulo la SPA propia enviando Referer de un dominio stateful, que es como
// Sanctum decide iniciar la sesión por cookie; sin esa cabecera la API no ve
// sesión y el login falla aunque las credenciales sean correctas.
function cabecerasSpa(): array
{
    return ['Referer' => 'http://localhost:8000'];
}

beforeEach(function (): void {
    // Omito solo el CSRF porque el flujo real lo obtiene de
    // /sanctum/csrf-cookie y aquí me interesa la sesión, no el token.
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

// Creo el usuario con rol, permiso y almacén para que el recurso devuelva las
// tres colecciones y las aserciones cubran el contrato completo del §7.1.
function crearUsuarioAutenticable(array $atributos = []): User
{
    $usuario = User::factory()->create(array_merge([
        'password' => bcrypt('Secreto123'),
        'is_active' => true,
    ], $atributos));

    $permiso = Permission::factory()->create([
        'slug' => 'users.view',
        'module' => 'users',
    ]);
    $rol = Role::factory()->create();
    $rol->permissions()->attach($permiso);
    $usuario->roles()->attach($rol);

    $almacen = Warehouse::factory()->create();
    $usuario->warehouses()->attach($almacen);

    return $usuario->fresh();
}

// Conservo aquí la ayuda de sesión delegando al TestCase, que es quien puede
// tocar la jarra de cookies y los guardias sin pelear con el tipado de Pest.
function conservarSesion(object $caso, object $respuesta): void
{
    $caso->conservarSesion($respuesta);
}

test('AC-001: con credenciales válidas inicia sesión y su perfil queda accesible', function (): void {
    crearUsuarioAutenticable(['email' => 'ac001@example.com']);

    $respuesta = $this->postJson('/api/v1/auth/login', [
        'email' => 'ac001@example.com',
        'password' => 'Secreto123',
    ], cabecerasSpa());

    $respuesta->assertOk();
    $respuesta->assertJsonPath('user.email', 'ac001@example.com');
    $respuesta->assertJsonStructure([
        'user' => ['id', 'name', 'email', 'is_active', 'roles', 'permissions', 'warehouses'],
    ]);

    conservarSesion($this, $respuesta);

    $perfil = $this->getJson('/api/v1/auth/me', cabecerasSpa());
    $perfil->assertOk();
    // Exijo la misma forma que el login: el perfil tambien viaja dentro de "user".
    $perfil->assertJsonPath('user.email', 'ac001@example.com');
    $perfil->assertJsonStructure([
        'user' => ['id', 'name', 'email', 'is_active', 'roles', 'permissions', 'warehouses'],
    ]);
});

test('AC-002: al cerrar sesión la sesión queda invalidada y el perfil exige login', function (): void {
    crearUsuarioAutenticable(['email' => 'ac002@example.com']);

    $ingreso = $this->postJson('/api/v1/auth/login', [
        'email' => 'ac002@example.com',
        'password' => 'Secreto123',
    ], cabecerasSpa());
    $ingreso->assertOk();
    conservarSesion($this, $ingreso);

    $salida = $this->postJson('/api/v1/auth/logout', [], cabecerasSpa());
    $salida->assertNoContent();
    conservarSesion($this, $salida);

    $this->getJson('/api/v1/auth/me', cabecerasSpa())
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');
});

test('AC-004: la cuenta desactivada con clave correcta recibe 403 sin operar', function (): void {
    crearUsuarioAutenticable(['email' => 'ac004@example.com', 'is_active' => false]);

    $respuesta = $this->postJson('/api/v1/auth/login', [
        'email' => 'ac004@example.com',
        'password' => 'Secreto123',
    ], cabecerasSpa());
    $respuesta->assertForbidden();
    $respuesta->assertJsonPath('error.code', 'CUENTA_INACTIVA');
    conservarSesion($this, $respuesta);

    $this->getJson('/api/v1/auth/me', cabecerasSpa())
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');
});

test('AC-004: la cuenta desactivada con clave errónea recibe 401 para no delatarla', function (): void {
    crearUsuarioAutenticable(['email' => 'ac004b@example.com', 'is_active' => false]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'ac004b@example.com',
        'password' => 'ClaveEquivocada',
    ], cabecerasSpa())
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO');
});

test('con credenciales inválidas responde 401 con el sobre uniforme', function (): void {
    crearUsuarioAutenticable(['email' => 'invalido@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'invalido@example.com',
        'password' => 'NoEsEsta',
    ], cabecerasSpa())
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO')
        ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
});

test('sin cuerpo válido responde 422 con un detalle por campo', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'no-es-email'], cabecerasSpa())
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDACION');

    $cuerpo = $this->postJson('/api/v1/auth/login', ['email' => 'no-es-email'], cabecerasSpa())->json();
    expect($cuerpo['error']['details'])->not->toBeEmpty();

    foreach ($cuerpo['error']['details'] as $detalle) {
        expect($detalle)->toHaveKeys(['field', 'code', 'message']);
    }
});

test('el perfil sin sesión responde 401 con el sobre uniforme', function (): void {
    $this->getJson('/api/v1/auth/me', cabecerasSpa())
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'NO_AUTENTICADO')
        ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
});

test('al sexto intento en un minuto responde 429 con el sobre uniforme', function (): void {
    crearUsuarioAutenticable(['email' => 'limite@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'limite@example.com',
            'password' => 'ClaveEquivocada',
        ], cabecerasSpa())->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'limite@example.com',
        'password' => 'ClaveEquivocada',
    ], cabecerasSpa())
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'LIMITE_EXCEDIDO');
});

test('ni el login ni el perfil exponen secretos', function (): void {
    crearUsuarioAutenticable(['email' => 'secretos@example.com']);

    $ingreso = $this->postJson('/api/v1/auth/login', [
        'email' => 'secretos@example.com',
        'password' => 'Secreto123',
    ], cabecerasSpa());
    $ingreso->assertOk();
    $login = $ingreso->json();
    conservarSesion($this, $ingreso);

    $perfil = $this->getJson('/api/v1/auth/me', cabecerasSpa())->assertOk()->json();

    foreach ([$login, $perfil] as $cuerpo) {
        $plano = json_encode($cuerpo);
        expect($plano)->not->toContain('password');
        expect($cuerpo)->not->toHaveKey('token');
        $usuario = $cuerpo['user'] ?? $cuerpo['data'];
        expect($usuario)->not->toHaveKey('password');
        expect($usuario)->not->toHaveKey('remember_token');
        expect($usuario)->not->toHaveKey('token');
    }
});
