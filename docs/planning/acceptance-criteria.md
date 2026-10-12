# Criterios de aceptación - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

## 1. Propósito y lectura

Este archivo define los criterios verificables AC-001 a AC-070 del ERP Shadow-Net StockFlow en formato Dado (Given), Cuando (When) y Entonces (Then). El índice maestro vive en [requirements.md](./requirements.md), el detalle funcional en [functional-requirements.md](./functional-requirements.md) y los atributos de calidad en [non-functional-requirements.md](./non-functional-requirements.md). Las reglas de inventario y los escenarios de concurrencia C-01 a C-04 provienen de [../architecture/inventory-domain.md](../architecture/inventory-domain.md). El alcance general se fija en [project-charter.md](./project-charter.md).

Cada criterio se verifica por prueba o revisión. Un requisito se considera cumplido solo cuando todos sus criterios asociados pasan.

## 2. Autenticación y usuarios (AC-001 a AC-006)

| ID | Criterio |
| --- | --- |
| AC-001 | Dado un usuario registrado y activo, cuando inicia sesión con credenciales correctas, entonces obtiene una sesión válida y accede a su perfil. Cubre HU-002 y RF-001. |
| AC-002 | Dado un usuario autenticado, cuando cierra sesión, entonces sus credenciales quedan invalidadas y las operaciones posteriores exigen autenticarse de nuevo. Cubre RF-002. |
| AC-003 | Dado un Administrador autenticado, cuando crea una cuenta con rol y almacenes asignados, entonces el nuevo usuario opera solo dentro de ese rol y alcance. Cubre HU-001, HU-004 y RF-005. |
| AC-004 | Dada una cuenta desactivada, cuando intenta iniciar sesión u operar, entonces el servidor rechaza la petición con error de autorización. Cubre HU-003 y RF-007. |
| AC-005 | Dado un usuario sin permiso sobre una operación, cuando la solicita de forma directa al servidor, entonces el servidor la rechaza aunque la interfaz la oculte. Cubre RF-011. |
| AC-006 | Dado el rol Auditor, cuando intenta confirmar un documento, entonces el servidor rechaza la operación y permite solo la consulta. Cubre RF-115. |

## 3. Almacenes (AC-007 a AC-011)

| ID | Criterio |
| --- | --- |
| AC-007 | Dado un Administrador, cuando registra un almacén con código nuevo y responsable activo, entonces el almacén queda disponible para documentos. Cubre HU-005 y RF-016. |
| AC-008 | Dado un código de almacén existente, cuando se crea o renombra otro con el mismo código, entonces el sistema rechaza la operación con error uniforme. Cubre RF-021 y AC-058. |
| AC-009 | Dado un Jefe de almacén con dos almacenes asignados, cuando lista almacenes, entonces ve solo esos dos. Cubre HU-006, RF-024 y AC-055. |
| AC-010 | Dado un almacén desactivado con historial, cuando se consulta su kardex, entonces el historial aparece completo aunque el almacén rechaza documentos nuevos. Cubre HU-007, RF-020 y RF-025. |
| AC-011 | Dado un Administrador, cuando retira a un usuario de un almacén, entonces ese usuario pierde de inmediato la consulta y la operación sobre ese almacén. Cubre RF-023 y AC-055. |

## 4. Categorías y unidades (AC-012 a AC-015)

| ID | Criterio |
| --- | --- |
| AC-012 | Dada una categoría padre activa, cuando se crea una subcategoría bajo ella, entonces el árbol la muestra en su posición jerárquica. Cubre HU-008 y RF-027. |
| AC-013 | Dada una categoría inactiva, cuando se crea un producto con ella, entonces el sistema rechaza la operación con error claro. Cubre RF-035. |
| AC-014 | Dada una unidad que no permite decimales, cuando una línea trae cantidad fraccionaria, entonces la confirmación se rechaza sin efectos. Cubre HU-009 y RF-034. |
| AC-015 | Dada una unidad que permite decimales, cuando una línea trae hasta tres decimales, entonces la confirmación la acepta y conserva la exactitud. Cubre RF-034. |

## 5. Productos (AC-016 a AC-022)

| ID | Criterio |
| --- | --- |
| AC-016 | Dado un SKU nuevo, cuando se crea el producto con categoría y unidad activas, entonces la ficha queda disponible para documentos. Cubre HU-010 y RF-036. |
| AC-017 | Dado un SKU existente, cuando se crea o renombra otro producto con el mismo SKU, entonces el sistema rechaza la operación. Cubre RF-049 y AC-058. |
| AC-018 | Dado un producto inactivo con historial, cuando se intenta incluirlo en un documento nuevo, entonces la confirmación se rechaza y su kardex sigue consultable. Cubre HU-011 y RF-041. |
| AC-019 | Dado un producto con mínimo por almacén, cuando su existencia cae bajo ese mínimo, entonces el sistema lo señala en la ficha, el dashboard y el reporte de faltantes. Cubre HU-012, RF-080 y RF-083. |
| AC-020 | Dado un catálogo con productos, cuando se busca por SKU exacto, nombre parcial o código de barras, entonces el resultado filtra de forma paginada. Cubre HU-013, RF-045 y AC-056. |
| AC-021 | Dado un producto con existencias en dos almacenes autorizados, cuando se abre su ficha, entonces cada almacén muestra cantidad, costo promedio y mínimos. Cubre RF-050. |
| AC-022 | Dado un producto del MVP, cuando se consulta su método de costeo, entonces el valor expuesto es promedio ponderado. Cubre RF-048. |

## 6. Inventario (AC-023 a AC-041)

| ID | Criterio |
| --- | --- |
| AC-023 | Dada una entrada en borrador con líneas válidas, cuando se confirma, entonces las existencias aumentan, los movimientos quedan en el kardex y el promedio ponderado se recalcula. Cubre HU-014, RF-055 y RF-056. |
| AC-024 | Dada una salida cuya cantidad supera la existencia, cuando se confirma, entonces el servidor la rechaza con error de stock insuficiente y ningún saldo cambia. Cubre HU-015, RF-058 y la invariante I-01. |
| AC-025 | Dada una existencia de 10 unidades, cuando dos salidas concurrentes de 8 se confirman a la vez, entonces exactamente una se aplica, la otra falla y el saldo final es 2, nunca negativo. Escenario C-01 de concurrencia. Cubre HU-015 y RF-058. |
| AC-026 | Dada una transferencia entre almacenes distintos, cuando se confirma, entonces el origen descuenta y el destino acredita en la misma transacción con dos movimientos enlazados al documento. Cubre HU-017, RF-061 y la invariante I-03. |
| AC-027 | Dadas dos transferencias cruzadas A hacia B y B hacia A del mismo producto, cuando se confirman en paralelo, entonces ninguna deja saldo negativo y ningún interbloqueo queda sin resolver. Escenario C-02 de concurrencia. Cubre RF-061. |
| AC-028 | Dado un producto sin fila de existencias en un almacén, cuando dos entradas simultáneas lo acreditan, entonces resulta una sola fila con la suma de ambas cantidades. Escenario C-03 de concurrencia. Cubre RF-056. |
| AC-029 | Dado un documento en borrador, cuando dos confirmaciones simultáneas llegan al servidor, entonces solo una aplica efectos y la otra es rechazada por estado. Escenario C-04 de concurrencia. Cubre RF-055. |
| AC-030 | Dada una transferencia con origen y destino iguales, cuando se confirma, entonces el sistema la rechaza sin generar movimientos. Cubre RF-062. |
| AC-031 | Dado un ajuste sin motivo, cuando se confirma, entonces el sistema lo rechaza y exige motivo y justificación. Cubre HU-016 y RF-059. |
| AC-032 | Dado un ajuste negativo mayor que la existencia, cuando se confirma, entonces el sistema lo rechaza sin dejar saldo negativo. Cubre RF-060 y la invariante I-01. |
| AC-033 | Dada una línea con cantidad cero o negativa, cuando se confirma el documento, entonces el sistema rechaza la línea completa sin efectos parciales. Cubre RF-073. |
| AC-034 | Dado un documento confirmado, cuando se solicita su reversión con motivo, entonces se generan movimientos compensatorios enlazados, el original queda marcado como revertido y nada se borra. Cubre HU-020, RF-067 y RF-068. |
| AC-035 | Dada una transferencia cuyo destino ya consumió parte de lo recibido, cuando se pide su reversión completa, entonces el sistema la rechaza y exige compensar por partes. Cubre RF-078. |
| AC-036 | Dada una confirmación con clave de idempotencia, cuando se reintenta con la misma clave tras una falla de red, entonces el sistema no duplica documentos ni movimientos. Cubre HU-021, RF-069 y RF-070. |
| AC-037 | Dado un documento en borrador con al menos una línea válida, cuando se confirma, entonces cambia a confirmado con responsable y fecha; si está en otro estado, la confirmación se rechaza. Cubre HU-018 y RF-055. |
| AC-038 | Dado un documento confirmado, cuando se suma su kardex por producto y almacén, entonces el total iguala el saldo materializado. Invariante I-02. Cubre RF-077. |
| AC-039 | Dado un kardex por producto y almacén, cuando se recorre en orden cronológico, entonces cada movimiento muestra su saldo resultante coherente con el anterior. Cubre HU-019, RF-064 y RF-076. |
| AC-040 | Dado un documento con referencia externa y observaciones, cuando se consulta su detalle, entonces ambos campos aparecen junto a líneas, estado y responsables. Cubre RF-066, RF-071 y RF-072. |
| AC-041 | Dada una transferencia confirmada, cuando se revisa el costo del destino, entonces el promedio del destino incorpora el costo vigente del origen según la fórmula de promedio ponderado. Cubre RF-063. |

## 7. Dashboard (AC-042 a AC-046)

| ID | Criterio |
| --- | --- |
| AC-042 | Dado un usuario autenticado, cuando abre el dashboard, entonces ve el total de productos activos de su alcance. Cubre HU-022 y RF-081. |
| AC-043 | Dado un usuario con almacenes asignados, cuando abre el dashboard, entonces las existencias y la valorización se limitan a esos almacenes. Cubre RF-082, RF-086 y AC-055. |
| AC-044 | Dados productos bajo su mínimo, cuando se abre el dashboard, entonces la lista de faltantes los incluye con almacén y cantidad. Cubre RF-083. |
| AC-045 | Dado un documento recién confirmado, cuando se recarga el dashboard, entonces los movimientos recientes y la valorización reflejan la operación. Cubre HU-023, RF-084 y RF-085. |
| AC-046 | Dado el dashboard, cuando se inspecciona el origen de sus cifras, entonces cada indicador proviene de la API autorizada sin valores fijos. Cubre RF-087. |

## 8. Reportes (AC-047 a AC-052)

| ID | Criterio |
| --- | --- |
| AC-047 | Dados filtros por almacén autorizado y período, cuando se genera el reporte de existencias, entonces el detalle cuadra con sus subtotales y el total general. Cubre HU-024, RF-091 y RF-102. |
| AC-048 | Dados filtros por producto, almacén, tipo y período, cuando se genera el reporte de movimientos, entonces solo aparecen movimientos del alcance autorizado. Cubre HU-025, RF-092 y AC-055. |
| AC-049 | Dada la valorización del inventario, cuando se genera su reporte, entonces cada línea vale cantidad por costo promedio y el total es su suma. Cubre RF-093. |
| AC-050 | Dado un reporte visible en pantalla, cuando se exporta a PDF, entonces el archivo se genera en el servidor con los mismos datos y filtros. Cubre HU-026 y RF-094. |
| AC-051 | Dado un reporte visible en pantalla, cuando se exporta a Excel, entonces el archivo se genera en el servidor con los mismos datos y filtros. Cubre HU-026 y RF-095. |
| AC-052 | Dado un reporte voluminoso, cuando se solicita, entonces el sistema lo procesa en cola y avisa al usuario al terminar sin bloquear su sesión. Cubre RF-096. |

## 9. Auditoría (AC-053 a AC-056)

| ID | Criterio |
| --- | --- |
| AC-053 | Dado un documento confirmado o revertido, cuando se consulta la bitácora, entonces existe una entrada con responsable, acción, entidad, referencia y fecha. Cubre HU-027, RF-106 y RF-107. |
| AC-054 | Dada una entrada de auditoría, cuando se inspeccionan sus campos, entonces no contiene contraseñas, tokens ni secretos. Cubre RF-113. |
| AC-055 | Dado un usuario con alcance limitado a ciertos almacenes, cuando consulta bitácora, reportes o documentos de otro almacén, entonces el servidor niega el acceso. Cubre HU-006, RF-012 y la autorización por almacén. |
| AC-056 | Dada la bitácora con volumen operativo, cuando se consulta, entonces responde paginada con filtros por usuario, acción, entidad y fecha. Cubre HU-027, RF-110 y AC-057. |

Criterios AC-053 y AC-055 confirman la trazabilidad de reversiones: cada reversión enlaza el documento compensatorio con el original y conserva el motivo.

## 10. Calidad y operación (AC-057 a AC-060)

| ID | Criterio |
| --- | --- |
| AC-057 | Dado cualquier listado, cuando se solicita sin paginación completa, entonces responde paginado con página, tamaño y total explícitos. Cubre HU-029, RF-116 y el filtrado con paginación. |
| AC-058 | Dado un error de negocio como duplicado, faltante o validación, cuando ocurre, entonces la respuesta trae código, mensaje en español y detalle del campo o producto. Cubre HU-029, RF-119 y el formato uniforme de errores. |
| AC-059 | Dada una operación válida, cuando la interfaz anticipa una validación, entonces el servidor la vuelve a aplicar y decide el resultado final. Cubre RF-120. |
| AC-060 | Dado el repositorio, cuando se sigue su guía documentada, entonces el sistema completo se levanta con un solo comando sobre el entorno canónico. Cubre HU-030 y RF-123. |

## 11. Importación de datos (AC-061 a AC-070)

| ID | Criterio |
| --- | --- |
| AC-061 | Dada la plantilla de importación de catálogo, cuando se descarga, entonces trae las columnas esperadas y una fila de ejemplo. Cubre HU-031 y RF-126. |
| AC-062 | Dado un archivo de catálogo válido, cuando se ejecuta la validación previa, entonces no modifica datos y los conteos antes y después son idénticos. Cubre HU-032 y RF-127. |
| AC-063 | Dado un archivo con una fila inválida, cuando se intenta aplicar, entonces la aplicación completa se impide y el informe indica fila, campo y motivo. Cubre HU-034, RF-128 y RF-129. |
| AC-064 | Dado un archivo ya importado, cuando se reimporta el mismo archivo, entonces no se duplican productos por SKU. Cubre HU-033 y RF-130. |
| AC-065 | Dada una importación aplicada, cuando se consulta la bitácora, entonces existe una entrada con autor, archivo y filas aplicadas. Cubre HU-036 y RF-131. |
| AC-066 | Dado un archivo que excede el límite de tamaño o de filas, cuando se envía, entonces el servidor lo rechaza con error uniforme y sin efectos. Cubre RF-132 y AC-058. |
| AC-067 | Dada una carga inicial válida, cuando se aplica, entonces las existencias igualan la suma del kardex y el promedio ponderado se recalcula. Invariante I-02. Cubre HU-035 y RF-135. |
| AC-068 | Dada una fila con producto inexistente o almacén fuera del alcance del usuario, cuando se valida la carga inicial, entonces la fila se rechaza y no produce efectos. Cubre RF-134 y AC-055. |
| AC-069 | Dada una carga inicial aplicada, cuando se reenvía con la misma clave de idempotencia, entonces no se duplican existencias. Cubre RF-136. |
| AC-070 | Dado un usuario sin el permiso correspondiente, cuando intenta importar, entonces el servidor rechaza la operación con 403 SIN_PERMISO. Cubre RF-128 y AC-005. |
