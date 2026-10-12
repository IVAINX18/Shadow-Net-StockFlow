# Bitácora de desarrollo - Shadow-Net StockFlow

**Autor:** Shadow-Net Technologies. **Zona horaria:** America/Bogota. **Formato de fecha:** DD/MM/AAAA.

Esta bitácora registra cada unidad de trabajo del proyecto: fecha, fase, archivos afectados, cambios, motivo, pruebas ejecutadas, resultado y pendientes. Se actualiza al cerrar cada unidad y nunca se reescribe el pasado: una corrección añade una entrada nueva.

## Resumen de entradas

| # | Fecha | Fase | Resultado |
| --- | --- | --- | --- |
| 1 | 10/10/2026 | Fase 0 - Planificación | Completada |
| 2 | 10/10/2026 | Fase 0 - Fundaciones | Completada (verificación de `docker compose up` pendiente del demonio Docker) |
| 3 | 10/10/2026 | Fase 0 - Fundaciones | Completada (entorno verificado con Podman; ADR-017 reemplaza ADR-011) |
| 4 | 12/10/2026 | Fase 1 - U1: esquema RBAC y almacenes | Completada (pruebas contra MySQL real) |
| 5 | 12/10/2026 | Fase 1 - U2: errores uniformes y autenticación | Completada (flujo SPA verificado por HTTP) |
| 6 | 12/10/2026 | Fase 1 - U3: autorización y alcance por almacén | Completada (403 y alcance verificados por HTTP) |
| 7 | 12/10/2026 | Fase 1 - U4: semillas de permisos, roles y admin | Completada (login real con el admin sembrado) |
| 8 | 12/10/2026 | Tooling - Base de conocimiento de agentes | Completada |
| 9 | 12/10/2026 | Planificación - Importación y carga inicial | Completada (revisión de coherencia; pendiente confirmación de Master) |
| 10 | 12/10/2026 | Fase 1 - U5: escritura de almacenes y usuarios autorizados | Completada (pruebas contra MySQL real) |

---

## Entrada 1 - 10/10/2026 - Inspección del repositorio y planificación integral

**Fase:** Fase 0 - Planificación (documentación, sin código de aplicación).

**Archivos afectados (creados):**
- `.gitignore`
- `docs/planning/project-charter.md`
- `docs/planning/requirements.md`
- `docs/planning/functional-requirements.md`
- `docs/planning/non-functional-requirements.md`
- `docs/planning/scope-and-milestones.md`
- `docs/planning/backlog.md`
- `docs/planning/acceptance-criteria.md`
- `docs/architecture/system-architecture.md`
- `docs/architecture/architecture-decisions.md`
- `docs/architecture/database-design.md`
- `docs/architecture/er-diagram.md`
- `docs/architecture/inventory-domain.md`
- `docs/architecture/api-design.md`
- `docs/architecture/security-design.md`
- `docs/architecture/frontend-architecture.md`
- `docs/architecture/backend-architecture.md`
- `docs/architecture/deployment-architecture.md`
- `docs/development/coding-standards.md`
- `docs/development/testing-strategy.md`
- `docs/development/development-workflow.md`
- `docs/README.md`
- `docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md`
- `README.md`

**Cambios efectuados:**
- Inspección del repositorio: se confirmó que estaba vacío (solo existía el directorio `.codegraph`), sin control de versiones inicializado y sin código de aplicación.
- Verificación del entorno local: PHP 8.4.26 con extensiones `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `zip`; Node 20.19.2 y npm 9.2.0; Docker 26.1.5 y Compose v5.6.0; Git 2.47.3. Ausentes: Composer, cliente/servidor MySQL y Angular CLI.
- Se detectó una incompatibilidad de entorno: Angular 22.2.x exige Node `^22.22.3 || ^24.15.0 || >=26` y el Node local (20.19.2) alcanzó fin de soporte el 30/04/2026. Se registró como ADR-006 y como riesgo R-07/R-11, con recomendación de subir a Node 22 LTS o 24 LTS.
- Se verificaron las versiones estables de todo el stack contra fuentes oficiales (Packagist, npm, docs de Laravel/Angular/MySQL) el 10/10/2026.
- Se elaboró la documentación de planificación completa: acta, requisitos, alcance, backlog, arquitectura, modelo de datos, dominio de inventario, API, seguridad, estándares, pruebas y flujo de trabajo.
- Se fijó el modelo canónico del inventario: documento, líneas y movimientos (libro contable), valorización por promedio ponderado móvil, bloqueo pesimista en orden consistente e idempotencia.

**Motivo:** la primera tarea del proyecto exige planificar antes de programar: inspeccionar el estado real, definir el alcance, diseñar la arquitectura y el modelo de datos, y establecer requisitos, pruebas y criterios de aceptación antes de escribir la primera línea de código de aplicación.

**Pruebas ejecutadas:**
- Inspección del sistema de archivos (`ls`, `find`) y del estado de Git (`git status`).
- Verificación de versiones de herramientas (`php -v`, `node -v`, `npm -v`, `docker --version`, `git --version`).
- Listado de extensiones de PHP (`php -m`).
- Consultas de versiones de dependencias contra Packagist y fuentes oficiales mediante el agente de investigación.

**Resultado:** no se ejecutó código de aplicación (no aplica suite de pruebas todavía). La planificación quedó documentada y coherente; no se afirma haber probado funcionalidad inexistente.

**Pendientes:**
- Confirmar con Master la decisión de entorno ADR-006 (subir Node a 22/24 LTS o mantener Angular 21 en Node 20).
- Confirmar la elección de ejecutor de pruebas (Pest 5 propuesto en ADR-010).
- Instalar Composer, MySQL 8.4 LTS (o usar Docker Compose) al iniciar la Fase 1.
- Inicializar el repositorio Git y establecer la rama principal al abrir la Fase 1.
- Comenzar la Fase 1 (fundaciones) tras la aprobación de la documentación.

**Nota:** esta entrada no registra pruebas de funcionalidad porque, por diseño de esta fase, no existe todavía código ejecutable. Se evita declarar verificaciones no realizadas.

---

## Entrada 2 - 10/10/2026 - Fase 0: fundaciones (esqueleto backend/frontend, Docker y CI)

**Fase:** Fase 0 - Fundaciones.

**Archivos afectados (creados o modificados):**
- `backend/` (Laravel 13.35.0 con Sanctum 4.3.3, Pest 5.3.1 y Pint 1.32.1; `HealthController`, `routes/api.php`, `tests/Feature/HealthTest.php`, `.env.example`, `Dockerfile`, `.dockerignore`).
- `frontend/` (Angular 22.2.2, TypeScript 6.0.3, standalone y zoneless; `Dockerfile`, `.dockerignore`).
- `docker-compose.yml`.
- `.env.example` (raíz).
- `.github/workflows/ci.yml`.
- `README.md` y `docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md` (corrección de numeración de fases).
- Esta bitácora.

**Cambios efectuados:**
- Instalación a nivel de usuario de Composer 2.10.3 (en `~/.local/bin`, firma SHA-384 verificada) y de Node v22.23.3 mediante nvm (checksum SHA-256 verificado), sin `sudo`.
- Creación del backend Laravel 13 con `composer create-project`, Sanctum vía `install:api`, Pest y Pint, y un endpoint versionado `GET /api/v1/health` con su prueba.
- Creación del frontend Angular 22 con componentes standalone y zoneless por defecto; build de producción correcto.
- `docker-compose.yml` con los servicios `mysql` (8.4, InnoDB, volumen persistente), `backend` (PHP 8.4) y `frontend` (Node 22), con límites de memoria por servicio.
- Dockerfiles: backend en PHP 8.4 CLI (extensiones `pdo_mysql`, `mbstring`, `bcmath`, `intl`, `zip`) y frontend en Node 22 con etapas de desarrollo, construcción y producción (nginx).
- Flujo de integración continua con Pint, Pest y build de Angular.
- Corrección de la numeración de fases (Fase 0 = fundaciones) en `README.md` y la especificación maestra.

**Motivo:** cerrar las fundaciones técnicas antes de los módulos funcionales, con un entorno reproducible de un solo comando (criterio CG-04 del acta) y una línea base de calidad verificada.

**Pruebas ejecutadas (resultados reales):**
- `php artisan --version` → Laravel Framework 13.35.0.
- `php artisan route:list --path=api` → incluye `GET api/v1/health`.
- `php artisan test` → 3 pruebas, 3 correctas, 6 aserciones.
- `./vendor/bin/pint --test` → sin diferencias de estilo.
- `npm run build` (Angular) → correcto; salida en `frontend/dist/frontend/browser`.
- `docker compose config -q` → composición válida; YAML del CI validado.

**Resultado:** la Fase 0 quedó completada en todo lo verificable localmente. No se pudo verificar `docker compose up` porque el demonio de Docker no está activo y su arranque requiere `sudo` (no disponible de forma no interactiva).

**Pendientes:**
- Iniciar el demonio de Docker (`sudo systemctl start docker`) y verificar `docker compose up` (acción de Master).
- Decidir sobre `laravel/boost`, sugerido por el `AGENTS.md` generado por el esqueleto; no se instaló y el PHP se mantiene en 8.4, no en 8.5.
- Crear los `environment` de Angular con `apiBaseUrl` (Fase 1).
- Migrar las pruebas de integración a MySQL real y añadir el servicio MySQL al CI cuando existan pruebas de base de datos (Fase 3).

**Nota:** se registran comandos y resultados realmente ejecutados; no se declara verificada ninguna operación que no se ejecutó.

---

## Entrada 3 - 10/10/2026 - Cambio de Docker a Podman

**Fase:** Fase 0 - Fundaciones.

**Archivos afectados (creados o modificados):**
- `compose.yaml` (renombrado desde `docker-compose.yml`, con nombre de registro completo `docker.io/library/mysql:8.4` y límites con `mem_limit`).
- `backend/Dockerfile` (`COPY --from=docker.io/library/composer:2` con registro completo para Buildah).
- `.env.example` (comentarios adaptados a podman-compose).
- Documentación: `docs/architecture/architecture-decisions.md` (nueva ADR-017), `docs/architecture/deployment-architecture.md`, `docs/architecture/system-architecture.md`, `docs/architecture/security-design.md`, `docs/development/development-workflow.md`, `docs/planning/backlog.md`, `docs/planning/project-charter.md`, `docs/planning/scope-and-milestones.md`, `docs/README.md`, `docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md`, `README.md` y esta bitácora.

**Cambios efectuados:**
- Motivo: el demonio de Docker está apagado en el host (Debian 13) y no puede arrancarse sin privilegios interactivos, por lo que `docker compose up` no era verificable. Podman no requiere demonio en segundo plano.
- Entorno usado: Podman 5.4.2, Buildah 1.39.3 y podman-compose 1.6.0 (instalado vía pipx). Docker 26.1.5 sigue instalado en el host pero su demonio está apagado y no se usa.
- Renombre de `docker-compose.yml` a `compose.yaml`, que podman-compose descubre automáticamente.
- Ajustes propios de Podman: (a) `image: docker.io/library/mysql:8.4` con registro completo porque Podman no resuelve nombres cortos en tiempo de ejecución; (b) límites de memoria con `mem_limit:` porque es la clave que podman-compose aplica directamente, en lugar de `deploy.resources.limits.memory`; (c) `COPY --from=docker.io/library/composer:2` con registro completo en el Dockerfile del backend porque Buildah no resuelve nombres cortos en `COPY --from`.
- Comandos canónicos: copiar `.env.example` a `.env`, luego `podman-compose up -d --build`, `podman-compose exec -T backend php artisan migrate --force` y `podman-compose down`.
- La integración continua no cambia: sigue con setup-php y setup-node sobre corredores alojados por GitHub y no depende de un demonio local.

**Pruebas ejecutadas (resultados reales):**
- Construcción de imágenes: backend 848 MB y frontend 667 MB.
- Contenedores en ejecución: `shadow-net-stockflow_mysql_1` (sano), `_backend_1` (puerto 8000) y `_frontend_1` (puerto 4200).
- `GET /api/v1/health` → `{"status":"ok","service":"shadow-net-stockflow-api","version":"v1",...}`.
- `php artisan migrate --force` contra MySQL real: 10 tablas creadas, incluida `personal_access_tokens`.
- Frontend con HTTP 200 y título "Shadow-Net StockFlow".

**Resultado:** el entorno local quedó verificado con Podman; la decisión quedó registrada en ADR-017, que reemplaza a ADR-011 sin borrar su texto original.

**Pendientes:**
- Crear los `environment` de Angular con `apiBaseUrl` (Fase 1).
- Migrar las pruebas de integración a MySQL real y añadir el servicio MySQL al CI cuando existan pruebas de base de datos (Fase 3).
- Decidir sobre `laravel/boost`, sugerido por el `AGENTS.md` generado por el esqueleto; no se instaló y el PHP se mantiene en 8.4, no en 8.5.

**Nota:** esta entrada no modifica la Entrada 2, que se conserva como historia; la verificación pendiente del demonio Docker quedó resuelta por el cambio a Podman.

---

## Entrada 4 - 12/10/2026 - Fase 1, Unidad 1: esquema RBAC, almacenes y pruebas contra MySQL

**Fase:** Fase 1 - Autenticación, RBAC y almacenes (Unidad 1 de 8).

**Archivos afectados (creados o modificados):**
- `backend/database/migrations/2026_10_12_000001_add_state_columns_to_users_table.php`
- `backend/database/migrations/2026_10_12_000002_create_roles_table.php`
- `backend/database/migrations/2026_10_12_000003_create_permissions_table.php`
- `backend/database/migrations/2026_10_12_000004_create_role_user_table.php`
- `backend/database/migrations/2026_10_12_000005_create_permission_role_table.php`
- `backend/database/migrations/2026_10_12_000006_create_warehouses_table.php`
- `backend/database/migrations/2026_10_12_000007_create_warehouse_user_table.php`
- `backend/app/Models/User.php`, `Role.php`, `Permission.php`, `Warehouse.php`
- `backend/database/factories/UserFactory.php`, `RoleFactory.php`, `PermissionFactory.php`, `WarehouseFactory.php`
- `backend/tests/Feature/RbacWarehouseSchemaTest.php`
- `backend/phpunit.xml` y `.github/workflows/ci.yml`
- `backend/.env` (local, ignorado por Git)

**Cambios efectuados:**
- Se creó el esquema físico del RBAC nativo (roles, permisos y sus pivotes) y de almacenes (código único, desactivación lógica y pivote usuario-almacén), conforme a docs/architecture/database-design.md, secciones 5.1 y 5.2.
- Se añadieron a `users` las columnas `is_active` y `last_login_at`.
- Se implementaron los modelos con relaciones, conversiones de tipo y los ayudantes `hasPermission()` (con caché por petición) y `accessibleWarehouseIds()` (alcance por almacén).
- Se creó la base de datos de pruebas `stockflow_test` en el MySQL del contenedor y se configuró `phpunit.xml` para usar MySQL real, sin SQLite.
- Se añadió el servicio `mysql:8.4` con comprobación de salud al trabajo de backend de la integración continua.

**Motivo:** disponer del modelo de datos y de los modelos Eloquent antes de implementar autenticación y autorización, y garantizar que las pruebas se ejecuten contra el mismo motor que producción (InnoDB), como exige la estrategia de pruebas.

**Pruebas ejecutadas (resultados reales):**
- `podman-compose exec -T backend php artisan migrate --force` → las 7 migraciones aplicadas.
- Verificación en `information_schema`: colación `utf8mb4_0900_ai_ci`, claves únicas `uq_*`, claves foráneas `fk_*` (CASCADE salvo `manager_user_id` con RESTRICT) e índice `idx_warehouses_is_active`.
- `php artisan test` (host, contra MySQL) → 6 pruebas, 6 correctas, 15 aserciones.
- `./vendor/bin/pint --test` → sin diferencias.
- La prueba `RbacWarehouseSchemaTest` rechaza el código de almacén duplicado y el slug de rol duplicado.

**Resultado:** Unidad 1 completada y verificada. Las tablas existen en MySQL real: `roles`, `permissions`, `role_user`, `permission_role`, `warehouses` y `warehouse_user`.

**Decisiones tomadas:**
- Las pruebas de integración usan MySQL real en lugar de SQLite (coherente con la estrategia de pruebas y con ADR-005); en local requieren el contenedor de MySQL levantado.
- `accessibleWarehouseIds()` contempla el permiso `warehouses.view_all` para que el Administrador no dependa de asignaciones manuales de almacén.

**Pendientes:**
- Unidad 2: errores uniformes y autenticación con Sanctum en modo estatal.
- Los cambios de esta unidad están sin confirmar en Git, a la espera de autorización.
- El host no tiene la extensión PHP `intl`, por lo que `php artisan db:show` no funciona localmente; no afecta a las pruebas ni a la base de datos.

**Nota:** no se modificaron `frontend/`, `compose.yaml` ni las rutas; la base `stockflow_test` es independiente de la base de desarrollo `stockflow`.

---

## Entrada 5 - 12/10/2026 - Fase 1, Unidad 2: errores uniformes y autenticación con Sanctum estatal

**Fase:** Fase 1 - Autenticación, RBAC y almacenes (Unidad 2 de 8).

**Archivos afectados (creados o modificados):**
- `backend/app/Http/Errors/ApiErrorResponse.php` (nuevo): único constructor del sobre de error.
- `backend/app/Http/Middleware/EnsureAccountIsActive.php` (nuevo): cuenta desactivada pierde acceso.
- `backend/app/Http/Controllers/Api/V1/AuthController.php` (nuevo): login, logout y perfil.
- `backend/app/Http/Requests/Auth/LoginRequest.php` (nuevo): validación y verificación de credenciales.
- `backend/app/Http/Resources/UserResource.php`, `RoleResource.php` y `WarehouseResource.php` (nuevos).
- `backend/routes/api/v1/auth.php` (nuevo) y `backend/routes/api.php` (modificado).
- `backend/bootstrap/app.php` (modificado): `statefulApi`, redirección de invitados y mapeo de excepciones.
- `backend/app/Providers/AppServiceProvider.php` (modificado): limitador `login` por correo y dirección.
- `backend/app/Models/User.php` y `Warehouse.php` (modificados): nombre explícito del pivote `warehouse_user`.
- `backend/tests/Feature/Auth/AuthTest.php` y `backend/tests/TestCase.php` (nuevos/modificado).
- `compose.yaml` (modificado): `--no-reload` en el comando del backend.
- `docs/architecture/api-design.md` (modificado): códigos `SOLICITUD_INVALIDA`, `METODO_NO_PERMITIDO`, `CSRF_INVALIDO` y `CUENTA_INACTIVA`.

**Cambios efectuados:**
- Se implementó la envoltura uniforme de errores con los códigos documentados y sin filtrar trazas ni SQL al cliente.
- Se activó Sanctum en modo estatal (cookie de sesión con CSRF) para la SPA, más el middleware de cuenta activa.
- Se crearon los endpoints de login (con límite de intentos), logout y perfil.
- Se limitó el login a cinco intentos por minuto por correo y dirección, con respuesta 429.

**Motivo:** controlar quién entra al sistema, con autorización y errores uniformes aplicados en el servidor (RF-001 a RF-003, RF-007, RF-011 y RNF-009).

**Pruebas ejecutadas (resultados reales):**
- `php artisan test` (host, contra MySQL) → 15 pruebas, 15 correctas, 86 aserciones.
- `./vendor/bin/pint --test` → sin diferencias; `php -l` sobre `bootstrap/app.php` → sin errores de sintaxis.
- Flujo completo de la SPA por HTTP real contra el contenedor: cookie CSRF (204) → login (200) → perfil (200) → logout (204) → perfil (401).
- Códigos verificados por HTTP: 401 `NO_AUTENTICADO`, 404 `NO_ENCONTRADO`, 405 `METODO_NO_PERMITIDO`, 419 `CSRF_INVALIDO`, 422 `VALIDACION` con detalles por campo y 429 `LIMITE_EXCEDIDO` en las pruebas.

**Resultado:** Unidad 2 completada y verificada.

**Defectos reales encontrados y corregidos durante la verificación:**
1. **El contenedor no conectaba a MySQL** (`Connection refused` a 127.0.0.1): `artisan serve` elimina del proceso hijo toda variable que también exista en el `.env` montado, de modo que el `DB_HOST=mysql` de la composición se perdía y ganaba el `.env` orientado al host. Se corrigió con `--no-reload` en el comando del servicio backend.
2. **500 en `/auth/me` sin encabezado `Accept`**: el middleware de autenticación buscaba la ruta `login`, inexistente en una API pura. Se corrigió desactivando la redirección de invitados para que el 401 uniforme siempre responda.
3. **Defecto de seguridad**: había un registro de depuración que escribía en el log el identificador de sesión y las claves de cookies, en contradicción con el criterio de salida de la fase. Se eliminó.
4. **Errores HTTP no contemplados devolvían 500**: cualquier `HttpException` de Symfony (por ejemplo 405 o el 419 del CSRF) caía en el manejador genérico. Se añadió el mapeo por estado y se documentaron los códigos nuevos.

**Decisiones tomadas:**
- El login verifica primero las credenciales y solo después el estado de la cuenta, para no revelar qué correos existen (anti-enumeración).
- El pivote `warehouse_user` se declara explícitamente en los modelos porque el nombre por defecto de Eloquent sería `user_warehouse` y no coincide con el esquema documentado.
- Los códigos `CUENTA_INACTIVA`, `SOLICITUD_INVALIDA`, `METODO_NO_PERMITIDO` y `CSRF_INVALIDO` se añadieron al catálogo documentado de la API.

**Pendientes:**
- Unidad 3: autorización (Gates, Policies y alcance por almacén).
- Los cambios de las unidades 1 y 2 siguen sin confirmar en Git, a la espera de autorización.

**Nota:** las pruebas unitarias no detectaron los defectos 1 y 4 porque solo se manifiestan en el entorno contenedorizado y con errores HTTP fuera del camino feliz; se encontraron con la verificación por HTTP real, tal como exige el estándar de calidad del proyecto.

---

## Entrada 6 - 12/10/2026 - Fase 1, Unidad 3: autorización (Gates, Policies y alcance por almacén)

**Fase:** Fase 1 - Autenticación, RBAC y almacenes (Unidad 3 de 8).

**Archivos afectados (creados o modificados):**
- `backend/app/Domain/Enums/PermissionSlug.php` (nuevo): catálogo de 19 permisos.
- `backend/app/Http/Middleware/EnsurePermission.php` (nuevo): exige un permiso concreto.
- `backend/app/Exceptions/OutsideWarehouseScopeException.php` (nuevo): alcance insuficiente.
- `backend/app/Policies/WarehousePolicy.php`, `UserPolicy.php` y `RolePolicy.php` (nuevos).
- `backend/app/Http/Requests/Warehouse/IndexWarehouseRequest.php` (nuevo).
- `backend/app/Http/Controllers/Api/V1/WarehouseController.php` (nuevo) y `backend/routes/api/v1/warehouses.php` (nuevo).
- `backend/app/Providers/AppServiceProvider.php` y `backend/routes/api.php` (modificados).
- `backend/app/Models/User.php` (modificado): `canAccessWarehouse()`.
- `backend/app/Http/Resources/WarehouseResource.php` (modificado): dirección, ciudad, responsable y usuarios autorizados cuando vienen cargados.
- `backend/tests/Feature/Authorization/WarehouseAuthorizationTest.php` (nuevo).
- `docs/architecture/security-design.md` (modificado): catálogo de permisos del MVP.

**Cambios efectuados:**
- Se definió el catálogo de permisos como una enumeración tipada y, a partir de ella, un Gate por permiso; no se usó `Gate::before` para que ningún rol pueda saltarse el alcance por almacén.
- Se crearon las Policies de almacenes, usuarios y roles, y el middleware `permission`, más la excepción de alcance que responde 403 con el código `FUERA_DE_ALCANCE`.
- Se añadieron los dos endpoints de lectura de almacenes del contrato: el listado, que acota el alcance en la consulta y pagina, filtra, busca y ordena; y el detalle, que verifica permiso y alcance.

**Motivo:** aplicar en el servidor quién puede hacer qué y sobre qué almacén (RF-009, RF-011, RF-012), que es el criterio de salida de la fase: 403 para el almacén ajeno y auditor en solo lectura.

**Decisiones tomadas:**
- Se incorporaron a esta unidad los dos endpoints de lectura de almacenes (listado y detalle) porque sin superficie HTTP no es posible comprobar por petición real el 403 de alcance. La escritura de almacenes sigue en la unidad 5.
- La autorización no usa un atajo de superusuario: el Administrador recibe el permiso `warehouses.view_all` para ver todos los almacenes, pero sus demás acciones siguen sujetas a permisos y al alcance que corresponda.
- El alcance se resuelve en la consulta, nunca filtrando en memoria después de cargar todo.

**Pruebas ejecutadas (resultados reales):**
- `php artisan test` (host, contra MySQL) → 27 pruebas, 27 correctas, 157 aserciones; 12 de ellas de autorización.
- `./vendor/bin/pint --test` → sin diferencias.
- Verificación por HTTP real contra el contenedor, con datos temporales creados y eliminados al terminar:
  - El Administrador (con `warehouses.view_all`) lista los dos almacenes; el Operador lista solo el suyo.
  - El Operador consulta un almacén ajeno → `403 FUERA_DE_ALCANCE`.
  - Un usuario sin permisos lista almacenes → `403 SIN_PERMISO` (AC-005).
  - Tras retirar al Operador del almacén, su listado queda vacío y el detalle responde `403 FUERA_DE_ALCANCE` (AC-011).

**Resultado:** Unidad 3 completada y verificada.

**Pendientes:**
- Unidad 4: semillas de roles, permisos y administrador de desarrollo.
- Unidad 5: escritura de almacenes y asociación de usuarios autorizados.
- Los cambios de las unidades 1 a 3 siguen sin confirmar en Git, a la espera de autorización.

**Nota:** la base de desarrollo quedó limpia tras la verificación (sin usuarios, almacenes, roles ni permisos temporales).

---

## Entrada 7 - 12/10/2026 - Fase 1, Unidad 4: semillas de permisos, roles y administrador de desarrollo

**Fase:** Fase 1 - Autenticación, RBAC y almacenes (Unidad 4 de 8).

**Archivos afectados (creados o modificados):**
- `backend/database/seeders/PermissionSeeder.php` (nuevo): los 19 permisos desde la enumeración.
- `backend/database/seeders/RoleSeeder.php` (nuevo): los cuatro roles base con su matriz.
- `backend/database/seeders/DevAdminUserSeeder.php` (nuevo): administrador de desarrollo.
- `backend/database/seeders/DatabaseSeeder.php` (modificado): los ejecuta en orden.
- `backend/.env.example` (modificado): variables del administrador de desarrollo, vacías.
- `backend/.env` (local, ignorado por Git): valores del administrador de desarrollo.
- `backend/tests/Feature/Seeders/SeedersTest.php` (nuevo).
- `docs/architecture/security-design.md` (modificado): matriz de rol a permiso.

**Cambios efectuados:**
- Se sembró el catálogo de permisos desde la enumeración tipada, de modo que la semilla y la autorización no pueden divergir.
- Se crearon los cuatro roles con su matriz exacta, usando `sync` para que reejecutar la semilla corrija desviaciones en lugar de acumular permisos.
- Se añadió el administrador de desarrollo, que solo se crea si las variables de entorno están presentes y el entorno no es producción.

**Motivo:** cumplir RF-015 (roles base precargados) y el apartado de semillas del modelo físico, para que el sistema sea usable y verificable sin crear datos a mano.

**Decisiones tomadas:**
- La matriz de permisos se define para los módulos ya construidos y para los planificados; los permisos de módulos futuros quedan concedidos en la semilla y surten efecto cuando su módulo llegue, sin volver a tocar el seeder.
- El Auditor queda estrictamente en solo lectura, sin `inventory.confirm`, conforme a AC-006.
- Las unidades de medida y las categorías iniciales se difieren a la Fase 2 por pertenecer a ese módulo, según lo acordado en el modelo físico.
- El administrador de desarrollo nunca se crea en producción y sus credenciales viven solo en variables de entorno; el seeder avisa y continúa cuando faltan.

**Pruebas ejecutadas (resultados reales):**
- `php artisan test` (host, contra MySQL) → 37 pruebas, 37 correctas, 177 aserciones; 10 de ellas de semillas.
- `./vendor/bin/pint --test` → sin diferencias.
- `php artisan db:seed --force` ejecutado dos veces en el contenedor: los conteos quedaron idénticos (4 roles, 19 permisos, 1 usuario administrador), lo que demuestra la idempotencia.
- Verificación por HTTP real: inicio de sesión con el administrador sembrado → 200, con el rol `administrador` y 19 permisos; `GET /api/v1/warehouses` → 200.
- Comprobado que no hay credenciales escritas en el código.

**Resultado:** Unidad 4 completada y verificada. El sistema ya es operable con un usuario real.

**Pendientes:**
- Unidad 5: escritura de almacenes (crear, actualizar, desactivar) y asociación de usuarios autorizados.
- Unidades 6 y 7: CRUD de usuarios y de roles.
- Los cambios de las unidades 1 a 4 siguen sin confirmar en Git, a la espera de autorización.

**Nota:** la base de desarrollo conserva las semillas (roles, permisos y administrador), que es su estado esperado; la base de pruebas se reconstruye en cada ejecución de pruebas.

---

## Entrada 8 - 12/10/2026 - Base de conocimiento de agentes (AGENTS.md)

**Fase:** Tooling (no pertenece a ninguna fase del producto).

**Archivos afectados (creados o modificados):**
- `AGENTS.md` (nuevo, raíz): mapa del proyecto, dónde buscar, mapa de código, convenciones, anti-patrones, comandos y notas.
- `backend/AGENTS.md` (reemplazado): base de conocimiento de la API Laravel.
- `backend/CLAUDE.md` (reemplazado): puntero a `AGENTS.md`.

**Cambios efectuados:**
- Se generó la base de conocimiento jerárquica de agentes: exploración en paralelo con cinco agentes, análisis estructural, mapa de código y puntuación de directorios.
- Se sustituyeron las dos plantillas autogeneradas por Laravel Boost, que ordenaban instalar `laravel/boost` y subir a PHP 8.5, en contradicción con las decisiones fijadas (ADR-002 y ADR-017).

**Motivo:** dar a cualquier agente un mapa real del proyecto y sus reglas, sin duplicar la especificación que ya vive en `docs/`.

**Decisiones tomadas:**
- Solo se crearon dos archivos: raíz y `backend/`. Se omitieron `frontend/` (andamiaje, lo cubre la raíz) y `docs/` (ya tiene su propio índice en `docs/README.md`); crear más habría duplicado contenido.
- El contenido está en español, con los encabezados canónicos en inglés, para que coincidan con el resto de la documentación y sigan siendo localizables por las herramientas.
- No hay indexador (`.codegraph/` vacío) ni servidor de lenguaje PHP, así que la centralidad del código queda marcada como no medida en lugar de inventarse.

**Pruebas ejecutadas (resultados reales):**
- Recuento de líneas dentro de los límites previstos: raíz 99, backend 67, puntero 8.
- Un único encabezado H1 real por archivo (los conteos iniciales incluían comentarios `#` dentro de bloques de código).
- Sin emojis: los caracteres no ASCII son tipográficos (flechas, viñetas de árbol, acentos) y coinciden con los que ya usan los documentos del proyecto.
- Enlaces cruzados `./AGENTS.md` y `../AGENTS.md` verificados; sin duplicar las reglas del archivo global de instrucciones.

**Resultado:** base de conocimiento de agentes completada y verificada.

**Pendientes:**
- Los cambios de las unidades 1 a 4 y este siguen sin confirmar en Git, a la espera de autorización.
- Deriva detectada entre documentación y código, registrada en las notas del `AGENTS.md` de la raíz para corregirla al tocar esos documentos: `development-workflow.md` afirma que `backend/` y `frontend/` aún no existen, y ADR-006 figura como pendiente aunque Node 22 ya está instalado.

**Nota:** no se modificó ningún documento de la especificación; la bitácora es el único documento actualizado.

---

## Entrada 9 - 12/10/2026 - Planificación: importación de catálogo y carga inicial de existencias

**Fase:** Planificación (documentación, sin código de aplicación).

**Archivos afectados (modificados):**
- `docs/planning/functional-requirements.md`
- `docs/planning/requirements.md`
- `docs/planning/acceptance-criteria.md`
- `docs/planning/scope-and-milestones.md`
- `docs/planning/backlog.md`
- `docs/architecture/api-design.md`
- `docs/architecture/security-design.md`
- `docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md`
- `README.md`
- Esta bitácora.

**Cambios efectuados:**
- Se añadieron los requisitos RF-126 a RF-137 (importación de catálogo y carga inicial de existencias) con su sección propia en `functional-requirements.md`.
- Se añadieron las reglas RN-IMP-01 a RN-IMP-05, las historias HU-031 a HU-036 y la fila de trazabilidad del nuevo módulo en `requirements.md`.
- Se añadieron los criterios AC-061 a AC-070 en `acceptance-criteria.md`.
- Se añadieron las épicas EP-012 (importación de catálogo, Fase 2) y EP-013 (carga inicial de existencias, Fase 3) con su orden y grafo en `backlog.md`.
- Se añadieron los seis endpoints del módulo de importación con sus errores en `api-design.md`.
- Se añadió el control C-10 de subidas de archivos y su fila de amenaza en `security-design.md`.
- Se actualizaron las fases 2 y 3 (entregables, duración estimada y criterios de salida), la tabla MoSCoW y la decisión de ampliación del MVP en `scope-and-milestones.md`, más la especificación maestra y el `README.md`.

**Motivo:** la captura manual bloquea la adopción: cargar cientos de SKU a mano no es viable y el sistema debe arrancar con catálogo y saldos reales.

**Decisiones tomadas:**
- La importación es atómica (RN-IMP-01): si una fila falla, no se aplica ninguna.
- Toda importación es idempotente por clave (por SKU en el catálogo) y queda auditada con su autor (RN-IMP-04).
- La carga inicial se aplica como documento de entrada confirmado, nunca escribiendo directo en las existencias (RN-IMP-03).
- Ambos objetivos son Must del MVP, con impacto en la duración estimada (Fase 2 de 2 a 3 semanas, Fase 3 de 3 a 4 semanas).

**Pruebas ejecutadas:**
- Revisión de coherencia: los identificadores nuevos (RF-126 a RF-137, RN-IMP-01 a RN-IMP-05, HU-031 a HU-036, AC-061 a AC-070, EP-012 y EP-013) no colisionan con los existentes y ningún identificador previo fue renumerado ni eliminado.
- Revisión de referencias cruzadas y enlaces relativos entre los diez archivos modificados.

**Resultado:** planificación completada como cambio de documentación; ninguna funcionalidad se declara implementada.

**Pendientes:**
- Confirmación de Master sobre la prioridad MoSCoW y el impacto en el calendario.

**Nota:** esta entrada no registra pruebas de funcionalidad porque, por diseño de esta fase, no existe todavía código ejecutable de importación.

---

## Entrada 10 - 12/10/2026 - Fase 1, Unidad 5: escritura de almacenes y usuarios autorizados

**Fase:** Fase 1 - Autenticación, RBAC y almacenes (Unidad 5 de 8).

**Archivos afectados (creados o modificados):**
- `backend/routes/api/v1/warehouses.php` (modificado): grupos de lectura, creación y actualización con su permiso propio.
- `backend/app/Http/Requests/Warehouse/StoreWarehouseRequest.php` (nuevo).
- `backend/app/Http/Requests/Warehouse/UpdateWarehouseRequest.php` (nuevo).
- `backend/app/Http/Requests/Warehouse/AttachWarehouseUsersRequest.php` (nuevo).
- `backend/app/Http/Controllers/Api/V1/WarehouseController.php` (modificado): `store`, `update`, `deactivate`, `users`, `attachUsers` y `detachUser`.
- `backend/app/Http/Resources/UserSummaryResource.php` (nuevo): resumen del autorizado con roles.
- `backend/app/Models/User.php` (modificado): `accessibleWarehouseIds()` con `view_all` devuelve todos.
- `backend/tests/Feature/Warehouses/WarehouseWriteTest.php` (nuevo): 12 pruebas de escritura.
- `docs/architecture/api-design.md` (modificado): `code` opcional y único en la fila del `PUT` del §7.4.
- Esta bitácora.

**Cambios efectuados:**
- Se dividieron las rutas de almacenes en tres grupos para que cada endpoint exija su permiso del §7.4 (`warehouses.view` en lecturas y listado de autorizados, `warehouses.create` en la creación, `warehouses.update` en el resto); el alcance por almacén lo verifica la Policy en cada acción, no el middleware.
- Se crearon los endpoints de escritura: crear (201), actualizar, desactivar (lógica, con 409 `ESTADO_INVALIDO` si ya estaba inactivo) y gestión de autorizados (listado paginado, adjuntar acumulativo y retirar con 204 idempotente).
- Se corrigió `accessibleWarehouseIds()`: con `warehouses.view_all` devuelve todos los almacenes, activos o no, para que el Administrador siga viendo en el listado uno que acaba de desactivar; el filtro `is_active` del listado sigue existiendo como parámetro.
- Se precisó el contrato del §7.4: el `PUT` acepta `code` opcional y único, como exige AC-008 (renombrar).

**Motivo:** cerrar el ciclo de vida del almacén (RF-016, RF-019 a RF-023) y su matriz de permisos y alcance (AC-007, AC-008, AC-010 en su parte implementable y AC-011).

**Decisiones tomadas:**
- La desactivación es lógica sin borrado ni `deleted_at`: la historia se conserva (RF-020).
- Adjuntar usuarios es acumulativo (`syncWithoutDetaching`): nunca se revoca acceso en silencio; la retirada tiene su propio endpoint.
- El responsable (`manager_user_id`) no obtiene acceso automático al almacén: responsable y autorizados son conceptos separados del contrato.
- El `code` sí se puede renombrar y respeta la unicidad ignorando el propio registro (AC-008).
- El listado de autorizados usa `UserSummaryResource`, más ligero que `UserResource`, que queda reservado al propio autenticado.

**Pruebas ejecutadas (resultados reales):**
- `php artisan test` (host, contra MySQL) → 49 pruebas, 49 correctas, 254 aserciones; 12 de ellas de escritura.
- `./vendor/bin/pint` → sin diferencias; `./vendor/bin/pint --test` → sin diferencias.

**Pendientes:**
- La guarda de existencias al desactivar («rechaza si tiene existencias positivas») queda para la Fase 3, cuando exista la tabla `inventories`; no se simuló ni se consultó ninguna tabla inexistente.
- Unidades 6 y 7: CRUD de usuarios y de roles.
- Los cambios de las unidades 1 a 5 siguen sin confirmar en Git, a la espera de autorización.

**Nota:** la base de pruebas se reconstruye en cada ejecución; la base de desarrollo no se tocó en esta unidad.
