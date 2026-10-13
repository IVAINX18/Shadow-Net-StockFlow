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
| 11 | 12/10/2026 | Fase 1 - U6 y U7: CRUD de usuarios y de roles, más reactivación de almacenes | Completada (pruebas contra MySQL real) |

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

---

## Entrada 11 - 12/10/2026 - Fase 1, Unidades 6 y 7: CRUD de usuarios y de roles, más reactivación de almacenes

**Fase:** Fase 1 - Autenticación, RBAC y almacenes (Unidades 6 y 7 de 8).

**Archivos afectados (creados o modificados):**
- `backend/app/Http/Controllers/Api/V1/UserController.php` (nuevo): `index`, `show`, `store`, `update`, `activate` y `deactivate`.
- `backend/app/Http/Controllers/Api/V1/RoleController.php` (nuevo): `index`, `store`, `show` y `update`.
- `backend/app/Http/Controllers/Api/V1/PermissionController.php` (nuevo): `index` del catálogo.
- `backend/app/Http/Controllers/Api/V1/WarehouseController.php` (modificado): `activate`.
- `backend/app/Http/Requests/User/IndexUserRequest.php`, `StoreUserRequest.php` y `UpdateUserRequest.php` (nuevos).
- `backend/app/Http/Requests/Role/IndexRoleRequest.php`, `StoreRoleRequest.php`, `UpdateRoleRequest.php` e `IndexPermissionRequest.php` (nuevos).
- `backend/app/Http/Resources/PermissionResource.php` (nuevo): detalle mínimo del permiso.
- `backend/app/Http/Resources/RoleResource.php` (modificado): descripción y permisos cuando vienen cargados.
- `backend/app/Http/Resources/UserResource.php` (modificado): precarga completa de permisos anidados.
- `backend/app/Http/Controllers/Api/V1/WarehouseController.php` (modificado): precarga de descripción en autorizados.
- `backend/app/Policies/WarehousePolicy.php` (modificado): acción `activate`.
- `backend/routes/api/v1/users.php` y `roles.php` (nuevos); `backend/routes/api.php` y `routes/api/v1/warehouses.php` (modificados).
- `backend/tests/Feature/Users/UserCrudTest.php` (nuevo): 13 pruebas de usuarios.
- `backend/tests/Feature/Roles/RoleCrudTest.php` (nuevo): 10 pruebas de roles y catálogo.
- `backend/tests/Feature/Warehouses/WarehouseActivateTest.php` (nuevo): 3 pruebas de reactivación.
- `docs/architecture/api-design.md` (modificado): fila de `POST /api/v1/warehouses/{id}/activate` en el §7.4.
- Esta bitácora.

**Cambios efectuados:**
- Se crearon los seis endpoints de usuarios del §7.2: listado paginado con filtros (`is_active`, `role` por slug, `warehouse_id`), búsqueda `q` y orden cerrado; alta con `is_active = true` y 201; detalle con 404; actualización con `sync` de roles y almacenes; activación y desactivación con 409 `ESTADO_INVALIDO` al repetir y al desactivar al último administrador activo.
- Se crearon los cinco endpoints del §7.3: listado y detalle de roles con permisos, alta con 201, actualización sin tocar el `slug`, y catálogo de permisos paginado con filtro `module`.
- Se añadió la reactivación de almacenes (`POST /api/v1/warehouses/{id}/activate`, 409 si ya estaba activo), que el contrato no tenía como inverso de la desactivación.
- Las rutas se dividen por permiso (`users.view`, `users.create`, `users.update`, `roles.view`, `roles.create`, `roles.update`); el alcance por almacén no aplica a cuentas ni roles, que son globales.

**Motivo:** cerrar la administración de cuentas y roles (RF-005 a RF-015) con su matriz de permisos y alcance (AC-003, AC-005, AC-006, AC-055).

**Decisiones tomadas:**
- El listado de usuarios usa `UserSummaryResource` (ligero, para terceros); `UserResource` queda reservado al propio autenticado y al detalle, como ya hacía el listado de autorizados por almacén.
- El `PUT` de usuario aplica roles y almacenes con `sync` (sustitución completa), mientras el endpoint de autorizados por almacén sigue acumulativo (`syncWithoutDetaching`): son contratos distintos y no se mezclan.
- El `slug` del rol no se edita porque es su identificador estable (semillas y Policies lo usan como clave) y el contrato no lo lista.
- Los roles exponen sus permisos como detalle mínimo (`PermissionResource`: id, nombre, slug y módulo) solo cuando la relación viene cargada; el `RoleController` siempre la precarga.
- La guarda del último administrador cuenta activos con el rol `administrador` y responde 409 `ESTADO_INVALIDO`, código ya previsto para transiciones no permitidas.
- No se implementaron cambio de contraseña (RF-014, unidad de cuenta propia), borrado de usuarios ni borrado de roles: el contrato no los contempla en estas unidades.

**Pruebas ejecutadas (resultados reales):**
- `php artisan test` (host, contra MySQL) → 75 pruebas, 75 correctas, 414 aserciones; 26 de ellas de estas unidades.
- `./vendor/bin/pint` → sin diferencias pendientes; `./vendor/bin/pint --test` → sin diferencias.

**Pendientes:**
- Unidad 8: cierre de la Fase 1 (verificación integral y criterios de salida).
- Riesgo detectado sin corregir (1): un Administrador puede quitarse a sí mismo el permiso de administración (vía `PUT /users/{id}` sobre su propio `role_ids` o vía `PUT /roles/{id}` sobre el rol `administrador`) y quedarse sin acceso; el sistema lo permite y no avisa.
- Riesgo detectado sin corregir (2): el rol `administrador` puede perder el permiso `users.update` (vía `PUT /roles/{id}` con una lista reducida) y bloquear toda la gestión de cuentas; el sistema lo permite y no avisa.
- Los cambios de las unidades 1 a 7 siguen sin confirmar en Git, a la espera de autorización.

**Nota:** no se modificaron el esquema ni las migraciones; la base de desarrollo no se tocó en esta unidad.

---

## Entrada 12 - 12/10/2026 - Fase 1, corrección del contrato del PUT de usuarios

**Fase:** Fase 1 - Autenticación, RBAC y almacenes (corrección posterior al cierre de las unidades).

**Archivos afectados (creados o modificados):**
- `backend/app/Http/Requests/User/UpdateUserRequest.php` (modificado): regla `prohibited` para `password` y su mensaje en español.
- `backend/tests/Feature/Users/UserCrudTest.php` (modificado): una prueba nueva.
- `docs/architecture/api-design.md` (modificado): el §7.2 declara que `password` se rechaza con 422.
- Esta bitácora.

**Cambios efectuados:**
- `password` viaja con la regla `prohibited`: si llega con valor, el endpoint responde 422 `VALIDACION` con el detalle `{field: "password", message: "La contraseña no se cambia por este endpoint."}`, en lugar de aceptar la petición y no cambiar nada.
- Un valor vacío se sigue tolerando, porque un formulario puede enviar el campo sin contenido.
- Se añadió la prueba que cubre el 422, comprueba que la contraseña original no se altera y verifica que el campo vacío no impide el resto de la actualización.

**Motivo:** el `PUT` respondía 200 aunque la contraseña no se modificara, lo que podía hacer creer a un cliente que el cambio había ocurrido.

**Decisiones tomadas:**
- `prohibited` y no `missing`: `missing` rechazaría también el campo vacío, y un formulario de edición puede enviarlo así; `prohibited` rechaza el intento real y tolera el campo sin contenido.
- No se aplica la misma regla al `slug` del rol, que también se ignora con 200: un formulario puede reenviar el valor que cargó (el identificador que muestra la ficha) y rechazarlo rompería una actualización legítima. Queda como diferencia consciente; endurecerlo sería la misma línea si se decide.
- No se tocaron el esquema ni las migraciones.

**Pruebas ejecutadas (resultados reales):**
- `php artisan test` (host, contra MySQL) → 76 pruebas, 76 correctas, 421 aserciones.
- `./vendor/bin/pint --test` → sin diferencias pendientes.
- Comprobación por HTTP real contra el contenedor: `PUT /api/v1/users/{id}` con `password` → 422 con el campo y el mensaje esperados; con `password` vacío → 200; el login con la contraseña original → 200 y con la intentada → 401.

**Pendientes:**
- Los dos riesgos de auto-bloqueo registrados en la Entrada 11 siguen sin corregir.
- Las unidades 1 a 7 ya están confirmadas y publicadas en Git (`27058fc`).
- Corrección de la Entrada 11: el conteo real fue 14 pruebas de usuarios y 9 de roles (la bitácora decía 13 y 10); el total de 23 era correcto.

**Nota:** no se modificaron el esquema ni las migraciones; la base de desarrollo no se tocó en esta unidad.

---

## Entrada 13 - 12/10/2026 - Frontend: base tecnica, sistema de diseno, autenticacion y armazon (F0-F3)

**Fase:** Fase 1 - Autenticacion, RBAC y almacenes (unidades de frontend F0 a F3, sobre la API ya cerrada de la Fase 1).

**Archivos afectados (creados o modificados):**
- `frontend/tsconfig.json` (modificado): `strict`, `noUnusedLocals`, `noUnusedParameters` y `strictTemplates` activados.
- `frontend/eslint.config.mjs` (nuevo): flat config con `angular-eslint` y `typescript-eslint`; script `lint` en `package.json`.
- `frontend/vitest.config.mts` (nuevo) y `frontend/angular.json` (`runnerConfig`): pruebas con un solo proceso hijo.
- `frontend/package.json` (modificado): `@angular/material` 22.2, `@angular/cdk` 22.2, `@fontsource/roboto`, `@fontsource/roboto-mono`, `material-symbols`, ESLint y `angular-eslint`; scripts `lint` y `palette:generate`.
- `frontend/angular.json` (modificado): entornos con `fileReplacements`, copia de las fuentes a `/fonts`, y los target `lint` y `test`.
- `frontend/tools/generate-palette.mjs` (nuevo): deriva la paleta tonal M3 de una unica semilla con `@material/material-color-utilities`.
- `frontend/src/styles.scss` y `frontend/src/styles/_palette.scss` (nuevo y generado): tema Material 3, fuentes autoalojadas, densidad, escala de espaciado e indices de apilamiento.
- `frontend/src/environments/{environment,environment.staging,environment.prod}.ts` (nuevos) y `core/services/api-base-url.token.ts`.
- `frontend/src/app/core/` (nuevo): `api.service`, `error.service`, `auth.service`, `theme.service`, interceptores de autenticacion y de errores, guardias `auth.guard` y `role.guard`, y modelos tipados.
- `frontend/src/app/shared/components/` (nuevo): `page-header`, `kpi-card`, `data-table`, `empty-state`, `error-state`, `loading-skeleton`, `status-chip`, `access-denied` y `module-pending`.
- `frontend/src/app/features/` (nuevo): `auth` (inicio de sesion), `shell` (armazon y navegacion), `dashboard`, `warehouses`, `users`, `roles` y `style-guide`.
- `frontend/src/app/{app.ts,app.html,app.scss,app.routes.ts,app.config.ts,app.spec.ts}` (modificados): raiz minima, rutas perezosas con guardias, configuracion zoneless con HTTP e interceptores, y pruebas reales.
- `frontend/src/index.html` (modificado): `lang="es"`, `color-scheme` y precarga de las fuentes de texto.
- `backend/config/sanctum.php` (modificado) y `backend/config/cors.php` (nuevo): origen de la SPA autorizado con credenciales.
- `.env.example` y `backend/.env.example` (modificados): variable `FRONTEND_URL` documentada.
- `.github/workflows/ci.yml` (modificado): el trabajo del frontend ahora ejecuta estilo, pruebas y compilacion.
- Esta bitacora.

**Cambios efectuados:**
- Se activo el tipado estricto que la documentacion exigia y no estaba, y se añadio el linter que no existia; `npm run lint`, `npm test` y `npm run build` forman la verificacion del frontend.
- Se construyo el sistema de diseno: paleta tonal M3 derivada de una unica semilla verde azulado, tema claro y oscuro, Roboto y Roboto Mono autoalojadas, Material Symbols como unica fuente de iconos, densidad compacta en las vistas de datos, cifras tabulares, escala de espaciado y escala de indices de apilamiento.
- Se construyo el nucleo HTTP con interceptores funcionales: credenciales y CSRF de Sanctum, normalizacion del sobre de error a un `AppError` tipado (validacion por campo, autorizacion, negocio, servidor), renovacion de la cookie CSRF y reintento unico ante 419, y redireccion al inicio de sesion ante 401 conservando la ruta deseada.
- Se construyo la autenticacion con Signals (sesion, perfil, permisos y almacenes), inicio de sesion con formulario reactivo tipado, cierre de sesion y guardias de navegacion.
- Se construyo el armazon: navegacion lateral colapsable agrupada, barra superior con marca, migas, conmutador de tema y menu de usuario; la visibilidad de cada seccion se filtra con los slugs de permiso reales de `/auth/me`.
- El tablero muestra solo los indicadores que las API reales soportan hoy (almacenes, usuarios, roles y permisos) y, aparte, tarjetas de dependencia que nombran la fase y el endpoint de cada indicador que aun no existe. Los once modulos sin API (productos, categorias, inventario, movimientos, transferencias, reportes, auditoria y configuracion) apuntan a una pantalla de "modulo pendiente": no se simulan datos en ningun punto.
- La guia de estilo `/guia-estilo` renderiza roles de color, escala tipografica, botones, campos, chips y los estados de carga, vacio y error para revision visual en ambos temas.
- El backend necesitaba autorizar el origen de la SPA para la sesion por cookie: se añadio `localhost:4200` a los dominios stateful de Sanctum y un `config/cors.php` con credenciales, documentando la variable `FRONTEND_URL`.

**Motivo:** la Fase 1 estaba cerrada solo en la API y el frontend no existia. Esta unidad entrega la base tecnica, el sistema de diseno, el nucleo HTTP, la autenticacion y el armazon sobre los que se construyen las pantallas de cada fase, con la SPA verificada contra la API real.

**Decisiones tomadas:**
- **Paleta que consume el tema:** `mat.theme` recibe `$stockflow-brand-palette`, que ademas de los dieciseis tonos incluye los sub-mapas `secondary`, `neutral`, `neutral-variant` y `error`, y la paleta `neutral` incorpora los tonos intermedios (4, 6, 12, 17, 22, 24, 87, 92, 94, 96). Es un requisito de Angular Material: sin esos sub-mapas los roles `surface-container-*`, `error`, `secondary` y `outline` se emiten vacios (`light-dark(, )`) y el tema queda roto. Verificado compilando el SCSS: cero roles vacios.
- **Un solo bloque de tema:** los dos esquemas se emiten en una unica invocacion y el navegador los resuelve con `light-dark()`; `ThemeService` solo escribe `color-scheme` en la raiz y persiste la eleccion, sin clase de tema oscuro que mantener.
- **Sin `@angular/animations`:** Angular Material 22 no lo importa en tiempo de ejecucion, asi que no se instala ni se registra `provideAnimationsAsync`.
- **Fuentes autoalojadas como assets** con nombre estable en `/fonts` y precarga de los pesos de texto en `index.html`; Material Symbols no se precarga por su tamano.
- **Pruebas con un solo proceso** (`forks`) sin paralelismo: el pool de hilos no arranca dentro del tiempo de espera en este equipo (riesgo R-10 de memoria ajustada).
- **Modulos sin API:** pantalla de dependencia con la fase y los endpoints que faltan, nunca datos de demostracion.
- **Unica excepcion de backend autorizada:** los dos ficheros de configuracion de origen y CORS; nada mas se toco en `backend/`.

**Pruebas ejecutadas (resultados reales):**
- `npm run lint` -> "All files pass linting".
- `npm test` -> 4 archivos, 12 pruebas, todas correctas.
- `npm run build` -> correcto. Paquete inicial 339,49 kB en crudo (92,27 kB de transferencia estimada) y estilos 12,26 kB, por debajo del aviso de 500 kB y del error de 1 MB; una carga perezosa por pantalla (login 24,39 kB, guia de estilo 12,09 kB, tablero 7,39 kB, armazon 97,25 kB).
- Compilacion del SCSS e inspeccion del CSS emitido: cero tokens `light-dark(, )` vacios; `--mat-sys-surface-container: light-dark(#eceeec, #1d201f)`; `--mat-sys-primary: light-dark(#006b5e, #56dbc5)`.
- Inspeccion del artefacto: las siete fuentes (`roboto-latin-{400,500,700}`, `roboto-mono-latin-{400,500,700}`, `material-symbols-outlined`) se copian a `dist/frontend/browser/fonts`.
- Flujo de autenticacion por HTTP contra el contenedor: `GET /sanctum/csrf-cookie` (204), `POST /api/v1/auth/login` (200 con el usuario y sus 19 permisos) y `GET /api/v1/auth/me` (200). Preflight `OPTIONS` con `Origin: http://localhost:4200` responde `Access-Control-Allow-Origin: http://localhost:4200` y `Access-Control-Allow-Credentials: true`.
- `npm run palette:generate` -> regenera la paleta desde la semilla sin errores.

**Pendientes:**
- No se pudo verificar el render en un navegador: el entorno no tiene navegador ni herramienta de automatizacion instalados. La revision visual de ambos temas y de `/guia-estilo` queda pendiente y se declara como no realizada.
- `environment.staging.ts` y `environment.prod.ts` contienen URLs de ejemplo (dominio reservado `.example`) que hay que sustituir antes de desplegar.
- Con la cache fria, el arranque del trabajador de Vitest en este disco agota el tiempo en la primera ejecucion; a partir de la segunda pasa. Conviene vigilarlo en la integracion continua.
- Los dos riesgos de auto-bloqueo registrados en la Entrada 11 siguen sin corregir.

**Nota:** no se modificaron el esquema ni las migraciones. Del backend solo cambiaron los dos ficheros de configuracion de origen y CORS (excepcion autorizada). Los cambios no se confirman en Git a la espera de autorizacion.

## Entrada 14 - 13/10/2026 - Frontend: refinamiento de gusto visual (sistema de diseno v1.1)

**Fase:** Fase 1 - refinamiento transversal del armazon y de las pantallas ya existentes. No abre unidades nuevas ni toca el backend.

**Archivos afectados (creados o modificados):**
- `docs/architecture/design-system.md` (v1.1): tipografia, vocabulario de forma, ritmo de composicion, excepcion cerrada de color para la advertencia, adaptacion de la referencia externa y casillas nuevas de verificacion.
- `frontend/package.json` y `package-lock.json`: se añaden `@fontsource/inter` y `@fontsource/jetbrains-mono`; se retiran `@fontsource/roboto` y `@fontsource/roboto-mono`.
- `frontend/angular.json` e `frontend/src/index.html`: copia de las fuentes nuevas y precarga de los pesos de texto.
- `frontend/src/styles.scss`: tipografia del tema sobre Inter, escalas de ritmo, utilidad de tracking cerrado, radio 8 para botones y el par semantico de advertencia.
- `frontend/src/app/features/{auth,shell,dashboard,warehouses,users,style-guide}`: ritmo por tokens, tracking cerrado en titulares, respuesta fisica al pulsar y bordes finos.
- `frontend/src/app/shared/components/`: los nueve componentes compartidos, incluido el defecto corregido de los tonos de estado.
- Esta bitacora.

**Cambios efectuados:**
- Se actualizo el contrato a la v1.1: diales (`MOTION_INTENSITY` de 2 a 3), familias tipograficas, tabla unica de radios, tokens de ritmo, realimentacion tactil, y la adaptacion de la referencia externa con lo que se adopta y lo que se descarta a proposito.
- Se cambio la tipografia de Roboto a Inter (400, 500 y 600) y JetBrains Mono (400 y 500), autoalojadas. Roboto desaparece del proyecto.
- Se fijo el vocabulario de forma: etiquetas e insignias en pildora, botones en 8 px, campos en 4 px, tarjetas en 12 px y superficies grandes en 16 px. Los botones dejan de ser pildora.
- Se añadieron los tokens de ritmo (`--app-page-gutter`, `--app-section-gap`, `--app-card-padding`, `--app-element-gap`) y se sustituyeron los margenes arbitrarios de las pantallas.
- Toda accion interactiva responde al pulsarse con un hundimiento de 1 px o escala 0.98 y transicion de 140 ms.
- **Defecto corregido:** los tonos de estado no eran distinguibles. Primero informacion y advertencia compartian `secondary-container`; al separarlos, exito y advertencia quedaron con el mismo color porque la paleta de semilla unica colapsa primario y terciario. Se declaro el unico par semantico de advertencia autorizado y ahora los cinco tonos son cinco colores distintos.
- Se amplio la guia de estilo de nueve a catorce secciones: vocabulario de forma con muestras reales, elevacion y separacion, ritmo de composicion, estados de boton, familias tipograficas y tonos de estado con etiquetas en español.

**Motivo:** el sistema ya era plano y de borde fino, pero la tipografia era la generica del sistema, el vocabulario de forma no estaba escrito en ninguna parte y un defecto real de tonos de estado quedaba oculto por una prueba que comprobaba nombres de clase en lugar de colores. El refinamiento ataca la tipografia y el detalle, que es donde estaba la mejora pendiente.

**Decisiones tomadas:**
- **Inter y JetBrains Mono:** la guia de gusto que rige estas decisiones desaconseja Inter por defecto, pero su excepcion explicita cubre las interfaces neutras al estilo Linear y de accesibilidad critica, y la referencia externa nombra Inter como su sustituta declarada. La excepcion queda registrada en el contrato.
- **Se conserva el acento verde azulado:** de la referencia externa se aprende el sistema, no la identidad. No se adopta su azul, ni su fondo de rejilla de puntos, ni su tema unico claro.
- **Radio de botones verificado, no adivinado:** los nombres de los tokens de forma se comprobaron en el SCSS de Material instalado, en el mapa de formas de Material 3 y en el CSS compilado.
- **Un unico par de tokens semanticos de advertencia:** Material 3 no define rol de advertencia y la paleta de semilla unica hace que primario y terciario sean el mismo color, de modo que cinco tonos distintos no caben en los roles de Material. La excepcion es un unico par ambar, cerrado y documentado.
- **La distincion de color se mide, no se supone:** queda escrito en el contrato y en la prueba que una suite verde en DOM simulado no prueba color.

**Pruebas ejecutadas (resultados reales):**
- `npm run lint` -> "All files pass linting"; `npm test` -> 3 archivos, 11 pruebas, todas correctas; `npx ng build --configuration development` -> correcto, con las cinco fuentes nuevas copiadas a `fonts` y sin rastro de Roboto.
- Pre-flight automatizado sobre el arbol fusionado: cero guiones largos o medios en el codigo, cero radios en pixeles, cero colores literales fuera de la paleta generada y del par de advertencia, una sola sombra (la del ejemplo declarado en la guia) y ninguna referencia a Roboto salvo el texto que lo menciona.
- Verificacion con navegador real (Thorium): el acceso entra al tablero, las once secciones montan, cero excepciones no capturadas y un unico error de consola, el 401 esperado del arranque sin sesion.
- Tema medido sobre colores calculados: claro `rgb(247, 250, 248)` y oscuro `rgb(16, 20, 19)`.
- Contrastes medidos (minimo AA 4.5 en texto y 3.0 en controles): etiqueta 14.72, ayuda 14.72, subtitulo 7.99, borde de campo 3.85 y boton 6.43. Todos cumplen.
- Tonos de estado medidos: cinco fondos distintos (`rgb(218,229,225)`, `rgb(205,232,225)`, `rgb(118,248,225)`, `rgb(254,243,199)` y `rgb(255,218,214)`), con contraste minimo de 7.22.
- Ritmo del acceso medido entre elementos completos: etiqueta a campo 4 px, campo a campo 24 px y campo a boton 24 px, con `row-gap` declarado de 24 px. La ayuda queda alineada con el campo con diferencia de 0 px.
- Guia de estilo: catorce secciones, tabla de radios con cinco muestras reales, elevacion con contraste entre contenedor de borde y capa flotante, barras de ritmo y cinco estados de boton.

**Leccion registrada:** una prueba que comprueba que dos tonos usan clases distintas pasa aunque los dos colores sean identicos. La comprobacion que vale es medir el color calculado en un navegador real. Esa medicion es la que descubrio el defecto.

**Pendientes:**
- La captura de la guia de estilo a pagina completa exige un viewport alto, porque el armazon desplaza dentro de su propio contenedor y `fullPage` recorta.
- `environment.staging.ts` y `environment.prod.ts` siguen con URLs de ejemplo; los dos riesgos de auto-bloqueo de la Entrada 11 y el arranque del trabajador de Vitest con cache fria siguen abiertos.
- Sustituir la tipografia y el vocabulario de forma cambio medidas de todas las pantallas: conviene revisar de nuevo cualquier pantalla que se añada en fases posteriores contra la tabla de la seccion 7.

**Nota:** no se toco el backend ni el esquema. La unica dependencia nueva son los paquetes de fuentes autoalojadas. Los cambios no se confirman en Git a la espera de autorizacion.
