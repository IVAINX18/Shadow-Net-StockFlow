# Bitácora de desarrollo - Shadow-Net StockFlow

**Autor:** Shadow-Net Technologies. **Zona horaria:** America/Bogota. **Formato de fecha:** DD/MM/AAAA.

Esta bitácora registra cada unidad de trabajo del proyecto: fecha, fase, archivos afectados, cambios, motivo, pruebas ejecutadas, resultado y pendientes. Se actualiza al cerrar cada unidad y nunca se reescribe el pasado: una corrección añade una entrada nueva.

## Resumen de entradas

| # | Fecha | Fase | Resultado |
| --- | --- | --- | --- |
| 1 | 10/10/2026 | Fase 0 - Planificación | Completada |
| 2 | 10/10/2026 | Fase 0 - Fundaciones | Completada (verificación de `docker compose up` pendiente del demonio Docker) |
| 3 | 10/10/2026 | Fase 0 - Fundaciones | Completada (entorno verificado con Podman; ADR-017 reemplaza ADR-011) |

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
