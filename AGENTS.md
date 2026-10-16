# PROJECT KNOWLEDGE BASE

**Updated:** 16/10/2026 · **Commit:** 216ac96 · **Branch:** main

## OVERVIEW
ERP web de inventarios y logística multi-almacén (Shadow-Net Technologies). Monorepo **documentación-primero**: API Laravel 13 + SPA Angular 22 + MySQL 8.4, entorno reproducible con **Podman**. El núcleo es un libro de movimientos (kardex) transaccional. Las **fases 0, 1, 2 y 3 están completadas y verificadas**: fundaciones, autenticación y RBAC con almacenes, catálogo con importación, e inventario (documentos, movimientos, kardex, reversos y carga inicial). El trabajo pendiente son las fases 4 a 7 (tablero, reportes, auditoría, calidad y despliegue).

## STRUCTURE
```
Shadownet_Stockflow/
├── docs/         # Especificación completa (español). FUENTE DE VERDAD.
├── backend/      # API Laravel 13 → ver backend/AGENTS.md
├── frontend/     # SPA Angular 22, cableada a la API (autenticación, tablero, almacenes, usuarios/roles y catálogo)
├── compose.yaml  # Servicios mysql + backend + frontend (podman-compose)
├── .env.example  # Variables de la composición (APP_KEY + DB_*)
└── .github/workflows/ci.yml   # CI: backend (Pint + Pest contra MySQL) y frontend (ESLint + Vitest + build)
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
| Identidad visual de la SPA | `docs/architecture/design-system.md` (contrato vigente) y `DESIGN.md` en la raíz (referencia de diseño) |
| Cómo probar | `docs/development/testing-strategy.md` |
| Cómo reportar avance | `docs/development/development-workflow.md` + `docs/logbook/development-log.md` |
| Fases y orden de trabajo | `docs/planning/scope-and-milestones.md`, `backlog.md` |

Índice navegable: `docs/README.md` → `docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md`.

## CODE MAP
Sin indexador (`.codegraph/` vacío) ni LSP de PHP instalado: **centralidad no medida**. Puntos de entrada reales:

| Archivo | Rol |
| --- | --- |
| `backend/bootstrap/app.php` | Rutas, middleware (`statefulApi`, alias `account.active` / `permission`), mapeo de excepciones al sobre de error |
| `backend/routes/api.php` → `routes/api/v1/*.php` | Rutas v1, un archivo por módulo (auth, almacenes, usuarios, roles, catálogo, importación e inventario) |
| `backend/app/Domain/Enums/PermissionSlug.php` | **Fuente única** de los 19 permisos |
| `backend/app/Providers/AppServiceProvider.php` | Un Gate por slug + limitador de login |
| `backend/app/Policies/{Warehouse,User,Role}Policy.php` | Autorización; Warehouse combina permiso + alcance por almacén |
| `backend/app/Http/Middleware/{EnsurePermission,EnsureAccountIsActive}.php` | RBAC por ruta y bloqueo de cuenta inactiva |
| `backend/app/Models/User.php` | `hasPermission()`, `accessibleWarehouseIds()`, `canAccessWarehouse()` |
| `backend/app/Services/Catalog/` | Servicios de dominio del catálogo; `CatalogRules` es la validación compartida entre la captura manual y la importación |
| `backend/app/Services/Inventory/` | Servicios de dominio del inventario: documentos transaccionales, movimientos append-only, kardex, reversos y carga inicial |
| `backend/app/Services/Audit/AuditLogger.php` | Bitácora append-only: acción, entidad, valores anterior y nuevo, autor |
| `backend/database/seeders/{Permission,Role,DevAdminUser,UnitOfMeasure}Seeder.php` | Semillas de permisos, roles, admin de desarrollo y unidades base |
| `frontend/src/app/app.config.ts` | Providers de la SPA: HTTP con interceptores de errores y de autenticación, ruteo y zona |
| `frontend/src/app/core/` | Servicios, modelos tipados, interceptores y guardias |
| `frontend/src/app/shared/components/` | Componentes compartidos (cabecera, tabla, indicador, estados, chip, diálogo de confirmación) |

Implementado hoy: salud, autenticación y perfil, RBAC completo (usuarios, roles y permisos), almacenes con su alcance por usuario, y todo el catálogo: categorías jerárquicas, unidades de medida, productos con configuración por almacén e importación con plantilla, simulacro y aplicación atómica e idempotente, y el núcleo de inventario en el backend (documentos transaccionales, movimientos append-only, kardex, reversos y carga inicial). **Siguen siendo plan el tablero con datos reales, los reportes, las exportaciones y las pantallas de auditoría.**

## CONVENTIONS
Solo lo que se desvía del estándar Laravel/Angular; el resto es convencional.
- **Comentarios en español, primera persona, sin emojis**, explicando el PORQUÉ (transacción, bloqueo, seguridad, decisión no obvia), nunca el qué. PHPDoc/TSDoc para contratos. Ver `docs/development/coding-standards.md`.
- **Código y mensajes de commit en inglés; documentación y comentarios en español.** Conventional Commits.
- **Documentación-primero:** si cambia el comportamiento, se actualizan el documento afectado y la **bitácora** en el mismo cambio.
- **Nombres canónicos sin sinónimos:** `/api/v1`; tipos `entrada|salida|ajuste|transferencia`; estados `borrador|confirmado|revertido|descartado`; sobre de error `{error:{code,message,details}}` con códigos en mayúsculas (`NO_AUTENTICADO`, `FUERA_DE_ALCANCE`, …).
- **Pruebas contra MySQL 8.4 real, nunca SQLite** (`backend/phpunit.xml` fija `stockflow_test`).
- Los decimales viajan como **cadenas** JSON, nunca flotantes.
- **La paleta del tema es generada, no se edita a mano:** `frontend/tools/generate-palette.mjs` deriva `src/styles/_palette.scss` de **una sola semilla** (`themeFromSourceColor`) con `npm run palette:generate`. Cambiar un color es cambiar la semilla y regenerar; lo que se escriba en el SCSS generado se pierde en la siguiente regeneración. El guion empaqueta con esbuild porque `@material/material-color-utilities` publica ESM **sin extensiones** y el resolutor de Node lo rechaza: importarla directamente desde un script propio falla, hay que pasar por esbuild igual que el guion.
- **Los neutros de Material 3 nunca son grises puros:** `themeFromSourceColor` fija un croma mínimo, así que cambiar la semilla solo cambia el *tono* del tinte, no lo elimina. Para un fondo neutro hay que emitir `neutral` y `neutral-variant` con croma cero (`TonalPalette.fromHueAndChroma(0, 0)`). Además, la superficie de M3 vive en el tono 98 (casi blanco), no en el 100: el blanco puro es una desviación explícita del sistema.

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
- **Modelo documentado ≠ migrado:** `database-design.md` describe 16 tablas; el conteo exacto en la base cambia con cada fase (la Fase 3 añadió las tablas de inventario), así que hay que confirmarlo en `database/migrations/` antes de asumirlo. Los nombres tampoco se asumen: se verifican en su migración.
- **La SPA está cableada a la API:** entornos por configuración, `HttpClient` con interceptores de errores y de sesión, rutas perezosas con guardias y componentes compartidos. No asumir que sigue siendo andamiaje.
- **Verificar la SPA en navegador exige servir con `no-store` y respaldo de rutas:** con un estático corriente, los paquetes en caché enmascaran fallos y las rutas profundas devuelven 404. El arnés de verificación vive **fuera del repositorio** y no sobrevive entre sesiones: regenerar la compilación (`ng build --configuration development`) antes de medir.
- **Bitácora obligatoria y de solo adición** (`docs/logbook/development-log.md`): una entrada por unidad; nunca se reescribe el pasado.
- **Deriva conocida docs ↔ código:** ADR-006 figura como decisión de entorno pendiente de confirmación aunque Node 22 ya está instalado y la CI lo usa. Corregir al tocar ese documento.
- `backend/AGENTS.md` y `backend/CLAUDE.md` eran plantillas autogeneradas de Laravel Boost (pedían PHP 8.5 e instalar Boost); hoy `backend/AGENTS.md` es la base de conocimiento real y `backend/CLAUDE.md` solo un puntero a ella.
- **Nunca dos suites de pruebas a la vez:** dos ejecuciones simultáneas de `php artisan test` colisionan sobre la misma base `stockflow_test`, porque `RefreshDatabase` migra y vacía al mismo tiempo. Los síntomas (tabla inexistente, clave duplicada) parecen fallos del código y no lo son; engañaron a dos agentes en una misma sesión. Una sola suite por vez.
- **Si la SPA no sirve en :4200, el problema es el volumen de dependencias, no el Dockerfile:** `compose.yaml` monta un volumen con nombre sobre `frontend/node_modules` y los volúmenes con nombre **no se resincronizan** mientras existan, así que conservan la lista de dependencias de cuando se crearon. El contenedor registra `Could not resolve "@angular/material/icon"` y el paquete no está en el volumen. Se repara eliminando el contenedor y el volumen `frontend-node-modules` y recreando el servicio, para que el volumen se siembre otra vez desde la imagen; el Dockerfile está bien y no hay que tocarlo.
