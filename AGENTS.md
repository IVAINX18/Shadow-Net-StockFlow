# PROJECT KNOWLEDGE BASE

**Updated:** 13/10/2026 · **Commit:** 804f060 · **Branch:** main

## OVERVIEW
ERP web de inventarios y logística multi-almacén (Shadow-Net Technologies). Monorepo **documentación-primero**: API Laravel 13 + SPA Angular 22 + MySQL 8.4, entorno reproducible con **Podman**. El núcleo es un libro de movimientos (kardex) transaccional. Las **fases 0, 1 y 2 están completadas y verificadas**: fundaciones, autenticación y RBAC con almacenes, y catálogo con importación. La Fase 3 (inventario: documentos, movimientos, kardex, reversos y carga inicial) es el siguiente paso.

## STRUCTURE
```
Shadownet_Stockflow/
├── docs/         # Especificación completa (español). FUENTE DE VERDAD.
├── backend/      # API Laravel 13 → ver backend/AGENTS.md
├── frontend/     # SPA Angular 22, cableada a la API (autenticación, tablero, almacenes, usuarios/roles y catálogo)
├── compose.yaml  # Servicios mysql + backend + frontend (podman-compose)
├── .env.example  # Variables de la composición (APP_KEY + DB_*)
└── .github/workflows/ci.yml   # CI: Pint + Pest (MySQL) y build de Angular
```

## WHERE TO LOOK
| Pregunta | Documento |
| --- | --- |
| Levantar el entorno / CI / despliegue | `docs/architecture/deployment-architecture.md`, `README.md` |
| Qué debe hacer el producto | `docs/planning/functional-requirements.md`, `requirements.md`, `acceptance-criteria.md` |
| Por qué una tecnología o versión | `docs/architecture/architecture-decisions.md` (ADR-001..017) |
| Modelo de datos y migraciones | `docs/architecture/database-design.md`, `er-diagram.md` |
| Reglas del núcleo de inventario | `docs/architecture/inventory-domain.md` |
| Endpoints, paginación y errores | `docs/architecture/api-design.md` |
| Controles y catálogo de seguridad | `docs/architecture/security-design.md` |
| Identidad visual de la SPA | `docs/architecture/design-system.md` |
| Cómo probar | `docs/development/testing-strategy.md` |
| Cómo reportar avance | `docs/development/development-workflow.md` + `docs/logbook/development-log.md` |
| Fases y orden de trabajo | `docs/planning/scope-and-milestones.md`, `backlog.md` |

Índice navegable: `docs/README.md` → `docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md`.

## CODE MAP
Sin indexador (`.codegraph/` vacío) ni LSP de PHP instalado: **centralidad no medida**. Puntos de entrada reales:

| Archivo | Rol |
| --- | --- |
| `backend/bootstrap/app.php` | Rutas, middleware (`statefulApi`, alias `account.active` / `permission`), mapeo de excepciones al sobre de error |
| `backend/routes/api.php` → `routes/api/v1/{auth,warehouses,users,roles,catalog,imports}.php` | Rutas v1, un archivo por módulo |
| `backend/app/Domain/Enums/PermissionSlug.php` | **Fuente única** de los 19 permisos |
| `backend/app/Providers/AppServiceProvider.php` | Un Gate por slug + limitador de login |
| `backend/app/Policies/{Warehouse,User,Role}Policy.php` | Autorización; Warehouse combina permiso + alcance por almacén |
| `backend/app/Http/Middleware/{EnsurePermission,EnsureAccountIsActive}.php` | RBAC por ruta y bloqueo de cuenta inactiva |
| `backend/app/Models/User.php` | `hasPermission()`, `accessibleWarehouseIds()`, `canAccessWarehouse()` |
| `backend/app/Services/Catalog/` | Servicios de dominio del catálogo; `CatalogRules` es la validación compartida entre la captura manual y la importación |
| `backend/app/Services/Audit/AuditLogger.php` | Bitácora append-only: acción, entidad, valores anterior y nuevo, autor |
| `backend/database/seeders/{Permission,Role,DevAdminUser,UnitOfMeasure}Seeder.php` | Semillas de permisos, roles, admin de desarrollo y unidades base |
| `frontend/src/app/app.config.ts` | Providers de la SPA: HTTP con interceptores de errores y de autenticación, ruteo y zona |
| `frontend/src/app/core/` | Servicios, modelos tipados, interceptores y guardias |
| `frontend/src/app/shared/components/` | Componentes compartidos (cabecera, tabla, indicador, estados, chip, diálogo de confirmación) |

Implementado hoy: salud, autenticación y perfil, RBAC completo (usuarios, roles y permisos), almacenes con su alcance por usuario, y todo el catálogo: categorías jerárquicas, unidades de medida, productos con configuración por almacén e importación con plantilla, simulacro y aplicación atómica e idempotente. **Lo que sigue siendo plan son el inventario (documentos, movimientos, kardex, reversos y carga inicial), el tablero con datos reales, los reportes, las exportaciones y las pantallas de auditoría.**

## CONVENTIONS
Solo lo que se desvía del estándar Laravel/Angular; el resto es convencional.
- **Comentarios en español, primera persona, sin emojis**, explicando el PORQUÉ (transacción, bloqueo, seguridad, decisión no obvia), nunca el qué. PHPDoc/TSDoc para contratos. Ver `docs/development/coding-standards.md`.
- **Código y mensajes de commit en inglés; documentación y comentarios en español.** Conventional Commits.
- **Documentación-primero:** si cambia el comportamiento, se actualizan el documento afectado y la **bitácora** en el mismo cambio.
- **Nombres canónicos sin sinónimos:** `/api/v1`; tipos `entrada|salida|ajuste|transferencia`; estados `borrador|confirmado|revertido|descartado`; sobre de error `{error:{code,message,details}}` con códigos en mayúsculas (`NO_AUTENTICADO`, `FUERA_DE_ALCANCE`, …).
- **Pruebas contra MySQL 8.4 real, nunca SQLite** (`backend/phpunit.xml` fija `stockflow_test`).
- Los decimales viajan como **cadenas** JSON, nunca flotantes.

## ANTI-PATTERNS (THIS PROJECT)
- **Nunca `docker` / `docker compose`**: el entorno canónico es **Podman** (`podman-compose`, `compose.yaml`). No se usa el demonio de Docker.
- No introducir **Redis, Kubernetes, microservicios ni event sourcing** (ADR-001/013; regla de no añadir infraestructura por moda).
- No `spatie/laravel-permission` (RBAC nativo), ni NgRx, ni Fortify/JWT (ADR-003/004/014).
- **No `Gate::before`**: ningún rol puede saltarse el alcance por almacén.
- **La autorización vive en el backend**; guardias y ocultamiento de UI son solo navegación.
- **Nunca** secretos, contraseñas, tokens, SQL ni trazas en respuestas o registros.
- **Nunca** editar ni borrar movimientos o documentos confirmados (libro append-only; se corrige con reversión).
- No filtrar el alcance por almacén **en memoria**: se resuelve en la consulta.
- Nada de `float` para dinero ni cantidades.
- No inventar endpoints, permisos ni tablas que la especificación no defina: los huecos se deciden y se documentan, no se rellenan en silencio.

## COMMANDS
```bash
# Raíz del repositorio
cp .env.example .env                          # completar APP_KEY y contraseñas
podman-compose up -d --build                  # mysql + backend + frontend
podman-compose exec -T backend php artisan migrate --force
curl http://localhost:8000/api/v1/health      # API; SPA en :4200
podman-compose down

# backend/
composer install
./vendor/bin/pint --test                      # estilo (lo mismo que corre la CI)
php artisan test                              # Pest 5; REQUIERE el contenedor MySQL arriba
php artisan db:seed --force                   # permisos, roles, admin y unidades base

# frontend/  (requiere Node 22 LTS vía nvm)
npm install
npm run lint && npm test && npm run build     # lo mismo que corre la CI
```

## NOTES
- **Node 22 obligatorio** para el frontend: el `node` del sistema (20.19.2) está EOL y Angular 22 no lo soporta; cargar nvm antes de cualquier comando de `frontend/`.
- **`--no-reload`** en el `command` del backend de `compose.yaml` es intencional: sin él, `artisan serve` descarta el `DB_HOST=mysql` de la composición y gana el `.env` del host. No quitarlo.
- **Tablas con nombre explícito:** `units_of_measure` y `product_warehouse` declaran `$table` en su modelo porque la pluralización de Eloquent no coincide con el nombre documentado; el pivote `warehouse_user` se declara explícito en las relaciones de sus dos modelos.
- **Modelo documentado ≠ migrado:** `database-design.md` describe 16 tablas; hoy existen 21 en la base, de las que 12 son de la aplicación (RBAC, almacenes, catálogo y auditoría) y el resto base de Laravel. Las tablas de inventario siguen siendo plan: no asumirlas.
- **La SPA está cableada a la API:** entornos por configuración, `HttpClient` con interceptores de errores y de sesión, rutas perezosas con guardias y componentes compartidos. No asumir que sigue siendo andamiaje.
- **Bitácora obligatoria y de solo adición** (`docs/logbook/development-log.md`): una entrada por unidad; nunca se reescribe el pasado.
- **Deriva conocida docs ↔ código:** ADR-006 figura como decisión de entorno pendiente de confirmación aunque Node 22 ya está instalado y la CI lo usa. Corregir al tocar ese documento.
- `backend/AGENTS.md` y `backend/CLAUDE.md` eran plantillas autogeneradas de Laravel Boost (pedían PHP 8.5 e instalar Boost); fueron sustituidas por esta base de conocimiento.
