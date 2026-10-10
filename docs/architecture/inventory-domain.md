# Diseño del dominio de inventario - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento es el núcleo del proyecto. Define las reglas de negocio del inventario, los estados y transiciones de los movimientos, los flujos de entrada, salida, ajuste y transferencia, la estrategia transaccional, la concurrencia, la idempotencia, la auditoría, la valorización y las invariantes del dominio con su plan de pruebas. El detalle de tablas y tipos vive en [database-design.md](./database-design.md) y el diagrama de entidades en [er-diagram.md](./er-diagram.md).

Regla permanente: **ninguna interfaz protege el inventario; solo el backend lo hace.** Todas las reglas aquí descritas se aplican en el servidor, dentro de transacciones, con independencia del cliente.

## 1. Conceptos fundamentales

### 1.1 Documento, línea y movimiento

El sistema separa la **intención de negocio** del **efecto sobre las existencias**:

- **Documento de inventario** (`inventory_documents`): la operación de negocio. Tiene un tipo (`entrada`, `salida`, `ajuste`, `transferencia`), un estado, un almacén de origen y, en transferencias, un almacén de destino. Agrupa una o varias líneas.
- **Línea de documento** (`inventory_document_lines`): el detalle. Cada línea referencia un producto y una cantidad, con su costo unitario.
- **Movimiento** (`inventory_movements`): el efecto atómico sobre el inventario de un producto en un almacén concreto. Es un registro **append-only**: una vez escrito no se modifica ni se elimina. La suma de movimientos por producto y almacén es la fuente de verdad del kardex.

Esta separación es la que permite que "el historial y las existencias se confirmen conjuntamente": al confirmar un documento se generan sus movimientos y se actualizan las existencias en la misma transacción.

### 1.2 Existencia materializada

Además del kardex, existe una tabla de saldo materializado (`inventories`) con una fila por producto y almacén. Existe por rendimiento: sumar el kardex completo en cada consulta es costoso. La fila de `inventories` **es la que se bloquea** para serializar operaciones concurrentes sobre el mismo producto y almacén. Su valor debe ser siempre igual a la suma de los movimientos; esa equivalencia es una invariante verificada por pruebas.

### 1.3 Tipos de documento

| Tipo | Efecto sobre existencias | Almacenes afectados |
| --- | --- | --- |
| `entrada` | Aumenta la existencia | Uno (destino) |
| `salida` | Disminuye la existencia | Uno (origen) |
| `ajuste` | Aumenta o disminuye, según el signo | Uno |
| `transferencia` | Disminuye en origen y aumenta en destino | Dos (origen y destino) |

## 2. Reglas de negocio del inventario

- **RN-INV-01.** Toda cantidad debe ser estrictamente mayor que cero (`quantity > 0`). La dirección la determina el tipo de documento, no el signo de la cantidad.
- **RN-INV-02.** Una salida no puede dejar la existencia por debajo de cero. Se valida contra el saldo bloqueado dentro de la transacción.
- **RN-INV-03.** Una transferencia exige almacén de origen y de destino distintos (`origin_warehouse_id != destination_warehouse_id`).
- **RN-INV-04.** Una transferencia descuenta del origen y acredita el destino de forma atómica: o suceden ambos efectos, o ninguno.
- **RN-INV-05.** Los movimientos y las existencias se escriben dentro de la misma transacción que confirma el documento.
- **RN-INV-06.** Si cualquier parte de la operación falla, se revierte todo el conjunto (ningún efecto parcial).
- **RN-INV-07.** Los documentos confirmados son inmutables. Corregirlos exige una **reversión** que genera los movimientos compensatorios.
- **RN-INV-08.** Un ajuste requiere un motivo (`reason_code`) y queda registrado en auditoría con su justificación.
- **RN-INV-09.** Un producto o un almacén inactivo no puede recibir movimientos nuevos.
- **RN-INV-10.** La cantidad de una salida o de la pata de origen de una transferencia no puede exceder la disponibilidad (`quantity` menos lo reservado si existiera reserva futura).
- **RN-INV-11.** Un documento solo puede confirmarse si está en estado `borrador` y tiene al menos una línea válida.
- **RN-INV-12.** La confirmación es idempotente: reintentar con la misma clave de idempotencia no genera un segundo efecto.

## 3. Estados y transiciones

### 3.1 Máquina de estados del documento

```
                 +-----------+
   crear         |           |  confirmar
  -------->      | BORRADOR  | ------------>  +-------------+
                 |           |                | CONFIRMADO  |
                 +-----+-----+                +------+------+
                       |                             |
                       | descartar                   | revertir
                       v                             v
                 +-----------+               +-------------+
                 | DESCARTADO|               |  REVERTIDO  |
                 +-----------+               +-------------+
```

| Estado | Significado | Transiciones permitidas |
| --- | --- | --- |
| `borrador` | Documento creado, aún no afecta existencias. Editable. | `confirmado`, `descartado` |
| `confirmado` | Documento aplicado. Generó movimientos y actualizó existencias. Inmutable. | `revertido` |
| `revertido` | Documento compensado por una reversión. Sus movimientos originales permanecen; se añadieron los compensatorios. | Ninguna |
| `descartado` | Borrador abandonado sin efecto. Estado final. | Ninguna |

Reglas de transición:

- Solo desde `borrador` se puede confirmar o descartar.
- Solo desde `confirmado` se puede revertir.
- `revertido` y `descartado` son terminales.
- En el MVP, la creación directa en estado `confirmado` (confirmación inmediata) está permitida como atajo, siempre que pase por el mismo servicio transaccional que la confirmación diferida. [DECISIÓN]

### 3.2 Dirección del movimiento

Cada movimiento registra una **dirección** derivada del tipo y del rol dentro del documento:

| Documento | Movimiento | Dirección | `quantity_delta` |
| --- | --- | --- | --- |
| `entrada` | 1 | `entrada` | `+cantidad` |
| `salida` | 1 | `salida` | `-cantidad` |
| `ajuste` | 1 | según signo | `+cantidad` o `-cantidad` |
| `transferencia` | 2 | origen `salida`, destino `entrada` | `-cantidad` y `+cantidad` |

## 4. Flujos de operación

Todos los flujos comparten el mismo armazón transaccional descrito en la sección 5. Los pasos funcionales son:

### 4.1 Entrada

1. El operador crea el documento de tipo `entrada` con almacén destino y al menos una línea (producto, cantidad, costo unitario).
2. Se valida: producto activo, almacén activo, cantidad positiva, acceso del usuario al almacén.
3. Al confirmar, por cada línea se bloquea (o crea) la fila de `inventories` y se **aumenta** la cantidad. Se actualiza el costo promedio ponderado con el costo de entrada (sección 8).
4. Se escriben los movimientos de dirección `entrada` con el saldo resultante.
5. Se registra la auditoría y se confirma la transacción.

### 4.2 Salida

1. El operador crea el documento de tipo `salida` con almacén origen y líneas.
2. Al confirmar, por cada línea se bloquea la fila de `inventories`.
3. Se comprueba disponibilidad: si `quantity < cantidad_solicitada`, se aborta la transacción y se devuelve un error de negocio (stock insuficiente) con el detalle del producto.
4. Si hay disponibilidad, se **disminuye** la cantidad y se escriben los movimientos de dirección `salida`.
5. Se registra la auditoría y se confirma la transacción.

### 4.3 Ajuste

1. El operador (o el jefe de almacén) crea el documento de tipo `ajuste` con un `reason_code` obligatorio (por ejemplo: `inventario_fisico`, `merma`, `robo`, `vencimiento`, `error_registro`, `otro`) y líneas con la cantidad y el signo del ajuste.
2. Al confirmar, se bloquea la fila, se valida que un ajuste negativo no deje saldo negativo, y se aplica el delta con su dirección.
3. El ajuste conserva la valorización: un ajuste de cantidad no altera el costo promedio unitario (solo redistribuye cantidades), salvo decisión explícita de un ajuste de valor. [DECISIÓN: el MVP solo ajusta cantidades, no valores; los ajustes de valor se posponen.]
4. Se registra la auditoría con el motivo y se confirma la transacción.

### 4.4 Transferencia

1. Se crea el documento de tipo `transferencia` con almacén origen, almacén destino (distintos) y líneas.
2. Al confirmar, la transacción trata **dos almacenes** para el mismo conjunto de productos. Para evitar interbloqueos, los bloqueos se adquieren en un orden global consistente (sección 6).
3. Por cada línea: se bloquea la fila de origen, se valida disponibilidad, se **disminuye** en origen y se **aumenta** en destino. Se generan **dos** movimientos por línea (salida en origen, entrada en destino), enlazados por el mismo documento.
4. El costo que viaja es el costo promedio vigente en el almacén de origen en el momento de la transferencia; en el destino se recalcula el promedio ponderado igual que en una entrada.
5. Se registra la auditoría y se confirma la transacción.

### 4.5 Reversión

1. Sobre un documento `confirmado` se solicita una reversión con un motivo.
2. Se crea un documento de reversión (o se marca el original como `revertido` con referencia al documento compensatorio) que genera movimientos de signo opuesto por cada movimiento original.
3. En una transferencia revertida, la pata de retorno respeta la disponibilidad del almacén que había recibido (puede no haber suficiente si ya se consumió; en ese caso la reversión completa se rechaza y se exige compensar por partes). 
4. Nunca se edita ni se borra el documento original: la corrección queda trazada.
5. Se registra la auditoría y se confirma la transacción.

## 5. Estrategia transaccional

### 5.1 Unidad atómica

La confirmación de un documento es **una única unidad de negocio atómica**. Se ejecuta dentro de una transacción de base de datos (`START TRANSACTION ... COMMIT/ROLLBACK`). Dentro de ella, en orden:

1. Se revalida el estado del documento (debe seguir en `borrador`) de forma bloqueante para evitar doble confirmación.
2. Se resuelven y **bloquean** las filas de `inventories` afectadas, en orden consistente.
3. Se validan las reglas de negocio (cantidades, disponibilidad, almacenes distintos, productos y almacenes activos).
4. Se aplican los deltas a `inventories`.
5. Se insertan los movimientos del kardex.
6. Se actualiza el estado del documento a `confirmado` con responsable y fecha.
7. Se inserta la entrada de auditoría.
8. Se confirma (COMMIT).

Si cualquier paso lanza una excepción o una regla de negocio falla, se ejecuta ROLLBACK y **ningún** efecto parcial queda en la base. Esto satisface RN-INV-05 y RN-INV-06.

### 5.2 Por qué la lógica no vive en el controlador

El controlador solo traduce HTTP a una llamada de caso de uso. La transacción y las reglas viven en un servicio de dominio (caso de uso) que no conoce HTTP ni JSON. Así las mismas reglas se aplican sin importar si la operación llega por la API, por un comando de consola o por un trabajo en cola.

## 6. Concurrencia y bloqueos

### 6.1 Bloqueo pesimista

Para serializar operaciones sobre el mismo producto y almacén se usa **bloqueo pesimista** con `SELECT ... FOR UPDATE` sobre las filas de `inventories`. Dos salidas simultáneas del mismo producto no pueden leer el saldo, decidir y descontar a la vez: la segunda espera a que la primera confirme o revierta. Esto evita la sobreventa y los saldos negativos (RN-INV-02, RN-INV-10).

Se descarta la actualización optimista con versión para el MVP porque, ante contención, obliga a reintentos en bucle en el cliente; el bloqueo pesimista es más simple de razonar y suficiente para la escala objetivo. [DECISIÓN]

### 6.2 Orden consistente de bloqueos

Los interbloqueos aparecen cuando dos transacciones toman los mismos bloqueos en orden distinto. Regla del proyecto: **toda transacción adquiere sus bloqueos en orden ascendente por `(warehouse_id, product_id)`.** En una transferencia, esto significa bloquear primero la fila de origen o destino cuyo par `(warehouse_id, product_id)` sea menor, sin importar cuál es origen y cuál destino, y luego la otra. Al fijar un orden global, dos transferencias cruzadas no pueden esperarse mutuamente en ciclo.

### 6.3 Filas que aún no existen

Si un producto no tiene fila en `inventories` para un almacén (nunca ha tenido existencia), el bloqueo no puede tomarse sobre una fila inexistente. Estrategia: la primera entrada crea la fila; para evitar que dos entradas simultáneas creen dos filas, existe una **restricción única** `(product_id, warehouse_id)` que provoca que una de las dos inserciones falle, y el servicio reintenta leyendo y bloqueando la fila ya creada. [DECISIÓN]

### 6.4 Reintento ante interbloqueo

Laravel permite reintentar la transacción ante un deadlock. El servicio transaccional se ejecuta con un número acotado de intentos; un interbloqueo es un evento transitorio que se resuelve repitiendo la transacción completa, nunca aplicando el delta parcialmente.

## 7. Idempotencia

### 7.1 Problema

Si el cliente envía una confirmación y la respuesta se pierde por red, el reintento no debe crear un segundo documento ni duplicar existencias.

### 7.2 Solución

Cada petición de creación/confirmación de documento lleva una **clave de idempotencia** (`idempotency_key`, cadena única generada por el cliente). La tabla `inventory_documents` tiene una restricción **única** sobre esa clave. Si llega una clave ya existe:

- Si el documento asociado está `confirmado`, el servidor devuelve el mismo resultado (o un conflicto idempotente) sin volver a aplicar efectos.
- Si la clave existe pero el documento quedó `descartado`, se informa y no se aplica nada.

La restricción única en la base es la garantía dura: aunque dos peticiones con la misma clave lleguen simultáneamente, solo una puede insertar la fila. [DECISIÓN]

## 8. Valorización del inventario

### 8.1 Comparación FIFO frente a promedio ponderado

| Criterio | FIFO (PEPS) | Promedio ponderado móvil |
| --- | --- | --- |
| Qué rastrea | Capas de costo por antigüedad; consume la más antigua primero. | Un costo unitario promedio por producto y almacén, recalculado en cada entrada. |
| Estructura de datos | Capas por capa (una fila o registro por cada entrada no consumida) + consumo parcial de capas. | Un único valor `average_cost` en la fila de `inventories`. |
| Complejidad de implementación | Alta: requiere abrir, consumir y cerrar capas, manejar consumos parciales y reversiones de capas. | Baja: una fórmula de actualización. |
| Precisión contable | Mayor, refleja el costo real por antigüedad. | Buena aproximación; suaviza el costo. |
| Reversiones | Complicadas: hay que reconstruir capas. | Simples: recomputar el promedio. |
| Caso ideal | Productos perecederos, lotes, alta rotación con costos volátiles, exigencia contable estricta. | Inventario general sin lotes, donde la exactitud por capa no es requisito. |

Fórmula del promedio ponderado móvil al recibir una entrada:

```
average_cost_nuevo = (quantity_actual * average_cost_actual + cantidad_entrada * costo_entrada)
                     / (quantity_actual + cantidad_entrada)
```

Las salidas y transferencias de origen no cambian `average_cost`; salen valoradas al promedio vigente. La valorización del inventario es `sum(quantity * average_cost)` por producto/almacén.

### 8.2 Recomendación

**Se adopta el promedio ponderado móvil para el MVP.** Razón: el dominio del MVP no maneja lotes ni vencimientos (excluidos a V1), y FIFO por capas multiplicaría la complejidad transaccional y de reversión justo en el núcleo que más necesita ser robusto y comprensible. El promedio ponderado es el estándar para inventario sin trazabilidad por lote y deja la puerta abierta a FIFO cuando se introduzcan lotes en V1/V2. [DECISIÓN]

### 8.3 Tipos numéricos y redondeo

- Cantidades: `DECIMAL(15,3)`, para soportar unidades discretas y fraccionarias (peso, volumen).
- Costo unitario promedio: `DECIMAL(18,6)`, para no perder precisión en la división.
- Montos monetarios (líneas, totales): `DECIMAL(18,2)`.
- Nunca se usan tipos de punto flotante (`FLOAT`/`DOUBLE`) para dinero ni cantidades.
- El redondeo a dos decimales se aplica solo al presentar montos y al calcular totales de línea/documento; el costo unitario conserva seis decimales para el cálculo interno.

## 9. Auditoría del inventario

Cada operación confirmada o revertida genera una entrada de auditoría con: usuario responsable, acción (`confirmar_documento`, `revertir_documento`, `crear_documento`, etc.), entidad afectada (`inventory_documents` y su identificador), referencia al documento, fecha y hora, y un resumen de los cambios relevantes. Nunca se guardan contraseñas, tokens ni secretos. La tabla y el formato exacto están en [database-design.md](./database-design.md) y las reglas de seguridad en [security-design.md](./security-design.md).

## 10. Invariantes del dominio

Las siguientes invariantes deben cumplirse siempre. Son la base de las pruebas (sección 11).

- **I-01 (no negativos).** Para todo `(product_id, warehouse_id)`, `inventories.quantity >= 0`.
- **I-02 (coherencia kardex-saldo).** Para todo `(product_id, warehouse_id)`, `inventories.quantity == sum(inventory_movements.quantity_delta)` de ese par.
- **I-03 (atomicidad de transferencia).** Tras una transferencia confirmada, la suma de deltas de origen es exactamente el negativo de la suma de deltas de destino para cada producto.
- **I-04 (inmutabilidad).** Ningún documento `confirmado` cambia de estado salvo a `revertido`, y ningún movimiento se modifica ni se elimina.
- **I-05 (trazabilidad).** Todo movimiento referencia un documento y un usuario responsables.
- **I-06 (idempotencia).** No existen dos documentos con la misma `idempotency_key`.
- **I-07 (valorización).** `average_cost > 0` cuando `quantity > 0`, y `average_cost` solo cambia en entradas (y en la pata de destino de una transferencia).

## 11. Pruebas del dominio

El plan completo está en [../development/testing-strategy.md](../development/testing-strategy.md). A continuación, las pruebas específicas del núcleo.

### 11.1 Pruebas unitarias

- Cálculo del promedio ponderado con entradas sucesivas (incluye cantidad previa cero).
- Transiciones de estado válidas e inválidas del documento.
- Derivación de dirección y signo por tipo de documento.

### 11.2 Pruebas de integración (transacción)

- Entrada aumenta existencia y escribe movimiento; kardex cuadra (I-02).
- Salida válida disminuye existencia y escribe movimiento.
- Salida mayor al stock es rechazada y no deja efecto (rollback completo).
- Transferencia descuenta origen y acredita destino en una sola transacción (I-03).
- Transferencia con mismo almacén de origen y destino es rechazada.
- Ajuste sin motivo es rechazado.
- Reversión compensa un documento confirmado sin borrarlo (I-04).
- Idempotencia: dos confirmaciones con la misma clave producen un solo efecto (I-06).

### 11.3 Pruebas de autorización

- Un usuario sin acceso al almacén no puede crear ni confirmar documentos de ese almacén.
- Un auditor no puede confirmar documentos (solo leer).

### 11.4 Pruebas de concurrencia

Estas pruebas son las que verifican que las garantías no son solo teóricas. Se ejecutan con transacciones paralelas reales:

- **C-01 (sobreventa).** Existencia de 10 unidades; dos salidas concurrentes de 8. Resultado esperado: exactamente una se confirma y la otra falla por stock insuficiente; la existencia final es 2 (nunca negativa).
- **C-02 (transferencias cruzadas).** Dos transferencias A->B y B->A del mismo producto en paralelo. Resultado esperado: ninguna deja saldo negativo y no se produce interbloqueo no resuelto (se reintenta si ocurre).
- **C-03 (primera entrada concurrente).** Dos entradas simultáneas para un producto sin fila previa en `inventories`. Resultado esperado: se obtiene una sola fila con la suma de ambas cantidades.
- **C-04 (doble confirmación).** Dos confirmaciones simultáneas del mismo documento. Resultado esperado: solo una aplica efectos; la otra es rechazada por estado.

Herramientas: pruebas de integración con transacciones reales de MySQL (no `sqlite` en memoria, porque el bloqueo de filas y el comportamiento de concurrencia difieren), lanzando procesos/promesas paralelas y verificando invariantes al final con la consulta de suma del kardex.

## 12. Límites del MVP en el inventario

- Una sola organización (sin aislamiento multi-inquilino).
- Sin reservas de stock ni pedidos pendientes (la disponibilidad es la existencia actual).
- Sin lotes, series ni vencimientos.
- Sin costeo FIFO (solo promedio ponderado).
- Ajustes de cantidad, no de valor.
- La reversión completa de una transferencia cuyo destino ya consumió parte se rechaza; se compensa por partes.
