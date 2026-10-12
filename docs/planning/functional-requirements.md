# Requisitos funcionales - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

## 1. Propósito y lectura

Este archivo detalla cada requisito funcional RF-001 a RF-137 del ERP Shadow-Net StockFlow. El índice maestro con reglas, historias y trazabilidad vive en [requirements.md](./requirements.md). Los atributos de calidad están en [non-functional-requirements.md](./non-functional-requirements.md) y los criterios verificables en [acceptance-criteria.md](./acceptance-criteria.md). El dominio de inventario se define en [../architecture/inventory-domain.md](../architecture/inventory-domain.md), el modelo físico en [../architecture/database-design.md](../architecture/database-design.md) y el acta de alcance en [project-charter.md](./project-charter.md).

Cada fila incluye identificador, título, descripción, módulo, prioridad MoSCoW, regla relacionada y fase. La prioridad Must corresponde al MVP, Should a V1 y Could a V2.

## 2. Autenticación y usuarios (RF-001 a RF-015)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-001 | Inicio de sesión con credenciales | El sistema autentica al usuario con correo y contraseña y abre una sesión válida. | Autenticación y usuarios | Must | RN-GEN-001 | MVP |
| RF-002 | Cierre de sesión | El sistema cierra la sesión actual e invalida sus credenciales de acceso. | Autenticación y usuarios | Must | RN-GEN-001 | MVP |
| RF-003 | Consulta de perfil propio | Todo usuario autenticado consulta sus datos, roles y almacenes asignados. | Autenticación y usuarios | Must | RN-GEN-001 | MVP |
| RF-004 | Actualización de perfil propio | El usuario actualiza su nombre y datos básicos sin cambiar sus roles ni su alcance. | Autenticación y usuarios | Should | RN-GEN-001 | V1 |
| RF-005 | Creación de usuarios | El Administrador crea cuentas con nombre, correo único y rol inicial. | Autenticación y usuarios | Must | RN-GEN-001 | MVP |
| RF-006 | Edición de usuarios | El Administrador edita datos y roles de una cuenta existente. | Autenticación y usuarios | Must | RN-GEN-001 | MVP |
| RF-007 | Activación y desactivación de cuentas | El Administrador desactiva cuentas sin borrarlas; la cuenta desactivada pierde todo acceso. | Autenticación y usuarios | Must | RN-GEN-005 | MVP |
| RF-008 | Asignación de roles | El Administrador asigna uno o varios roles a cada usuario según su función. | Autenticación y usuarios | Must | RN-GEN-001 | MVP |
| RF-009 | Gestión de permisos por rol | El Administrador define qué permisos concede cada rol por módulo operativo. | Autenticación y usuarios | Must | RN-GEN-001 | MVP |
| RF-010 | Asignación de almacenes a usuarios | El Administrador vincula cada usuario operativo con los almacenes que puede consultar y operar. | Autenticación y usuarios | Must | RN-GEN-002 | MVP |
| RF-011 | Protección de operaciones en el servidor | Cada operación verifica rol y permiso en el servidor antes de ejecutarse. | Autenticación y usuarios | Must | RN-GEN-001 | MVP |
| RF-012 | Verificación de alcance por almacén | Cada consulta y documento verifica en el servidor que el usuario accede al almacén indicado. | Autenticación y usuarios | Must | RN-GEN-002 | MVP |
| RF-013 | Listado paginado de usuarios | El Administrador lista usuarios con paginación, filtro por estado y búsqueda por nombre o correo. | Autenticación y usuarios | Must | RN-GEN-011 | MVP |
| RF-014 | Cambio de contraseña propia | Todo usuario cambia su contraseña con verificación de la anterior. | Autenticación y usuarios | Should | RN-GEN-001 | V1 |
| RF-015 | Roles base precargados | El sistema incluye los roles Administrador, Jefe de almacén, Operador y Auditor con sus permisos iniciales. | Autenticación y usuarios | Must | RN-GEN-001 | MVP |

## 3. Almacenes (RF-016 a RF-025)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-016 | Creación de almacenes | El Administrador crea almacenes con código único, nombre, dirección, ciudad y responsable. | Almacenes | Must | RN-GEN-003 | MVP |
| RF-017 | Listado paginado de almacenes | Los usuarios listan almacenes con paginación y búsqueda por código o nombre. | Almacenes | Must | RN-GEN-011 | MVP |
| RF-018 | Detalle de almacén | El sistema muestra datos, responsable, usuarios autorizados y estado de cada almacén. | Almacenes | Must | RN-GEN-002 | MVP |
| RF-019 | Actualización de almacenes | El Administrador edita nombre, dirección, ciudad y responsable de un almacén activo. | Almacenes | Must | RN-GEN-001 | MVP |
| RF-020 | Desactivación de almacenes | El Administrador desactiva un almacén sin borrar su kardex ni su historial. | Almacenes | Must | RN-GEN-006 | MVP |
| RF-021 | Unicidad de código de almacén | El sistema rechaza crear o renombrar un almacén con un código ya existente. | Almacenes | Must | RN-GEN-003 | MVP |
| RF-022 | Asignación de responsable | El Administrador designa un usuario activo como responsable de cada almacén. | Almacenes | Must | RN-GEN-006 | MVP |
| RF-023 | Asociación de usuarios autorizados | El Administrador agrega o retira usuarios autorizados por almacén. | Almacenes | Must | RN-GEN-002 | MVP |
| RF-024 | Consulta de mis almacenes | Cada usuario operativo consulta solo los almacenes vinculados a su perfil. | Almacenes | Must | RN-GEN-002 | MVP |
| RF-025 | Bloqueo operativo de almacén inactivo | Ningún documento nuevo referencia un almacén inactivo, aunque su historial sigue consultable. | Almacenes | Must | RN-INV-09 | MVP |

## 4. Categorías y unidades (RF-026 a RF-035)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-026 | Creación de categorías | El Jefe de almacén crea categorías con nombre y jerarquía opcional de padre. | Categorías y unidades | Must | RN-GEN-006 | MVP |
| RF-027 | Subcategorías jerárquicas | El sistema sostiene categorías hijas bajo una categoría padre con profundidad acotada. | Categorías y unidades | Must | RN-GEN-006 | MVP |
| RF-028 | Listado jerárquico de categorías | Los usuarios consultan el árbol de categorías con paginación y filtro por estado. | Categorías y unidades | Must | RN-GEN-011 | MVP |
| RF-029 | Actualización de categorías | El Jefe de almacén edita nombre y posición jerárquica de una categoría. | Categorías y unidades | Must | RN-GEN-001 | MVP |
| RF-030 | Desactivación de categorías | El sistema desactiva categorías sin borrarlas y bloquea su uso en productos nuevos. | Categorías y unidades | Must | RN-GEN-006 | MVP |
| RF-031 | Creación de unidades de medida | El Administrador crea unidades con código único, nombre y permiso de decimales. | Categorías y unidades | Must | RN-GEN-003 | MVP |
| RF-032 | Catálogo base de unidades | El sistema precarga unidades de uso común como unidad, kilogramo y litro. [DECISIÓN] Fuente: acta, SD-04 y modelo de datos. | Categorías y unidades | Must | RN-GEN-007 | MVP |
| RF-033 | Listado de unidades de medida | Los usuarios consultan el catálogo de unidades con búsqueda por código o nombre. | Categorías y unidades | Must | RN-GEN-011 | MVP |
| RF-034 | Soporte de decimales por unidad | El sistema acepta cantidades fraccionarias solo en unidades que permiten decimales. | Categorías y unidades | Must | RN-GEN-007 | MVP |
| RF-035 | Bloqueo de categoría inactiva | Ningún producto nuevo usa una categoría inactiva, aunque los productos existentes la conservan. | Categorías y unidades | Must | RN-GEN-006 | MVP |

## 5. Productos (RF-036 a RF-050)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-036 | Creación de productos | El Jefe de almacén crea productos con SKU único, nombre, categoría y unidad de medida. | Productos | Must | RN-GEN-003 | MVP |
| RF-037 | Código de barras opcional | El producto registra un código de barras opcional, único cuando se informa. | Productos | Must | RN-GEN-004 | MVP |
| RF-038 | Descripción del producto | El producto incluye una descripción libre que explica su presentación y uso. | Productos | Must | RN-GEN-003 | MVP |
| RF-039 | Precios de referencia | El producto registra costo de referencia y precio de venta como valores informativos. | Productos | Must | RN-GEN-007 | MVP |
| RF-040 | Activación y desactivación | El Jefe de almacén desactiva productos sin borrar su historial ni su valorización. | Productos | Must | RN-GEN-006 | MVP |
| RF-041 | Bloqueo operativo de producto inactivo | Ningún documento nuevo incluye un producto inactivo. | Productos | Must | RN-INV-09 | MVP |
| RF-042 | Stock mínimo por almacén | El Jefe de almacén define el stock mínimo de cada producto por almacén. | Productos | Must | RN-GEN-003 | MVP |
| RF-043 | Stock máximo por almacén | El Jefe de almacén define el stock máximo opcional de cada producto por almacén. | Productos | Must | RN-GEN-003 | MVP |
| RF-044 | Punto de reorden por almacén | El Jefe de almacén define el punto de reorden opcional que anticipa la reposición. | Productos | Should | RN-GEN-003 | V1 |
| RF-045 | Búsqueda por SKU, nombre o barras | Los usuarios localizan productos por SKU exacto, nombre parcial o código de barras. | Productos | Must | RN-GEN-011 | MVP |
| RF-046 | Listado paginado de productos | Los usuarios listan productos con paginación y filtros por categoría, estado y almacén. | Productos | Must | RN-GEN-011 | MVP |
| RF-047 | Actualización de ficha de producto | El Jefe de almacén edita datos, precios y clasificación de un producto activo. | Productos | Must | RN-GEN-001 | MVP |
| RF-048 | Política de costeo visible | Cada producto expone su método de costeo, fijado en promedio ponderado para el MVP. | Productos | Must | RN-GEN-008 | MVP |
| RF-049 | Unicidad de SKU | El sistema rechaza crear o renombrar un producto con un SKU ya existente. | Productos | Must | RN-GEN-003 | MVP |
| RF-050 | Ficha con existencias por almacén | La ficha de producto resume existencias, costo promedio y mínimos por cada almacén autorizado. | Productos | Must | RN-INV-05 | MVP |

## 6. Inventario (RF-051 a RF-080)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-051 | Consulta de existencias por producto y almacén | Los usuarios consultan cantidad, costo promedio y fecha de último movimiento por par producto y almacén. | Inventario | Must | RN-INV-05 | MVP |
| RF-052 | Creación de documento en borrador | El Operador crea documentos de entrada, salida, ajuste o transferencia en estado borrador. | Inventario | Must | RN-INV-11 | MVP |
| RF-053 | Edición de borrador | El Operador agrega, edita o retira líneas de un documento en borrador. | Inventario | Must | RN-INV-11 | MVP |
| RF-054 | Descarte de borrador | El Operador descarta un borrador sin generar movimientos ni afectar existencias. | Inventario | Must | RN-INV-11 | MVP |
| RF-055 | Confirmación de documento | El usuario autorizado confirma un borrador y el sistema aplica movimientos y existencias en una transacción. | Inventario | Must | RN-INV-05 | MVP |
| RF-056 | Entrada con costo unitario | La entrada registra costo unitario por línea y recalcula el promedio ponderado del destino. | Inventario | Must | RN-GEN-008 | MVP |
| RF-057 | Salida con validación de disponibilidad | La salida verifica disponibilidad dentro de la transacción y descuenta solo si hay stock suficiente. | Inventario | Must | RN-INV-02 | MVP |
| RF-058 | Rechazo de sobreventa | Toda salida que exceda la disponibilidad se rechaza con error de negocio sin efectos parciales. | Inventario | Must | RN-INV-10 | MVP |
| RF-059 | Ajuste con motivo obligatorio | Todo ajuste exige motivo y justificación registrada en auditoría. | Inventario | Must | RN-INV-08 | MVP |
| RF-060 | Ajuste positivo y negativo | El ajuste aumenta o disminuye existencias según su signo, sin dejar saldos negativos. | Inventario | Must | RN-INV-02 | MVP |
| RF-061 | Transferencia atómica | La transferencia descuenta origen y acredita destino en una sola transacción o revierte todo. | Inventario | Must | RN-INV-04 | MVP |
| RF-062 | Validación de almacenes distintos | El sistema rechaza transferencias con origen y destino iguales. | Inventario | Must | RN-INV-03 | MVP |
| RF-063 | Costo que viaja en transferencia | La transferencia traslada el costo promedio vigente del origen y recalcula el promedio del destino. | Inventario | Must | RN-GEN-008 | MVP |
| RF-064 | Kardex por producto y almacén | Los usuarios consultan movimientos cronológicos con saldo resultante por producto y almacén. | Inventario | Must | RN-INV-05 | MVP |
| RF-065 | Historial de documentos | Los usuarios listan documentos con filtros por tipo, estado, almacén y período. | Inventario | Must | RN-GEN-011 | MVP |
| RF-066 | Detalle de documento con líneas | El sistema muestra cabecera, líneas, estado, responsable y referencias de cada documento. | Inventario | Must | RN-INV-11 | MVP |
| RF-067 | Reversión de documento confirmado | El usuario autorizado revierte un documento confirmado mediante movimientos compensatorios con motivo. | Inventario | Must | RN-INV-07 | MVP |
| RF-068 | Inmutabilidad del documento confirmado | Ninguna edición modifica un documento confirmado; solo la reversión lo compensa. | Inventario | Must | RN-INV-07 | MVP |
| RF-069 | Confirmación idempotente | Reintentar una confirmación con la misma clave de idempotencia no duplica efectos. | Inventario | Must | RN-INV-12 | MVP |
| RF-070 | Clave de idempotencia única | El sistema rechaza un segundo documento con una clave de idempotencia ya usada. | Inventario | Must | RN-INV-12 | MVP |
| RF-071 | Referencia externa del documento | Cada documento registra una referencia externa opcional como factura o guía. | Inventario | Must | RN-INV-11 | MVP |
| RF-072 | Observaciones del documento | Cada documento registra observaciones libres que explican la operación. | Inventario | Must | RN-INV-11 | MVP |
| RF-073 | Validación de cantidad positiva | Toda línea exige cantidad mayor que cero; la dirección la fija el tipo de documento. | Inventario | Must | RN-INV-01 | MVP |
| RF-074 | Validación de entidades activas | La confirmación exige producto y almacenes activos al momento de aplicarse. | Inventario | Must | RN-INV-09 | MVP |
| RF-075 | Confirmación inmediata | El sistema permite crear y confirmar en un solo paso pasando por el mismo servicio transaccional. | Inventario | Must | RN-INV-05 | MVP |
| RF-076 | Saldo resultante por movimiento | Cada movimiento almacena el saldo posterior para reconstruir el kardex sin recalcular. | Inventario | Must | RN-INV-05 | MVP |
| RF-077 | Coherencia kardex y existencias | Movimientos y saldo materializado se escriben juntos; su igualdad es invariante verificada. | Inventario | Must | RN-INV-05 | MVP |
| RF-078 | Reversión parcial de transferencia consumida | Si el destino ya consumió parte, la reversión completa se rechaza y se compensa por partes. | Inventario | Must | RN-INV-07 | MVP |
| RF-079 | Numeración legible de documento | Cada documento recibe un número consecutivo legible además de su identificador público. | Inventario | Should | RN-INV-11 | V1 |
| RF-080 | Alerta de stock bajo mínimo | El sistema señala productos cuya existencia cae bajo el mínimo configurado por almacén. | Inventario | Must | RN-GEN-003 | MVP |

## 7. Dashboard (RF-081 a RF-090)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-081 | Total de productos activos | El dashboard muestra el conteo de productos activos visibles para el usuario. | Dashboard | Must | RN-GEN-006 | MVP |
| RF-082 | Existencias por almacén | El dashboard resume cantidades por cada almacén autorizado del usuario. | Dashboard | Must | RN-GEN-002 | MVP |
| RF-083 | Productos bajo stock mínimo | El dashboard lista productos que cayeron bajo su mínimo por almacén. | Dashboard | Must | RN-GEN-003 | MVP |
| RF-084 | Movimientos recientes | El dashboard presenta los últimos movimientos con documento, producto y responsable. | Dashboard | Must | RN-INV-05 | MVP |
| RF-085 | Valorización total del inventario | El dashboard muestra el valor total como suma de cantidad por costo promedio. | Dashboard | Must | RN-GEN-008 | MVP |
| RF-086 | Valorización por almacén | El dashboard descompone el valor total por cada almacén autorizado. | Dashboard | Must | RN-GEN-008 | MVP |
| RF-087 | Datos servidos por la API real | Todos los indicadores del dashboard provienen de la API con autorización, sin cifras fijas. | Dashboard | Must | RN-GEN-001 | MVP |
| RF-088 | Filtro del dashboard por almacén | El usuario restringe los indicadores a un almacén autorizado. | Dashboard | Must | RN-GEN-002 | MVP |
| RF-089 | Accesos según rol | Cada rol ve solo los indicadores permitidos por sus permisos y su alcance. | Dashboard | Must | RN-GEN-002 | MVP |
| RF-090 | Actualización de indicadores | Los indicadores reflejan cada documento confirmado o revertido sin intervención manual. | Dashboard | Should | RN-INV-05 | V1 |

## 8. Reportes (RF-091 a RF-105)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-091 | Reporte de existencias por almacén | El sistema genera existencias con cantidad, costo promedio y valor por producto y almacén. | Reportes | Must | RN-INV-05 | MVP |
| RF-092 | Reporte de movimientos | El sistema genera el historial de movimientos con filtros por período, producto, almacén y tipo. | Reportes | Must | RN-INV-05 | MVP |
| RF-093 | Reporte de valorización | El sistema genera el valor del inventario agregado por producto, almacén y total. | Reportes | Must | RN-GEN-008 | MVP |
| RF-094 | Exportación a PDF | Los reportes se exportan a PDF generados en el servidor. | Reportes | Must | RN-GEN-013 | MVP |
| RF-095 | Exportación a Excel | Los reportes se exportan a Excel generados en el servidor. | Reportes | Must | RN-GEN-013 | MVP |
| RF-096 | Procesamiento asíncrono de reportes grandes | Los reportes voluminosos se procesan en cola y se avisa al usuario al terminar. | Reportes | Must | RN-GEN-013 | MVP |
| RF-097 | Autorización de reportes | Cada reporte y exportación respeta rol y alcance por almacén del solicitante. | Reportes | Must | RN-GEN-002 | MVP |
| RF-098 | Filtro por período | Los reportes aceptan rango de fechas inicial y final en la zona America/Bogota. | Reportes | Must | RN-GEN-014 | MVP |
| RF-099 | Filtro por producto y categoría | Los reportes aceptan filtros por producto individual o categoría completa. | Reportes | Must | RN-GEN-011 | MVP |
| RF-100 | Filtro por almacén y tipo | Los reportes aceptan filtros por almacén autorizado y tipo de documento. | Reportes | Must | RN-GEN-002 | MVP |
| RF-101 | Paginación de reportes en pantalla | Los reportes extensos se presentan paginados antes de exportarse. | Reportes | Must | RN-GEN-011 | MVP |
| RF-102 | Totales y subtotales | Cada reporte incluye totales por agrupación y un total general coherente con el detalle. | Reportes | Must | RN-GEN-008 | MVP |
| RF-103 | Trazabilidad de exportaciones | Cada exportación registra quién la generó, cuándo y con qué filtros. | Reportes | Should | RN-GEN-009 | V1 |
| RF-104 | Reporte de faltantes | El sistema genera productos bajo mínimo con su almacén y cantidad actual. | Reportes | Should | RN-GEN-003 | V1 |
| RF-105 | Reporte de ajustes por motivo | El sistema genera ajustes agrupados por motivo con su justificación. | Reportes | Should | RN-INV-08 | V1 |

## 9. Auditoría (RF-106 a RF-115)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-106 | Registro de confirmaciones | Cada confirmación de documento genera una entrada con responsable, fecha y referencia. | Auditoría | Must | RN-GEN-009 | MVP |
| RF-107 | Registro de reversiones | Cada reversión genera una entrada enlazada con el documento original y su motivo. | Auditoría | Must | RN-INV-07 | MVP |
| RF-108 | Registro de gestión de catálogos | Cada creación, edición o desactivación de usuarios, almacenes, productos y categorías genera una entrada. | Auditoría | Must | RN-GEN-009 | MVP |
| RF-109 | Detalle de cambios | Cada entrada conserva valores anteriores y nuevos de los campos relevantes. | Auditoría | Must | RN-GEN-009 | MVP |
| RF-110 | Consulta paginada de bitácora | Los auditores consultan la bitácora paginada con filtros por usuario, acción, entidad y fecha. | Auditoría | Must | RN-GEN-011 | MVP |
| RF-111 | Bitácora de solo lectura | Ningún rol edita ni elimina entradas de auditoría por ninguna vía. | Auditoría | Must | RN-GEN-009 | MVP |
| RF-112 | Conservación sin borrado | La bitácora se conserva de forma permanente junto con el kardex. | Auditoría | Must | RN-GEN-009 | MVP |
| RF-113 | Exclusión de secretos | Las entradas nunca incluyen contraseñas, tokens ni secretos. | Auditoría | Must | RN-GEN-010 | MVP |
| RF-114 | Registro de red y cliente | Cada entrada conserva dirección de red y agente de cliente cuando están disponibles. | Auditoría | Should | RN-GEN-009 | V1 |
| RF-115 | Acceso de auditoría para el Auditor | El rol Auditor consulta kardex, reportes y bitácora sin permiso de modificación. | Auditoría | Must | RN-GEN-002 | MVP |

## 10. Calidad y operación (RF-116 a RF-125)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-116 | Paginación uniforme | Todos los listados responden paginados con página, tamaño y total explícitos. | Calidad y operación | Must | RN-GEN-011 | MVP |
| RF-117 | Filtros explícitos por listado | Cada listado documenta los filtros que acepta y rechaza parámetros desconocidos con error claro. | Calidad y operación | Must | RN-GEN-011 | MVP |
| RF-118 | Búsqueda textual | Los catálogos ofrecen búsqueda parcial por los campos definidos en cada módulo. | Calidad y operación | Must | RN-GEN-011 | MVP |
| RF-119 | Formato uniforme de errores | Todo error responde con código, mensaje en español y detalle por campo o producto. | Calidad y operación | Must | RN-GEN-012 | MVP |
| RF-120 | Validación en el servidor | Toda regla de negocio se aplica en el servidor aunque la interfaz también la anticipe. | Calidad y operación | Must | RN-GEN-001 | MVP |
| RF-121 | Registros de aplicación | El sistema registra eventos operativos y errores con nivel, contexto y referencia temporal. | Calidad y operación | Must | RN-GEN-009 | MVP |
| RF-122 | Documentación viva de la API | La API expone su contrato OpenAPI versionado junto con la aplicación. | Calidad y operación | Must | RN-GEN-001 | MVP |
| RF-123 | Despliegue reproducible | El sistema se despliega con un comando documentado sobre el entorno canónico de contenedores. | Calidad y operación | Must | RN-GEN-001 | MVP |
| RF-124 | Datos iniciales del sistema | La instalación precarga roles, permisos, unidades base y un administrador definido por variables de entorno. | Calidad y operación | Must | RN-GEN-001 | MVP |
| RF-125 | Zona horaria de presentación | Las fechas se almacenan en UTC y toda presentación usa America/Bogota. | Calidad y operación | Must | RN-GEN-014 | MVP |

## 11. Importación de datos (RF-126 a RF-137)

| ID | Título | Descripción | Módulo | Prioridad | RN | Fase |
| --- | --- | --- | --- | --- | --- | --- |
| RF-126 | Plantilla de importación de catálogo | El sistema ofrece la descarga de la plantilla de catálogo en XLSX y CSV con las columnas esperadas y una fila de ejemplo. | Importación de datos | Must | RN-IMP-02 | MVP |
| RF-127 | Validación previa del catálogo | El sistema valida el archivo de catálogo en modo de prueba (dry-run) y devuelve un informe por fila sin modificar ningún dato. | Importación de datos | Must | RN-IMP-02 | MVP |
| RF-128 | Confirmación de la importación de catálogo | El sistema crea o actualiza categorías, unidades y productos en una sola transacción a partir del archivo validado. | Importación de datos | Must | RN-IMP-01 | MVP |
| RF-129 | Informe de errores por fila | La validación informa por cada fila el campo y el motivo del fallo, sin abortar el análisis de las demás filas. | Importación de datos | Must | RN-IMP-02 | MVP |
| RF-130 | Idempotencia por SKU | La importación crea o actualiza por SKU; reimportar el mismo archivo no duplica productos. | Importación de datos | Must | RN-IMP-04 | MVP |
| RF-131 | Trazabilidad de la importación | Cada importación de catálogo queda registrada en la auditoría con autor, fecha, archivo y número de filas aplicadas. | Importación de datos | Must | RN-IMP-04 | MVP |
| RF-132 | Límites y validación del archivo | El sistema valida en el servidor el tamaño, el número de filas, el tipo y la codificación del archivo, y rechaza el que exceda los límites con un error claro. | Importación de datos | Must | RN-IMP-05 | MVP |
| RF-133 | Plantilla de carga inicial de existencias | El sistema ofrece la descarga de la plantilla de carga inicial con SKU, almacén, cantidad y costo. | Importación de datos | Must | RN-IMP-02 | MVP |
| RF-134 | Validación previa de la carga inicial | El sistema valida la carga inicial en modo de prueba: rechaza producto inexistente, almacén fuera de alcance, cantidad inválida y decimales en unidades que no los permiten. | Importación de datos | Must | RN-IMP-02 | MVP |
| RF-135 | Carga inicial como documento de entrada confirmado | La carga inicial se aplica como un documento de entrada confirmado; nunca escribe directo en las existencias y el kardex cuadra. | Importación de datos | Must | RN-IMP-03 | MVP |
| RF-136 | Idempotencia de la carga inicial | Reenviar la carga inicial con la misma clave no duplica existencias. | Importación de datos | Must | RN-IMP-04 | MVP |
| RF-137 | Procesamiento en cola para archivos grandes | Los archivos que superan el umbral definido se procesan en cola y el usuario consulta el resultado sin bloquear su sesión. | Importación de datos | Should | RN-GEN-013 | V1 |

La importación reutiliza siempre la misma validación y autorización que la captura manual (RN-IMP-02) y nunca relaja una regla de negocio.
