# PROJECT KNOWLEDGE BASE

**Generated:** 12/10/2026 · **Commit:** dec7683 · **Branch:** main

## OVERVIEW
ERP web de inventarios y logística multi-almacén (Shadow-Net Technologies). Monorepo **documentación-primero**: API Laravel 13 + SPA Angular 22 + MySQL 8.4, entorno reproducible con **Podman**. El núcleo es un libro de movimientos (kardex) transaccional; hoy está en construcción (Fase 1 en curso: autenticación, RBAC y almacenes).

## STRUCTURE
```
Shadownet_Stockflow/
├── docs/         # Especificación completa (español). FUENTE DE VERDAD.
├── backend/      # API Laravel 13 → ver backend/AGENTS.md
├── frontend/     # SPA Angular 22 (andamiaje; AÚN sin cablear a la API)
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
| Cómo probar | `docs/development/testing-strategy.md` |
| Cómo reportar avance | `docs/development/development-workflow.md` + `docs/logbook/development-log.md` |
| Fases y orden de trabajo | `docs/planning/scope-and-milestones.md`, `backlog.md` |

Índice navegable: `docs/README.md` → `docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md`.

## CODE MAP
Sin indexador (`.codegraph/` vacío) ni LSP de PHP instalado: **centralidad no medida**. Puntos de entrada reales:

| Archivo | Rol |
| --- | --- |
| `backend/bootstrap/app.php` | Rutas, middleware (`statefulApi`, alias `account.active` / `permission`), mapeo de excepciones al sobre de error |
| `backend/routes/api.php` → `routes/api/v1/{auth,warehouses}.php` | Rutas v1, un archivo por módulo |
| `backend/app/Domain/Enums/PermissionSlug.php` | **Fuente única** de los 19 permisos |
| `backend/app/Providers/AppServiceProvider.php` | Un Gate por slug + limitador de login |
| `backend/app/Policies/{Warehouse,User,Role}Policy.php` | Autorización; Warehouse combina permiso + alcance por almacén |
| `backend/app/Http/Middleware/{EnsurePermission,EnsureAccountIsActive}.php` | RBAC por ruta y bloqueo de cuenta inactiva |
| `backend/app/Models/User.php` | `hasPermission()`, `accessibleWarehouseIds()`, `canAccessWarehouse()` |
| `backend/database/seeders/{Permission,Role,DevAdmin}Seeder.php` | Semillas de permisos, roles y admin de desarrollo |
| `frontend/src/app/app.config.ts` | Providers de la SPA (sin interceptores ni guardias todavía) |

Implementado hoy: salud, login/logout/perfil, listado y detalle de almacenes. **El resto del catálogo de `api-design.md` es plan, no código.**

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
php artisan db:seed --force                   # permisos, roles y admin de desarrollo

# frontend/  (requiere Node 22 LTS vía nvm)
npm install && npm run build                  # ng build con tipado estricto
```

## NOTES
- **Node 22 obligatorio** para el frontend: el `node` del sistema (20.19.2) está EOL y Angular 22 no lo soporta; cargar nvm antes de cualquier comando de `frontend/`.
- **`--no-reload`** en el `command` del backend de `compose.yaml` es intencional: sin él, `artisan serve` descarta el `DB_HOST=mysql` de la composición y gana el `.env` del host. No quitarlo.
- **El pivote `warehouse_user`** se declara explícito en los dos modelos porque el nombre por defecto de Eloquent no coincide con el esquema documentado.
- **Modelo documentado ≠ migrado:** `database-design.md` describe 16 tablas; solo existen las de RBAC/almacenes más las base de Laravel. No asumir las tablas de inventario.
- **El frontend no está cableado:** sin `environments`, sin `HttpClient` y con `routes = []`.
- **Bitácora obligatoria y de solo adición** (`docs/logbook/development-log.md`): una entrada por unidad; nunca se reescribe el pasado.
- **Deriva conocida docs ↔ código:** `development-workflow.md` afirma que `backend/` y `frontend/` aún no existen, y ADR-006 figura como decisión pendiente aunque Node 22 ya está instalado. Corregir al tocar esos documentos.
- `backend/AGENTS.md` y `backend/CLAUDE.md` eran plantillas autogeneradas de Laravel Boost (pedían PHP 8.5 e instalar Boost); fueron sustituidas por esta base de conocimiento.
