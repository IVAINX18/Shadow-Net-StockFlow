# Registro de decisiones de arquitectura (ADR) - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento registra las decisiones de arquitectura del MVP con su contexto, alternativas evaluadas y consecuencias. Cada decisión tiene un identificador estable (ADR-NNN). Las versiones se verificaron contra fuentes oficiales el 10/10/2026 y no se inventaron. El detalle funcional vive en [../planning/requirements.md](../planning/requirements.md); el dominio de inventario en [inventory-domain.md](./inventory-domain.md); el modelo de datos en [database-design.md](./database-design.md).

## 1. Cómo leer este documento

Cada ADR responde: qué problema había (**Contexto**), qué se decidió (**Decisión**), qué se comparó (**Alternativas**) y qué se gana o se sacrifica (**Consecuencias**). Una ADR no se borra: si cambia, se marca como reemplazada por otra.

## 2. Resumen de versiones del MVP

Versiones estables verificadas (Packagist, npm, docs oficiales) el 10/10/2026:

| Componente | Versión fijada | Restricción | Nota de compatibilidad |
| --- | --- | --- | --- |
| PHP | 8.4.x (runtime 8.4.26 local) | `^8.3` | Laravel 13 exige PHP `^8.3`. |
| Laravel | 13.35.x | `^13.0` | Línea actual; 12 quedó en solo-seguridad. |
| Laravel Sanctum | 4.3.x | `^4.0` | Soporte de Laravel 13 desde 4.3.1. |
| Composer | 2.10.3 | herramienta | Instalar con el instalador oficial. |
| Laravel Pint | 1.32.x | `^1.0` (dev) | Estilo de código. |
| Pest | 5.3.x | `^5.0` (dev) | Ejecutor de pruebas (construido sobre PHPUnit 13). |
| dompdf (barryvdh) | 3.1.x | `^3.0` | Exportación PDF. |
| maatwebsite/excel | 4.0.x | `^4.0` | Exportación Excel; compatible con Laravel 13. |
| Angular | 22.2.x | `~22.2.0` | Exige Node `^22.22.3 || ^24.15.0 || >=26`. |
| Node.js | 22 LTS (22.23.x) o 24 LTS | `^22.22.3 || ^24.15.0` | **Subir desde el 20.19.2 actual (EOL).** |
| TypeScript | 6.0.x | `>=6.0.0 <6.1.0` | Requerido por Angular 22. |
| RxJS | 7.4.x | `^6.5.3 || ^7.4.0` | — |
| @angular/material | 22.2.x | `~22.2.0` | UI accesible. |
| MySQL | 8.4 LTS (8.4.11) | `8.4.x` | InnoDB, ACID, bloqueo a nivel de fila. |
| Podman / Buildah / podman-compose | 5.4.2 / 1.39.3 / 1.6.0 | — | Entorno canónico reproducible, sin demonio (ADR-017). Docker 26.1.5 sigue instalado en el host pero su demonio está apagado y no se usa. |

## 3. Registro de decisiones

### ADR-001. Monolito modular con API-first (no microservicios)

- **Contexto:** se necesita un ERP que un solo desarrollador pueda construir, entender y desplegar.
- **Decisión:** una API monolítica modular en Laravel (capas: rutas, controladores finos, casos de uso, dominio, acceso a datos) y un frontend Angular que consume esa API. Un solo artefacto de backend, un solo frontend.
- **Alternativas:** microservicios (un servicio por dominio) y monolito con vistas Blade embebidas.
- **Consecuencias:** menos piezas móviles, transacciones locales simples (clave para el inventario), despliegue reproducible. La modularidad interna deja la puerta abierta a extraer servicios si algún día hace falta, sin pagar ese costo hoy.

### ADR-002. Laravel 13 sobre PHP 8.4

- **Contexto:** el entorno local tiene PHP 8.4.26; Laravel 12 entró en solo-seguridad en 2026.
- **Decisión:** Laravel 13.35.x, que exige PHP `^8.3` y soporta 8.4.
- **Alternativas:** Laravel 12 (solo parches de seguridad, no conviene en greenfield); Laravel 11 (EOL).
- **Consecuencias:** esqueleto moderno (`bootstrap/app.php`, sin HTTP Kernel), bug fixes hasta 2027. Se fija `"php": "^8.3"` en `composer.json`, no el parche exacto.

### ADR-003. Autenticación con Laravel Sanctum

- **Contexto:** el cliente es una SPA Angular y también se prevé acceso por API de terceros.
- **Decisión:** Sanctum 4.x. Para la SPA propia se usa autenticación por cookie de sesión de estado (`statefulApi`), no tokens de API; para integraciones futuras, tokens con habilidades (`abilities`).
- **Alternativas:** Laravel Fortify (añade 2FA/passkeys, fuera del MVP); JWT puro (más piezas y sin el manejo de sesiones de Laravel).
- **Consecuencias:** la SPA se autentica por cookie más CSRF; `tokenCan()` devuelve verdadero para la SPA propia, por lo que la autorización real se hace con Policies, nunca confiando solo en el token. Se implementan a mano los endpoints de login, logout y perfil; Fortify se reevalúa en V1 para recuperación de contraseña y 2FA.

### ADR-004. RBAC nativo con Roles, Permisos, Gates y Policies

- **Contexto:** se necesita control de acceso por rol (Administrador, Jefe de almacén, Operador, Auditor) y por almacén.
- **Decisión:** implementar RBAC nativo: tablas `roles`, `permissions`, pivotes `role_user` y `permission_role`, y autorización con Gates y Policies de Laravel. El alcance por almacén se modela con el pivote `warehouse_user`.
- **Alternativas:** paquete `spatie/laravel-permission` 8.x (estándar de la industria).
- **Consecuencias:** se gana control total y valor pedagógico (el autor comprende el mecanismo de autorización), y se evita una dependencia que no resuelve el alcance por almacén (que es requisito propio). Se sacrifica la comodidad del trait `HasRoles`. Se documenta que, si la complejidad de RBAC creciera mucho, se reevaluaría Spatie en V1.

### ADR-005. MySQL 8.4 LTS con InnoDB

- **Contexto:** el núcleo exige transacciones, integridad referencial y bloqueo de filas.
- **Decisión:** MySQL 8.4 LTS (parche 8.4.11), motor InnoDB. Aislamiento `REPEATABLE READ` por defecto (InnoDB), que junto con `SELECT ... FOR UPDATE` serializa las operaciones de inventario.
- **Alternativas:** MySQL 9.7 LTS (también LTS, más nuevo); PostgreSQL (excelente, pero el stack objetivo pide MySQL); SQLite (inservible para pruebas de concurrencia reales).
- **Consecuencias:** InnoDB da ACID, bloqueo a nivel de fila y las cuatro isolation levels; se admiten restricciones `CHECK` (aplicadas desde MySQL 8.0.16) e índices sobre columnas generadas. MySQL 8.0 está fuera de soporte activo (abr-2026), por lo que no se usa.

### ADR-006. Angular 22 con Node 22 LTS (decisión de entorno pendiente de confirmación)

- **Contexto:** el entorno local tiene Node 20.19.2, que alcanzó EOL el 30/04/2026. Angular 22.2.x exige Node `^22.22.3 || ^24.15.0 || >=26`.
- **Decisión propuesta:** **subir Node a 22 LTS (22.23.x) o 24 LTS** y fijar Angular 22.2.x, TypeScript 6.0.x y Material 22.2.x. Angular 21+ es zoneless por defecto y los Signals son estables.
- **Alternativas:** (a) mantener Node 20.19.2 con Angular 21.x (funciona, pero arrastra un runtime EOL); (b) fijar Node 22 dentro de Docker y no tocar el host.
- **Consecuencias:** Angular 22 habilita Signal Forms, `resource()`/`httpResource()` estables y formularios tipados. **Esta decisión cambia el entorno del host y requiere confirmación de Master**; se recomienda Node 22 LTS. Si Master prefiere no tocar el host, la alternativa (b) aísla Node 22 en Docker, o la (a) baja a Angular 21.

### ADR-007. Valorización por promedio ponderado móvil

- **Contexto:** hay que valorizar el inventario; FIFO y promedio ponderado son los candidatos.
- **Decisión:** promedio ponderado móvil por producto y almacén.
- **Alternativas:** FIFO por capas de costo.
- **Consecuencias:** sin lotes en el MVP, el promedio ponderado es más simple, robusto y reversible. FIFO se pospone a V1/V2 cuando existan lotes. Detalle y fórmula en [inventory-domain.md](./inventory-domain.md) (§8).

### ADR-008. Modelo documento - línea - movimiento (libro contable)

- **Contexto:** "actualizar una columna de stock" no da trazabilidad ni permite estados, reversiones ni auditoría.
- **Decisión:** separar la intención de negocio (`inventory_documents` + `inventory_document_lines`) del efecto (`inventory_movements`, append-only). El saldo materializado (`inventories`) se actualiza en la misma transacción.
- **Alternativas:** una sola tabla de movimientos sin documento (pierde estados y agrupación); actualizar solo `inventories` con un log de texto (pierde integridad y kardex consultable).
- **Consecuencias:** kardex consultable, estados y reversiones trazables. Se paga con una transacción algo más larga y tres tablas en vez de una.

### ADR-009. Bloqueo pesimista con `SELECT ... FOR UPDATE`

- **Contexto:** las operaciones concurrentes pueden producir sobreventa o saldos negativos.
- **Decisión:** bloquear las filas de `inventories` con `SELECT ... FOR UPDATE`, siempre en orden consistente por `(warehouse_id, product_id)`, con reintento acotado ante deadlock.
- **Alternativas:** actualización optimista con columna de versión (más reintentos en el cliente).
- **Consecuencias:** garantías simples de razonar para la escala objetivo; el orden consistente evita interbloqueos. Ver [inventory-domain.md](./inventory-domain.md) (§6).

### ADR-010. Pest como ejecutor de pruebas

- **Contexto:** el stack permite PHPUnit o Pest.
- **Decisión:** usar Pest 5.3.x (sobre PHPUnit 13) como ejecutor principal, con pruebas unitarias, de integración, de autorización y de concurrencia.
- **Alternativas:** PHPUnit 13.4.x puro.
- **Consecuencias:** sintaxis más expresiva y menor ceremonia; las aserciones subyacentes son de PHPUnit, así que el aprendizaje se transfiere. No se instalan ambos como ejecutor principal.

### ADR-011. Docker Compose como entorno canónico

- **Estado:** Reemplazada por ADR-017.
- **Contexto:** faltan localmente Composer y MySQL; se quiere un entorno reproducible.
- **Decisión:** `docker-compose.yml` con servicios de MySQL, backend (PHP-FPM/artisan) y frontend (Node). El desarrollo puede hacerse en el host o en contenedores, pero el entorno de referencia es el contenedor.
- **Alternativas:** instalar todo en el host.
- **Consecuencias:** reproducibilidad y paridad con integración continua. Se documentan los requisitos de instalación local como alternativa. Se vigila el consumo de recursos (RAM ajustada).

### ADR-012. OpenAPI como contrato de la API

- **Contexto:** se necesita documentar y validar la API.
- **Decisión:** documentar la API v1 con una especificación OpenAPI, generada y versionada en el repositorio, servida para consulta.
- **Alternativas:** documentación manual en Markdown (se desincroniza).
- **Consecuencias:** contrato único y comprobable; permite generar clientes o mocks en el futuro. El diseño de endpoints vive en [api-design.md](./api-design.md).

### ADR-013. Colas de base de datos en el MVP (sin Redis)

- **Contexto:** reportes pesados y correos pueden bloquear la petición.
- **Decisión:** usar el driver de colas de base de datos de Laravel para trabajos asíncronos (exportaciones grandes). Redis queda fuera del MVP.
- **Alternativas:** Redis como backend de colas y caché.
- **Consecuencias:** una pieza menos que operar; suficiente para el volumen del MVP. Redis se reevalúa si la carga lo exige. Prohibido por regla salvo justificación.

### ADR-014. Signals y Reactive Forms en el frontend

- **Contexto:** se necesita estado reactivo y formularios complejos.
- **Decisión:** usar Signals para el estado local y `computed()` para derivados; Reactive Forms para formularios complejos; `provideHttpClient` con interceptores funcionales para autenticación y manejo centralizado de errores.
- **Alternativas:** Signal Forms (estables en Angular 22, pero más nuevos y con menos material de aprendizaje disponible); NgRx (sobredimensionado para el MVP).
- **Consecuencias:** patrón moderno sin sobrecarga de librería de estado global. Guardias de ruta como ayuda de navegación, nunca como sustituto de la autorización del backend.

### ADR-015. Librerías de exportación PDF y Excel

- **Contexto:** los reportes deben exportarse a PDF y Excel.
- **Decisión:** `barryvdh/laravel-dompdf` 3.1.x para PDF y `maatwebsite/excel` 4.0.x para Excel.
- **Alternativas:** PhpSpreadsheet directo (más código); generación en el frontend (duplica lógica y expone datos).
- **Consecuencias:** exportación en el backend, reutilizable y con las mismas reglas de autorización.

### ADR-016. Estructura del repositorio (monorepo documentación-primero)

- **Contexto:** el ecosistema Shadow-Net organiza repositorios con la especificación en `docs/`.
- **Decisión:** un solo repositorio con `docs/` (esta especificación), `backend/` (Laravel) y `frontend/` (Angular) en fases posteriores. Ahora solo existe `docs/`.
- **Alternativas:** dos repositorios separados (backend y frontend).
- **Consecuencias:** una sola historia de versiones y una bitácora única; coherente con los demás proyectos Shadow-Net. El detalle de carpetas está en [../development/development-workflow.md](../development/development-workflow.md).

### ADR-017. Podman en lugar de Docker (entorno sin demonio)

- **Estado:** Vigente. Reemplaza a ADR-011, cuyo texto original se conserva por historia.
- **Contexto:** el host (Debian 13) tiene Docker 26.1.5 instalado pero su demonio está apagado y no puede arrancarse sin privilegios interactivos, por lo que `docker compose up` no es verificable. Se necesita un motor de contenedores que funcione sin demonio en segundo plano.
- **Decisión:** usar **Podman 5.4.2** con **Buildah 1.39.3** como constructor e imágenes OCI, y orquestar el entorno local con **podman-compose 1.6.0** (instalado vía pipx) sobre el fichero **`compose.yaml`** (autodescubierto por podman-compose). Los comandos canónicos son `podman-compose up -d --build`, `podman-compose exec -T backend php artisan migrate --force` y `podman-compose down`, tras copiar `.env.example` a `.env`. La composición exige tres ajustes propios de Podman: (a) `image: docker.io/library/mysql:8.4` con nombre de registro completo porque Podman no resuelve nombres cortos en tiempo de ejecución; (b) límites de memoria con `mem_limit:` porque es la clave que podman-compose aplica directamente, en lugar de `deploy.resources.limits.memory`; (c) `COPY --from=docker.io/library/composer:2` con registro completo en el Dockerfile del backend porque Buildah no resuelve nombres cortos en `COPY --from`.
- **Alternativas:** arrancar el demonio de Docker con privilegios (descartado por no disponible); instalar todo en el host (pierde reproducibilidad).
- **Consecuencias:** entorno local reproducible sin demonio y verificado el 10/10/2026: imágenes de backend (848 MB) y frontend (667 MB) construidas, contenedores `shadow-net-stockflow_mysql_1` (sano), `_backend_1` (puerto 8000) y `_frontend_1` (puerto 4200) en ejecución, `GET /api/v1/health` con `{"status":"ok","service":"shadow-net-stockflow-api","version":"v1",...}`, `php artisan migrate --force` contra MySQL real con 10 tablas incluida `personal_access_tokens`, y frontend con HTTP 200 y título "Shadow-Net StockFlow". La integración continua no cambia: sigue con setup-php y setup-node y no depende de un demonio local.

## 4. Decisiones descartadas (resumen)

| Descartada | Motivo |
| --- | --- |
| Microservicios | Complejidad operativa sin necesidad; rompe las transacciones locales del inventario. |
| Kubernetes | Sobredimensionado para el MVP; prohibido por la regla de no introducir infraestructura por moda. |
| Redis | No se justifica con el volumen del MVP; se usa la cola de base de datos. |
| Event sourcing | Complejidad conceptual alta; el libro de movimientos ya da trazabilidad suficiente. |
| FIFO en el MVP | Requiere capas de costo; sin lotes no aporta valor proporcional. |
| `spatie/laravel-permission` | No cubre el alcance por almacén; se prioriza RBAC nativo. |
| SQLite para pruebas de concurrencia | No reproduce el bloqueo de filas de InnoDB. |
| Flotantes para dinero/cantidades | Riesgo de redondeo; se usan DECIMAL. |
