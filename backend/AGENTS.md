# PROJECT KNOWLEDGE BASE — backend

**Generated:** 12/10/2026 · **Commit:** dec7683 · **Branch:** main

> Contexto del proyecto, convenciones globales, comandos de raíz y anti-patrones transversales: ver [`../AGENTS.md`](../AGENTS.md). Este documento cubre **solo la API Laravel**.

## OVERVIEW
API REST de Shadow-Net StockFlow: Laravel 13.35 sobre PHP 8.4 con MySQL 8.4 (InnoDB) y Sanctum en modo sesión de cookie para la SPA. Aplicación **solo API**: no hay vistas ni páginas web.

## STRUCTURE
```
backend/
├── app/
│   ├── Domain/Enums/PermissionSlug.php    # catálogo único de los 19 permisos
│   ├── Exceptions/OutsideWarehouseScopeException.php
│   ├── Http/
│   │   ├── Controllers/Api/V1/            # finos: traducen HTTP
│   │   ├── Requests/{Auth,Warehouse}/     # validación de forma
│   │   ├── Resources/                     # forma JSON de la v1
│   │   ├── Middleware/                    # EnsurePermission, EnsureAccountIsActive
│   │   └── Errors/ApiErrorResponse.php    # sobre de error (no es scaffold)
│   ├── Models/                            # User, Role, Permission, Warehouse
│   ├── Policies/                          # Warehouse, User, Role
│   └── Providers/AppServiceProvider.php   # Gates por slug + limitador de login
├── bootstrap/app.php                      # rutas, middleware y mapeo de errores
├── routes/api.php → routes/api/v1/*.php   # un archivo por módulo
├── database/{migrations,factories,seeders}
└── tests/{Feature,Unit}                   # Pest, contra MySQL real
```

## WHERE TO LOOK
| Tarea | Archivo |
| --- | --- |
| Añadir o cambiar una ruta | `routes/api/v1/<modulo>.php` (+ `require` en `routes/api.php`) |
| Añadir un permiso | `app/Domain/Enums/PermissionSlug.php`, luego la matriz en `database/seeders/RoleSeeder.php` |
| Autorizar una acción nueva | Policy en `app/Policies/` (auto-descubierta) o middleware `permission:<slug>` |
| Cambiar el sobre de error | `app/Http/Errors/ApiErrorResponse.php` + los render de `bootstrap/app.php` |
| Nueva tabla | `database/migrations/` siguiendo el orden de `docs/architecture/database-design.md` §7 |
| Semillas | `database/seeders/` en orden Permission → Role → DevAdmin |
| Pruebas | `tests/Feature/**`, nombradas `AC-###: <comportamiento>` |

## CONVENTIONS
- **Controlador fino:** valida con un FormRequest, autoriza con middleware o Policy y traduce el resultado con un Resource. Las transacciones y las reglas de negocio irán en servicios de dominio (Fase 3), nunca en el controlador.
- `declare(strict_types=1)` y tipos de retorno declarados; `casts()` para los tipos; `fillable` explícito y cerrado (por eso `is_active` y `last_login_at` no se asignan en masa).
- Cuando el código de error importa, la excepción lo transporta: `AuthorizationException('SIN_PERMISO' | 'CUENTA_INACTIVA')` y `OutsideWarehouseScopeException` se mapean a su código en `bootstrap/app.php`. No se inventan códigos nuevos fuera de la tabla de `docs/architecture/api-design.md` §5.
- Los Resources **nunca** exponen `password`, `remember_token` ni tokens.
- El alcance por almacén se resuelve **en la consulta** (`accessibleWarehouseIds()`), no filtrando el resultado en PHP.
- `hasPermission()` cachea los slugs por petición; `canAccessWarehouse()` **no** cachea la pertenencia, porque retirar una asignación debe surtir efecto en la petición siguiente (AC-011).

## ANTI-PATTERNS (backend)
- No crear `AuthServiceProvider`: los Gates viven en `AppServiceProvider`. No añadir `Gate::before`.
- No mover `app/Domain/`, `app/Http/Errors/` ni la excepción de alcance: no son scaffold de Laravel y las convenciones dependen de su sitio.
- No renombrar el pivote `warehouse_user` sin actualizar los dos modelos, la Policy y las pruebas.
- No devolver trazas, SQL ni mensajes de excepción al cliente: el 500 expone solo un `error_id` que sí queda registrado en el log.
- No añadir autenticación por token: hoy es solo sesión de cookie (`statefulApi`); los tokens con habilidades son para integraciones futuras.
- No usar `sqlite` para pruebas: `phpunit.xml` fija `stockflow_test` en MySQL.

## COMMANDS
```bash
composer install
./vendor/bin/pint --test                     # estilo (lo mismo que corre la CI)
php artisan test                             # REQUIERE el contenedor MySQL arriba
php artisan test --filter=AC-009             # un caso concreto
php artisan migrate --force
php artisan db:seed --force                  # permisos, roles y admin de desarrollo
```

