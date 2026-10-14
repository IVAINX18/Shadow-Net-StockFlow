# PROJECT KNOWLEDGE BASE — backend

**Updated:** 13/10/2026 · **Commit:** 804f060 · **Branch:** main

> Contexto del proyecto, convenciones globales, comandos de raíz y anti-patrones transversales: ver [`../AGENTS.md`](../AGENTS.md). Este documento cubre **solo la API Laravel**.

## OVERVIEW
API REST de Shadow-Net StockFlow: Laravel 13.35 sobre PHP 8.4 con MySQL 8.4 (InnoDB) y Sanctum en modo sesión de cookie para la SPA. Aplicación **solo API**: no hay vistas ni páginas web. Implementadas las fases 1 y 2 (RBAC con almacenes y catálogo con importación); la Fase 3 (inventario y kardex) es el siguiente paso.

## STRUCTURE
```
backend/
├── app/
│   ├── Domain/Enums/PermissionSlug.php    # catálogo único de los 19 permisos
│   ├── Exceptions/OutsideWarehouseScopeException.php
│   ├── Http/
│   │   ├── Controllers/Api/V1/            # finos: traducen HTTP
│   │   ├── Requests/{Auth,Catalog,Role,User,Warehouse}/  # validación de forma
│   │   ├── Resources/                     # forma JSON de la v1
│   │   ├── Middleware/                    # EnsurePermission, EnsureAccountIsActive
│   │   └── Errors/ApiErrorResponse.php    # sobre de error (no es scaffold)
│   ├── Models/                            # User, Role, Permission, Warehouse, Category, UnitOfMeasure, Product, ProductWarehouse, AuditLog
│   ├── Policies/                          # Warehouse, User, Role
│   ├── Services/
│   │   ├── Audit/AuditLogger.php          # bitácora append-only
│   │   └── Catalog/                       # CategoryService, UnitOfMeasureService, ProductService, CatalogRules, CatalogImportService
│   └── Providers/AppServiceProvider.php   # Gates por slug + limitador de login
├── bootstrap/app.php                      # rutas, middleware y mapeo de errores
├── routes/api.php → routes/api/v1/*.php   # un archivo por módulo (auth, warehouses, users, roles, catalog, imports)
├── database/{migrations,factories,seeders}
└── tests/{Feature,Unit}                   # Pest, contra MySQL real
```

## WHERE TO LOOK
| Tarea | Archivo |
| --- | --- |
| Añadir o cambiar una ruta | `routes/api/v1/<modulo>.php` (+ `require` en `routes/api.php`) |
| Añadir un permiso | `app/Domain/Enums/PermissionSlug.php`, luego la matriz en `database/seeders/RoleSeeder.php` |
| Autorizar una acción nueva | Policy en `app/Policies/` (auto-descubierta) o middleware `permission:<slug>` |
| Poner una regla de negocio | `app/Services/<Dominio>/`; el controlador solo traduce HTTP |
| Cambiar el sobre de error | `app/Http/Errors/ApiErrorResponse.php` + los render de `bootstrap/app.php` |
| Nueva tabla | `database/migrations/` siguiendo el orden de `docs/architecture/database-design.md` §7 |
| Semillas | `database/seeders/` en orden Permission → Role → DevAdmin → UnitOfMeasure |
| Pruebas | `tests/Feature/<Modulo>/**`, nombradas `AC-###: <comportamiento>` |

## CONVENTIONS
- **Controlador fino:** valida con un FormRequest, autoriza con middleware o Policy y traduce el resultado con un Resource. **La lógica de dominio vive en `app/Services/`**, nunca en el controlador ni en el modelo.
- **Validación compartida:** cuando una regla se aplica a más de una puerta de entrada (captura manual e importación), vive una sola vez. `CatalogRules` es el ejemplo: la importación no relaja ni duplica las reglas de la captura manual (RN-IMP-02).
- `declare(strict_types=1)` y tipos de retorno declarados; `casts()` para los tipos; `fillable` explícito y cerrado (por eso `is_active` y `last_login_at` no se asignan en masa).
- Cuando el código de error importa, la excepción lo transporta: `AuthorizationException('SIN_PERMISO' | 'CUENTA_INACTIVA')` y `OutsideWarehouseScopeException` se mapean a su código en `bootstrap/app.php`. No se inventan códigos nuevos fuera de la tabla de `docs/architecture/api-design.md` §5.
- Los Resources **nunca** exponen `password`, `remember_token` ni tokens. Los decimales salen como **cadenas**, nunca como número.
- El alcance por almacén se resuelve **en la consulta** (`accessibleWarehouseIds()`), no filtrando el resultado en PHP.
- `hasPermission()` cachea los slugs por petición; `canAccessWarehouse()` **no** cachea la pertenencia, porque retirar una asignación debe surtir efecto en la petición siguiente (AC-011).

## ANTI-PATTERNS (backend)
- No crear `AuthServiceProvider`: los Gates viven en `AppServiceProvider`. No añadir `Gate::before`.
- No mover `app/Domain/`, `app/Http/Errors/` ni la excepción de alcance: no son scaffold de Laravel y las convenciones dependen de su sitio.
- No renombrar el pivote `warehouse_user` sin actualizar los dos modelos, la Policy y las pruebas.
- No duplicar una validación entre la captura manual y la importación: se extrae al servicio y ambos la llaman.
- No comparar dinero ni cantidades con flotante: se usa `bccomp` sobre cadenas, y falta de extensión es una excepción clara, no una degradación silenciosa.
- No devolver trazas, SQL ni mensajes de excepción al cliente: el 500 expone solo un `error_id` que sí queda registrado en el log.
- No añadir autenticación por token: hoy es solo sesión de cookie (`statefulApi`); los tokens con habilidades son para integraciones futuras.
- No usar `sqlite` para pruebas: `phpunit.xml` fija `stockflow_test` en MySQL.

## COMMANDS
```bash
composer install
./vendor/bin/pint --test                     # estilo (lo mismo que corre la CI)
php artisan test                             # REQUIERE el contenedor MySQL arriba
php artisan test --filter=Catalog            # un módulo concreto
php artisan migrate --force
php artisan db:seed --force                  # permisos, roles, admin y unidades base
```

## NOTES
- **Dependencia de plataforma:** `phpoffice/phpspreadsheet` exige la extensión `gd`, que está en el `Dockerfile` y en las extensiones del flujo de integración continua. Si falta en cualquiera de los dos, la instalación falla o la plantilla responde 500.
- **Tablas con nombre explícito:** `units_of_measure` y `product_warehouse` declaran `$table` en su modelo porque la pluralización de Eloquent no coincide con el nombre documentado; el pivote `warehouse_user` se declara explícito en las relaciones de sus dos modelos.
- **El archivo de la importación no se guarda** en el servidor: la auditoría conserva el nombre del archivo y los conteos, nunca su contenido.
