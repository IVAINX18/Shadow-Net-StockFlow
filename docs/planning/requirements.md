# Requisitos del sistema - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

## 1. Introducción

Este documento es el índice maestro de requisitos del ERP Shadow-Net StockFlow. Fija qué necesita el sistema para cumplir el acta de constitución ([project-charter.md](./project-charter.md)) sin repetir el detalle que vive en otros archivos. El detalle funcional numerado está en [functional-requirements.md](./functional-requirements.md), los atributos de calidad en [non-functional-requirements.md](./non-functional-requirements.md) y los criterios verificables en [acceptance-criteria.md](./acceptance-criteria.md). Las reglas del núcleo de inventario se definen en [../architecture/inventory-domain.md](../architecture/inventory-domain.md), las decisiones técnicas en [../architecture/architecture-decisions.md](../architecture/architecture-decisions.md) y el modelo físico en [../architecture/database-design.md](../architecture/database-design.md) con su diagrama en [../architecture/er-diagram.md](../architecture/er-diagram.md).

El alcance del MVP cubre una sola organización con múltiples almacenes. El inventario es el núcleo: cada operación de negocio es un documento que agrupa líneas y que, al confirmarse, genera movimientos inmutables en el kardex y actualiza existencias por producto y almacén dentro de una sola transacción, con valorización por promedio ponderado móvil.

## 2. Metodología

La captura de requisitos siguió cuatro pasos. Primero se analizó el acta ([project-charter.md](./project-charter.md)), secciones 4 a 6, para extraer objetivos y alcance del MVP. Después se revisó el dominio de inventario ([../architecture/inventory-domain.md](../architecture/inventory-domain.md)) para fijar las reglas RN-INV sin alterarlas. Luego se derivó una historia de usuario por cada necesidad de rol identificada en el acta. Por último se asignó cada requisito funcional a un módulo y a una fase con la leyenda MoSCoW de la sección 3.

Toda afirmación que introduce una elección no derivada de los documentos canónicos lleva marcador ([DECISIÓN], [HIPÓTESIS] o [ESTIMACIÓN]) con su fuente. Lo que ya está decidido en el acta o en la arquitectura no lleva marcador, porque su fuente es el propio documento canónico enlazado.

## 3. Leyenda MoSCoW

| Prioridad | Significado en este proyecto | Fase de entrega |
| --- | --- | --- |
| Must | Requisito del MVP. Sin esto el sistema no opera ni demuestra integridad del inventario. | MVP |
| Should | Requisito de V1. Mejora el control y la operación, pero no bloquea el MVP. | V1 |
| Could | Requisito de V2. Evolución planificada cuando el núcleo esté estable. | V2 |
| Won't | Excluido del horizonte actual. Se registra para evitar deriva de alcance. | Futuro o nunca |

## 4. Módulos del sistema

| Módulo | Descripción | Rango RF |
| --- | --- | --- |
| Autenticación y usuarios | Sesión, perfil, cuentas, roles y permisos con alcance por almacén. | RF-001 a RF-015 |
| Almacenes | Catálogo de almacenes, código único y usuarios autorizados por almacén. | RF-016 a RF-025 |
| Categorías y unidades | Jerarquía de categorías y catálogo de unidades de medida. | RF-026 a RF-035 |
| Productos | Ficha de producto con SKU único, precios de referencia y configuración de stock por almacén. | RF-036 a RF-050 |
| Inventario | Documentos de entrada, salida, ajuste y transferencia, kardex, existencias y reversiones. | RF-051 a RF-080 |
| Dashboard | Indicadores operativos de existencias, faltantes, valorización y movimientos recientes. | RF-081 a RF-090 |
| Reportes | Existencias, movimientos y valorización con exportación a PDF y Excel. | RF-091 a RF-105 |
| Auditoría | Bitácora de quién hizo qué, cuándo y sobre qué entidad, sin secretos. | RF-106 a RF-115 |
| Calidad y operación | Paginación, filtros, errores uniformes, registros, pruebas y despliegue reproducible. | RF-116 a RF-125 |
| Importación de datos | Plantilla, validación previa (dry-run), importación de catálogo y carga inicial de existencias. | RF-126 a RF-137 |

El detalle de cada RF vive en [functional-requirements.md](./functional-requirements.md). Este archivo no lo duplica.

## 5. Reglas de negocio

### 5.1 Reglas de inventario (RN-INV-01 a RN-INV-12)

Las reglas siguientes reproducen fielmente la sección 2 de [../architecture/inventory-domain.md](../architecture/inventory-domain.md). Cualquier duda se resuelve a favor de ese documento.

| ID | Regla |
| --- | --- |
| RN-INV-01 | Toda cantidad de línea es estrictamente mayor que cero. La dirección la determina el tipo de documento, no el signo. |
| RN-INV-02 | Ninguna salida deja la existencia por debajo de cero. La validación ocurre dentro de la transacción contra el saldo bloqueado. |
| RN-INV-03 | Toda transferencia exige origen y destino distintos. |
| RN-INV-04 | Cada transferencia descuenta origen y acredita destino de forma atómica: ambos efectos o ninguno. |
| RN-INV-05 | Los movimientos y las existencias se escriben en la misma transacción que confirma el documento. |
| RN-INV-06 | Si cualquier parte de la confirmación falla, se revierte todo el conjunto sin efectos parciales. |
| RN-INV-07 | Los documentos confirmados son inmutables. Toda corrección exige una reversión con movimientos compensatorios. |
| RN-INV-08 | Todo ajuste requiere motivo (`reason_code`) y queda registrado en auditoría con su justificación. |
| RN-INV-09 | Ningún producto o almacén inactivo recibe movimientos nuevos. |
| RN-INV-10 | La cantidad de una salida, o de la pata de origen de una transferencia, no excede la disponibilidad actual. |
| RN-INV-11 | Solo un documento en estado `borrador` con al menos una línea válida puede confirmarse. |
| RN-INV-12 | La confirmación es idempotente: reintentar con la misma clave de idempotencia no genera un segundo efecto. |

### 5.2 Reglas generales complementarias (RN-GEN-001 a RN-GEN-014)

| ID | Regla |
| --- | --- |
| RN-GEN-001 | Toda autorización se verifica en el servidor. La interfaz solo refleja permisos, nunca los concede. |
| RN-GEN-002 | Un usuario solo consulta y opera almacenes vinculados a su perfil, salvo el Administrador. |
| RN-GEN-003 | El SKU de producto es único en el sistema. El código de almacén es único en el sistema. |
| RN-GEN-004 | El código de barras es opcional, pero cuando se informa debe ser único entre productos con valor no nulo. |
| RN-GEN-005 | Ninguna cuenta desactivada inicia sesión ni conserva sesiones válidas. |
| RN-GEN-006 | Ninguna entidad inactiva (usuario, almacén, producto, categoría) participa en documentos nuevos, aunque su historial se conserva. |
| RN-GEN-007 | Las cantidades usan DECIMAL con tres decimales y los costos promedio conservan seis decimales internos; los totales se redondean a dos decimales al presentar. |
| RN-GEN-008 | La valorización del MVP usa promedio ponderado móvil por producto y almacén; las salidas no alteran el costo promedio vigente. |
| RN-GEN-009 | Todo documento confirmado o revertido genera una entrada de auditoría con responsable, acción, entidad y referencia. |
| RN-GEN-010 | La auditoría nunca almacena contraseñas, tokens ni secretos. |
| RN-GEN-011 | Los listados responden paginados con filtros explícitos; ninguna consulta devuelve el catálogo completo sin paginar. |
| RN-GEN-012 | Todo error de negocio responde con formato uniforme: código, mensaje en español y detalle por campo o producto cuando aplica. |
| RN-GEN-013 | Las exportaciones a PDF y Excel se generan en el servidor con las mismas reglas de autorización que la consulta en pantalla. [DECISIÓN] Fuente: [../architecture/architecture-decisions.md](../architecture/architecture-decisions.md) ADR-015. |
| RN-GEN-014 | Las fechas se almacenan en UTC y se presentan en la zona America/Bogota. |

### 5.3 Reglas de importación (RN-IMP-01 a RN-IMP-05)

| ID | Regla |
| --- | --- |
| RN-IMP-01 | La importación es atómica: si una fila falla, no se aplica ninguna. |
| RN-IMP-02 | La importación reutiliza las mismas validaciones, permisos y reglas que la captura manual. |
| RN-IMP-03 | La carga inicial de existencias se aplica como documento de entrada confirmado, nunca escribiendo directo en las existencias. |
| RN-IMP-04 | Toda importación es idempotente por clave y queda auditada con su autor. |
| RN-IMP-05 | El archivo se valida en el servidor; su contenido nunca se confía. |

## 6. Historias de usuario

Formato: Como (rol) quiero (capacidad) para (beneficio).

### 6.1 Autenticación y usuarios

| ID | Historia |
| --- | --- |
| HU-001 | Como Administrador quiero crear cuentas con roles para que cada persona opere según su función. |
| HU-002 | Como usuario quiero iniciar y cerrar sesión y consultar mi perfil para trabajar con identidad verificada. |
| HU-003 | Como Administrador quiero activar y desactivar cuentas para impedir el acceso de personal retirado sin borrar su historial. |
| HU-004 | Como Administrador quiero asignar usuarios a almacenes específicos para limitar su alcance operativo. |

### 6.2 Almacenes

| ID | Historia |
| --- | --- |
| HU-005 | Como Administrador quiero registrar almacenes con código único, nombre, ubicación y responsable para identificar cada sede. |
| HU-006 | Como Jefe de almacén quiero consultar solo mis almacenes asignados para concentrarme en mi operación. |
| HU-007 | Como Administrador quiero desactivar un almacén sin borrar su kardex para cerrar sedes manteniendo la trazabilidad. |

### 6.3 Categorías y unidades

| ID | Historia |
| --- | --- |
| HU-008 | Como Jefe de almacén quiero organizar productos en categorías jerárquicas para clasificar el catálogo. |
| HU-009 | Como Operador quiero registrar cantidades en la unidad de medida del producto para reflejar unidades discretas o fraccionarias. |

### 6.4 Productos

| ID | Historia |
| --- | --- |
| HU-010 | Como Jefe de almacén quiero crear productos con SKU único y unidad de medida para identificar cada artículo. |
| HU-011 | Como Jefe de almacén quiero activar y desactivar productos para retirar artículos sin perder su historial. |
| HU-012 | Como Jefe de almacén quiero definir stock mínimo y máximo por almacén para detectar faltantes y excesos. |
| HU-013 | Como Operador quiero buscar productos por SKU, nombre o código de barras para registrar movimientos con rapidez. |

### 6.5 Inventario

| ID | Historia |
| --- | --- |
| HU-014 | Como Operador quiero registrar entradas con costo para aumentar existencias con su valorización. |
| HU-015 | Como Operador quiero registrar salidas validadas contra disponibilidad para no vender stock inexistente. |
| HU-016 | Como Jefe de almacén quiero registrar ajustes con motivo para corregir diferencias del conteo físico. |
| HU-017 | Como Jefe de almacén quiero transferir mercancía entre almacenes de forma atómica para mover stock sin pérdidas. |
| HU-018 | Como Operador quiero crear documentos en borrador y confirmarlos después para separar la captura de la aplicación contable. |
| HU-019 | Como Auditor quiero consultar el kardex por producto y almacén para reconstruir cada cambio con su saldo. |
| HU-020 | Como Jefe de almacén quiero revertir un documento confirmado con motivo para corregir errores sin borrar historia. |
| HU-021 | Como Operador quiero reintentar una confirmación sin duplicar efectos para operar con red inestable. |

### 6.6 Dashboard

| ID | Historia |
| --- | --- |
| HU-022 | Como Jefe de almacén quiero ver productos activos, existencias por almacén y faltantes para priorizar la reposición. |
| HU-023 | Como Administrador quiero ver movimientos recientes y valorización total para conocer el estado del inventario. |

### 6.7 Reportes

| ID | Historia |
| --- | --- |
| HU-024 | Como Auditor quiero obtener el reporte de existencias por almacén para conciliar saldos. |
| HU-025 | Como Auditor quiero obtener el historial de movimientos con filtros para rastrear operaciones por período. |
| HU-026 | Como Administrador quiero exportar reportes a PDF y Excel para compartirlos y archivarlos. |

### 6.8 Auditoría

| ID | Historia |
| --- | --- |
| HU-027 | Como Auditor quiero consultar la bitácora con responsable, acción, entidad y fecha para rendir cuentas. |
| HU-028 | Como Administrador quiero conservar la auditoría sin borrado para atender revisiones futuras. |

### 6.9 Calidad y operación

| ID | Historia |
| --- | --- |
| HU-029 | Como Operador quiero listados paginados con filtros y mensajes de error claros para trabajar sin confusión. |
| HU-030 | Como Administrador quiero registros de aplicación y despliegue reproducible para operar el sistema con confianza. |

### 6.10 Importación de datos

| ID | Historia |
| --- | --- |
| HU-031 | Como Jefe de almacén quiero descargar la plantilla de importación para preparar el catálogo con el formato esperado. |
| HU-032 | Como Jefe de almacén quiero validar el archivo antes de aplicarlo para detectar errores sin modificar datos. |
| HU-033 | Como Jefe de almacén quiero importar el catálogo desde Excel sin teclear cientos de productos para poner el sistema en marcha con rapidez. |
| HU-034 | Como Operador quiero ver por fila qué falló para corregir solo lo necesario. |
| HU-035 | Como Jefe de almacén quiero cargar las existencias iniciales por almacén para empezar a operar con saldos reales. |
| HU-036 | Como Auditor quiero consultar en la bitácora las importaciones realizadas para rendir cuentas de cada carga. |

## 7. Matriz de trazabilidad

Cada fila enlaza módulo con su rango de RF detallado en [functional-requirements.md](./functional-requirements.md), sus historias y sus criterios en [acceptance-criteria.md](./acceptance-criteria.md).

| Módulo | RF | HU | AC |
| --- | --- | --- | --- |
| Autenticación y usuarios | RF-001 a RF-015 | HU-001 a HU-004 | AC-001 a AC-006 |
| Almacenes | RF-016 a RF-025 | HU-005 a HU-007 | AC-007 a AC-011 |
| Categorías y unidades | RF-026 a RF-035 | HU-008 a HU-009 | AC-012 a AC-015 |
| Productos | RF-036 a RF-050 | HU-010 a HU-013 | AC-016 a AC-022 |
| Inventario | RF-051 a RF-080 | HU-014 a HU-021 | AC-023 a AC-041 |
| Dashboard | RF-081 a RF-090 | HU-022 a HU-023 | AC-042 a AC-046 |
| Reportes | RF-091 a RF-105 | HU-024 a HU-026 | AC-047 a AC-052 |
| Auditoría | RF-106 a RF-115 | HU-027 a HU-028 | AC-053 a AC-056 |
| Calidad y operación | RF-116 a RF-125 | HU-029 a HU-030 | AC-057 a AC-060 |
| Importación de datos | RF-126 a RF-137 | HU-031 a HU-036 | AC-061 a AC-070 |

Los atributos transversales de rendimiento, seguridad y disponibilidad se verifican con [non-functional-requirements.md](./non-functional-requirements.md), identificadores RNF-001 a RNF-030.
