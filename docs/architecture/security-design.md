# Diseño de seguridad - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento define el modelo de seguridad del MVP: principio rector, modelo de amenazas, controles, correspondencia con OWASP Top 10, respuesta a incidentes y lista de verificación previa al lanzamiento. La API que estos controles protegen está en [api-design.md](./api-design.md), las reglas de inventario en [inventory-domain.md](./inventory-domain.md), el modelo físico en [database-design.md](./database-design.md) y las decisiones de plataforma en [architecture-decisions.md](./architecture-decisions.md).

## 1. Principio rector

**El frontend nunca es confiable para la autorización.** [DECISIÓN] Toda regla de negocio, validación y comprobación de permisos se aplica en el servidor, dentro de transacciones y con independencia del cliente. Los guardias de ruta, los menús ocultos y las validaciones de formulario en Angular solo mejoran la experiencia; nunca sustituyen a las Policies, los Gates ni al alcance por almacén del backend. Un atacante con un cliente HTTP arbitrario recibe exactamente las mismas denegaciones que la interfaz oficial.

## 2. Modelo de amenazas (estilo STRIDE)

Alcance: SPA Angular, API `/api/v1`, base MySQL 8.4 con InnoDB, sesiones Sanctum por cookie y tokens con habilidades para integraciones.

| Amenaza | Categoría STRIDE | Escenario | Control (sección 3) |
| --- | --- | --- | --- |
| Robo de sesión (cookie) | Suplantación | Un atacante obtiene la cookie de sesión y opera como la víctima. | C-06: cookie `HttpOnly`, `Secure`, `SameSite=Lax`; CSRF obligatorio; cierre de sesión que invalida; expiración y `last_login_at`. |
| Falsificación de solicitudes entre sitios (CSRF) | Suplantación | Un sitio malicioso dispara acciones con la sesión activa de la víctima. | C-06: token CSRF en la SPA; las integraciones usan cabecera de token, no cookie. |
| IDOR y evasión del alcance por almacén | Elevación de privilegio | Un usuario altera `warehouse_id` o el `uuid` de un documento para operar sobre un almacén ajeno. | C-02: Policies con alcance `warehouse_user` en cada lectura y escritura; denegación por defecto; pruebas de autorización. |
| Escalamiento por rol (permiso insuficiente) | Elevación de privilegio | Un operador o auditor invoca endpoints de confirmación, reversión o administración. | C-02: permiso RBAC exigido por endpoint (ver [api-design.md](./api-design.md)); Gates en rutas y Policies en casos de uso. |
| Asignación masiva | Manipulación | El cliente envía campos no previstos (`is_active`, `average_cost`, `status`, `confirmed_by`) para alterar el estado interno. | C-01: listas explícitas de campos asignables; los campos internos solo los escribe el servicio de dominio. |
| Condición de carrera sobre el stock | Denegación / Manipulación | Dos salidas concurrentes consumen el mismo saldo y dejan existencias negativas. | C-04: bloqueo `SELECT ... FOR UPDATE` en orden `(warehouse_id, product_id)`, validación dentro de la transacción y reintento acotado (ver [inventory-domain.md](./inventory-domain.md)). |
| Repetición por idempotencia (replay) | Repudiación / Manipulación | Un reintento de red o un atacante repite una creación o confirmación para duplicar efectos. | C-05: `Idempotency-Key` con restricción única y manejo de conflicto idempotente (ver [api-design.md](./api-design.md)). |
| Fuerza bruta sobre el login | Suplantación | Ataque automatizado que prueba credenciales contra `/auth/login`. | C-03: limitación de intentos, bloqueo temporal, mensajes genéricos y registro de intentos. |
| Filtración de secretos | Divulgación de información | Claves, credenciales o tokens en el código, en semillas, en logs o en respuestas. | C-07: secretos solo por variables de entorno; semillas sin credenciales fijas; auditoría sin secretos. |
| Registro de datos sensibles | Divulgación de información | Contraseñas, tokens o costos indebidos en logs o en `audit_logs`. | C-08: auditoría con responsable, acción, entidad y referencia, sin secretos; revisión de `old_values` y `new_values`. |
| Validación solo en el cliente | Manipulación | Peticiones artesanales con cantidades negativas, almacenes iguales en transferencias o ajustes sin motivo. | C-01: validación completa en el servidor; el cliente nunca decide. |
| Borrado o edición del historial | Repudiación | Intento de modificar movimientos o borrar documentos confirmados para ocultar un faltante. | C-09: libro append-only, documentos confirmados inmutables, reversión compensatoria y claves con `RESTRICT`. |
| Subida de archivo malicioso o sobredimensionado | Manipulación / Denegación | Un archivo de importación con tipo inesperado, tamaño excesivo o contenido hostil compromete el backend o la disponibilidad. | C-10: validación de tipo, tamaño, codificación y filas en el servidor; contenido nunca confiable; mismos permisos y reglas; auditoría con autor. |

## 3. Controles

### C-01. Validación en el servidor, siempre

- Cada endpoint valida tipos, rangos, enumeraciones y reglas de negocio antes de tocar la base (cantidades positivas, almacenes distintos en transferencias, motivo obligatorio en ajustes, productos y almacenes activos). [DECISIÓN]
- La validación vive en la capa de petición más el caso de uso; el controlador no contiene reglas de negocio (ver [inventory-domain.md](./inventory-domain.md)).
- Las restricciones `CHECK`, `UNIQUE` y de clave foránea de [database-design.md](./database-design.md) actúan como última red, nunca como única defensa.

### C-02. Autorización por defecto denegada: Policies, Gates y alcance por almacén

- Toda ruta protegida exige autenticación y el permiso RBAC documentado en [api-design.md](./api-design.md); sin ambos, se deniega (`401` o `403`). [DECISIÓN]
- Las Policies comprueban el permiso y, cuando hay un almacén implicado, la pertenencia a `warehouse_user`. El alcance se evalúa en lecturas (listas, detalle, kardex, reportes) y en escrituras (crear, confirmar, revertir, configurar min/max).
- El rol Auditor solo recibe permisos de lectura; nunca confirma, revierte ni administra.
- La desactivación de usuarios, almacenes, productos y categorías exige el permiso correspondiente y respeta la estrategia de preservación del historial de [database-design.md](./database-design.md).

### Catálogo de permisos del MVP (Fase 1)

El catálogo es la fuente única de los permisos RBAC del MVP y vive como enum respaldado por cadena en el backend (`App\Domain\Enums\PermissionSlug`); de él se derivan los Gates y los slugs que exige cada endpoint de [api-design.md](./api-design.md).

| Slug | Módulo | Qué autoriza |
| --- | --- | --- |
| users.view | users | Leer usuarios y su detalle. |
| users.create | users | Crear cuentas de usuario. |
| users.update | users | Editar, activar y desactivar cuentas. |
| roles.view | users | Leer roles y el catálogo de permisos. |
| roles.create | users | Crear roles. |
| roles.update | users | Editar roles y sus permisos. |
| warehouses.view | warehouses | Leer los almacenes dentro del alcance del usuario. |
| warehouses.view_all | warehouses | Conceder visibilidad global de almacenes. |
| warehouses.create | warehouses | Crear almacenes. |
| warehouses.update | warehouses | Editar, desactivar y administrar autorizados de un almacén. |
| catalog.create | catalog | Crear categorías, unidades y productos. |
| catalog.update | catalog | Editar y desactivar categorías, unidades y productos. |
| inventory.view | inventory | Leer documentos de inventario y kardex dentro del alcance. |
| inventory.create | inventory | Crear y descartar documentos de inventario. |
| inventory.confirm | inventory | Confirmar documentos de inventario. |
| inventory.reverse | inventory | Revertir documentos confirmados. |
| reports.view | reports | Consultar reportes dentro del alcance. |
| reports.export | reports | Exportar reportes a PDF o Excel. |
| audit.view | audit | Leer la bitácora de auditoría. |

`warehouses.view_all` es el único slug que concede visibilidad global de almacenes: excede el alcance `warehouse_user` y solo lo recibe el rol Administrador; no es un atajo que salte la autorización de otras acciones (por eso no existe `Gate::before`). La asignación de cada slug a cada rol (Administrador, Jefe de almacén, Operador, Auditor) se siembra en la Unidad 4; este catálogo solo define los slugs.

### Matriz de rol a permiso (Fase 1)

| Rol | Permisos |
| --- | --- |
| Administrador | Los 19 slugs del catálogo (incluye `warehouses.view_all`). |
| Jefe de almacén | `warehouses.view`, `warehouses.update`, `catalog.create`, `catalog.update`, `inventory.view`, `inventory.create`, `inventory.confirm`, `inventory.reverse`, `reports.view`, `reports.export`, `audit.view`. |
| Operador | `warehouses.view`, `inventory.view`, `inventory.create`, `inventory.confirm`. |
| Auditor | `warehouses.view`, `inventory.view`, `reports.view`, `audit.view` (solo lectura: nunca recibe `inventory.confirm`). |

El administrador de desarrollo se crea solo desde las variables de entorno `DEV_ADMIN_NAME`, `DEV_ADMIN_EMAIL` y `DEV_ADMIN_PASSWORD`, y nunca en producción.

### C-03. Limitación de peticiones

- El login tiene un límite estricto de intentos por dirección y cuenta, con bloqueo temporal y respuesta `429`. [DECISIÓN]
- Los endpoints de escritura de inventario y de exportación aplican límites para contener abusos y repeticiones agresivas; la respuesta incluye el código `LIMITE_EXCEDIDO`.
- Los mensajes de autenticación fallida son genéricos (no revelan si el correo existe).

### C-04. Integridad transaccional frente a concurrencia

- La confirmación es una única transacción que revalida el estado, bloquea filas en orden consistente, valida disponibilidad, aplica deltas, escribe movimientos, cambia el estado y audita, con reversión total ante cualquier fallo (ver [inventory-domain.md](./inventory-domain.md)).
- El orden global de bloqueos `(warehouse_id, product_id)` y el reintento acotado ante interbloqueos son obligatorios en todo caso de uso que toque existencias.

### C-05. Idempotencia como control de seguridad

- La clave de idempotencia es obligatoria en creación y confirmación de documentos; su unicidad en la base impide el doble efecto incluso con peticiones simultáneas.
- Una clave reutilizada con un cuerpo distinto no crea un segundo documento: se responde `409` con código `IDEMPOTENCIA_REPETIDA`.

### C-06. Sesiones, CSRF y contraseñas

- Cookies de sesión con marcas `HttpOnly`, `Secure` y `SameSite=Lax`; toda la comunicación viaja sobre TLS (sección 4). [DECISIÓN]
- Protección CSRF activa en la SPA; el logout invalida la sesión en el servidor.
- Contraseñas solo como hash (bcrypt o argon2); nunca en texto plano, nunca en semillas fijas, nunca en logs ni en auditoría.
- El usuario administrador inicial de desarrollo se crea desde variables de entorno, no desde valores fijos en el código (ver [database-design.md](./database-design.md)).

### C-07. Gestión de secretos

- Todo secreto (credenciales de base de datos, clave de aplicación, credenciales de semillas, tokens de integración) vive en variables de entorno o en el gestor de secretos del despliegue; jamás en el repositorio. [DECISIÓN]
- La base de datos no almacena tokens, secretos ni datos de tarjetas (ver [database-design.md](./database-design.md)).

### C-08. Auditoría sin secretos

- Cada operación relevante registra usuario, acción, entidad, referencia, fecha, dirección IP y agente de usuario; los campos `old_values` y `new_values` nunca contienen contraseñas, tokens ni secretos. [DECISIÓN]
- `audit_logs` es de solo escritura para la aplicación y de solo lectura para el rol Auditor; jamás se borra ni se edita.

### C-09. Inmutabilidad del historial

- Los movimientos son append-only (sin `updated_at`); los documentos confirmados solo transitan a `revertido` mediante reversión compensatoria; las claves foráneas usan `RESTRICT` para preservar la historia.

### C-10. Subidas de archivos (importación)

- Todo archivo de importación se valida en el servidor: tipo, tamaño, codificación y número de filas; el contenido nunca se confía. [DECISIÓN]
- El procesamiento ocurre en el backend con los mismos permisos y reglas que la captura manual; ningún archivo omite la autorización por rol ni el alcance por almacén.
- Los prefijos de fórmula (`=`, `+`, `-`, `@`) se neutralizan al exportar para prevenir inyección de fórmulas en hojas de cálculo.
- Cada importación queda auditada con su autor, fecha, archivo y número de filas aplicadas.

## 4. Transporte y plataforma

- TLS obligatorio en todo el tráfico HTTP; las cookies de sesión exigen canal seguro. [DECISIÓN]
- Cabeceras de seguridad estándar en las respuestas (tipo de contenido, encuadre y políticas básicas según el despliegue documentado en la fase de operación).
- Podman con podman-compose como entorno canónico reproducible y sin demonio; la configuración sensible viaja por entorno, no en imágenes ni en el repositorio (ADR-017 de [architecture-decisions.md](./architecture-decisions.md)).

## 5. Correspondencia con OWASP Top 10 (2021)

| Riesgo OWASP | Cómo se cubre en este diseño | Referencia |
| --- | --- | --- |
| A01 Control de acceso roto | Denegación por defecto; Policies + Gates + alcance `warehouse_user`; pruebas de autorización; IDOR tratado como amenaza central. | C-02, [api-design.md](./api-design.md) |
| A02 Fallos criptográficos | Hash de contraseñas; TLS obligatorio; sin secretos en base, logs ni repositorio. | C-06, C-07, sección 4 |
| A03 Inyección | Consultas parametrizadas del ORM; sin SQL concatenado; validación estricta de entradas y listas cerradas de `sort` y filtros. | C-01 |
| A04 Diseño inseguro | Núcleo transaccional con bloqueo pesimista, idempotencia y máquina de estados; modelo de amenazas previo a la implementación. | C-04, C-05, sección 2 |
| A05 Configuración incorrecta | Entorno canónico por contenedores; mensajes de error sin detalle interno; `ERROR_INTERNO` genérico hacia el cliente. | Sección 4 |
| A06 Componentes vulnerables | Versiones fijadas y verificadas en [architecture-decisions.md](./architecture-decisions.md); actualización consciente de Laravel, Sanctum, Angular y MySQL. | ADR, sección 7 |
| A07 Fallos de identificación y autenticación | Sanctum por cookie + CSRF para la SPA y habilidades para integraciones; límites contra fuerza bruta; sin credenciales fijas. | C-03, C-06 |
| A08 Fallos de integridad de datos y software | Libro append-only e inmutabilidad de confirmados; reversión compensatoria; redondeo y tipos `DECIMAL` documentados. | C-09, [inventory-domain.md](./inventory-domain.md) |
| A09 Fallos de registro y monitoreo | Auditoría completa sin secretos; registro de intentos de login; índices de consulta en `audit_logs`. | C-08 |
| A10 Falsificación de solicitudes del lado del servidor (SSRF) | El MVP no obtiene URLs arbitrarias del cliente; cualquier integración futura que lo haga exigirá lista de destinos permitidos y validación. | C-01 |

## 6. Respuesta a incidentes (bases)

1. **Detectar:** la auditoría y los logs de autenticación son la fuente primaria; todo `403` masivo, `429` o `409` anómalo se investiga.
2. **Contener:** desactivar la cuenta comprometida (`POST /users/{id}/deactivate`), invalidar sus sesiones y rotar los secretos afectados; si el alcance es un almacén, retirar temporalmente la pertenencia en `warehouse_user`.
3. **Preservar evidencia:** no borrar ni editar movimientos ni auditoría; exportar las entradas relevantes con su referencia antes de cualquier corrección.
4. **Corregir con trazabilidad:** los efectos indebidos se compensan con reversiones y ajustes motivados, nunca con edición directa de saldos o del kardex.
5. **Recuperar y aprender:** restablecer el acceso, verificar las invariantes I-01 a I-07 de [inventory-domain.md](./inventory-domain.md) y registrar la lección como punto de la lista de verificación.

## 7. Lista de verificación previa al lanzamiento

1. Todas las rutas protegidas exigen autenticación; ninguna responde datos sin sesión o token válido.
2. Cada endpoint aplica su permiso RBAC y el alcance `warehouse_user` donde corresponda, verificado con pruebas de autorización (incluido el rol Auditor en solo lectura).
3. La validación de negocio está duplicada en el servidor aunque exista en el frontend (cantidades, estados, motivos, almacenes activos).
4. La asignación masiva está cerrada: ningún campo interno (`status`, `average_cost`, `confirmed_by`, `balance_after`) es escribible desde la petición.
5. La confirmación de inventario es atómica, con bloqueo en orden consistente y reversión total, según [inventory-domain.md](./inventory-domain.md).
6. La clave de idempotencia es obligatoria en creación y confirmación, con unicidad en la base y manejo de conflicto probado.
7. Las pruebas de concurrencia C-01 a C-04 de [inventory-domain.md](./inventory-domain.md) pasan sobre MySQL real, no sobre SQLite en memoria.
8. El login tiene límite de intentos, bloqueo temporal y mensajes genéricos; el logout invalida la sesión.
9. Las cookies usan `HttpOnly`, `Secure` y `SameSite=Lax`; CSRF activo; TLS en todo el tráfico.
10. Ningún secreto vive en el repositorio, en semillas ni en imágenes; el administrador inicial sale de variables de entorno.
11. La auditoría registra responsable, acción, entidad y referencia sin contraseñas, tokens ni secretos, y no admite borrado.
12. Los errores hacia el cliente usan el formato uniforme sin detalle interno; los `500` no filtran trazas ni SQL.
13. Los reportes y exportaciones respetan el mismo alcance por almacén que las consultas en línea.
14. Las dependencias fijadas en [architecture-decisions.md](./architecture-decisions.md) están instaladas sin vulnerabilidades conocidas pendientes de parche.
