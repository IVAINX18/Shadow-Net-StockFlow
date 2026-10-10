# Acta de constitución del proyecto - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento es la puerta de entrada al proyecto Shadow-Net StockFlow. Define el problema, los usuarios, los objetivos, el alcance del MVP, las exclusiones, los riesgos y los criterios de éxito. El detalle funcional vive en [requirements.md](./requirements.md), la descomposición por fases en [scope-and-milestones.md](./scope-and-milestones.md) y las decisiones técnicas en [../architecture/architecture-decisions.md](../architecture/architecture-decisions.md). Nada aquí duplica esos documentos: este acta fija el marco.

## 1. Resumen ejecutivo

Shadow-Net StockFlow es un sistema ERP web de gestión de inventarios y logística multi-almacén, desarrollado como producto profesional del ecosistema Shadow-Net Technologies y como proyecto central del portafolio de Ingeniería de Sistemas de su autor. Permite a una organización administrar productos, existencias, almacenes, movimientos de mercancía, transferencias, usuarios, permisos, reportes y auditoría desde una plataforma centralizada, con integridad transaccional garantizada por el backend.

El MVP se centra en una sola organización con múltiples almacenes. La arquitectura se diseña para evolucionar (multi-organización, lotes, órdenes de compra, contabilidad) sin introducir complejidad innecesaria ahora. El primer objetivo técnico no es "guardar stock en una columna", sino mantener coherentes existencias e historial mediante un libro de movimientos (kardex) transaccional, con concurrencia segura y correcciones trazables.

## 2. Problema que resuelve

La gestión de inventario en pequeñas y medianas operaciones suele resolverse con hojas de cálculo o anotaciones informales. Ese enfoque falla de cuatro formas concretas:

1. **Existencias y realidad divergen.** No existe un historial confiable de por qué el stock cambió, de modo que las diferencias entre lo físico y lo registrado no se pueden explicar ni auditar.
2. **No hay control por ubicación.** Cuando la mercancía vive en varios almacenes o bodegas, una sola cifra de stock no dice dónde está cada unidad ni permite transferir con trazabilidad.
3. **Riesgo de saldos negativos y sobreventa.** Sin bloqueo ni validación transaccional, dos operaciones simultáneas pueden consumir el mismo stock y dejar existencias negativas.
4. **Ausencia de responsabilidad y trazabilidad.** Nadie sabe quién movió qué, cuándo y por qué, lo que hace imposible rendir cuentas y atender una auditoría.

Shadow-Net StockFlow ataca las cuatro causas: un libro de movimientos inmutable como fuente de verdad, existencias por producto y almacén, operaciones de inventario atómicas con bloqueo de filas, y una bitácora de auditoría que registra responsable, acción, entidad y referencia sin almacenar secretos.

## 3. Usuarios objetivo

### 3.1 Usuarios de la organización

| Rol | Descripción | Necesidad principal |
| --- | --- | --- |
| Administrador | Configura la organización, usuarios, roles y almacenes. | Control total y trazabilidad. |
| Jefe de almacén | Responsable de uno o varios almacenes. | Gestionar existencias, entradas, salidas y transferencias de sus almacenes. |
| Operador de almacén | Ejecuta movimientos del día a día. | Registrar entradas, salidas y ajustes de forma rápida y segura. |
| Auditor | Revisa sin modificar. | Consultar kardex, valorización y bitácora en modo solo lectura. |

### 3.2 Perfil del desarrollador

El proyecto también se dirige a su autor como vehículo de aprendizaje. Por eso cada entregable incluye explicación del flujo de ejecución, de las decisiones técnicas y de los patrones empleados. El éxito no es solo que la aplicación funcione, sino que su autor pueda comprender y defender profesionalmente cada decisión. [DECISIÓN]

## 4. Objetivos

### 4.1 Objetivos de producto

- **OP-01.** Ofrecer existencias exactas por producto y almacén, respaldadas por un kardex completo.
- **OP-02.** Garantizar la integridad transaccional: ninguna salida puede consumir más de lo disponible ni dejar saldos negativos.
- **OP-03.** Permitir transferencias atómicas entre almacenes con trazabilidad de origen y destino.
- **OP-04.** Proveer reportes de existencias, movimientos y valorización del inventario, exportables.
- **OP-05.** Registrar auditoría de cada operación relevante con responsable, acción y referencia.
- **OP-06.** Controlar el acceso por rol y por almacén, validando siempre en el servidor.

### 4.2 Objetivos de aprendizaje

- **OA-01.** Comprender y aplicar arquitectura limpia, separación de responsabilidades y casos de uso.
- **OA-02.** Dominar transacciones, bloqueo de filas e idempotencia en MySQL mediante Laravel.
- **OA-03.** Diseñar una API REST versionada, segura y documentada con OpenAPI.
- **OA-04.** Construir un frontend Angular con componentes standalone, Signals y patrón de servicios.
- **OA-05.** Establecer pruebas automatizadas (unitarias, integración, autorización y concurrencia).

## 5. Alcance del MVP

El MVP entrega un ERP funcional de una organización con múltiples almacenes. Entra en el MVP:

- **Autenticación y usuarios:** inicio y cierre de sesión, perfil, activación y desactivación de cuentas, roles y permisos, acceso limitado por almacén, protección de endpoints en el servidor.
- **Almacenes:** crear, consultar, actualizar y desactivar; código único, nombre, dirección, ciudad y responsable; asociación de usuarios autorizados.
- **Productos y categorías:** SKU único, nombre, descripción, código de barras opcional, categorías jerárquicas, unidad de medida, estado activo o inactivo, stock mínimo y máximo por almacén, política de costos y precios.
- **Inventario:** existencias por producto y almacén, entradas, salidas, ajustes justificados, transferencias entre almacenes, kardex e historial, validación de cantidades y stock disponible, referencias y observaciones, reversiones auditables.
- **Dashboard:** total de productos activos, existencias por almacén, productos bajo stock mínimo, movimientos recientes e indicadores de valorización, alimentado por la API real.
- **Reportes:** existencias por almacén, historial de movimientos, valorización y exportación a PDF y Excel; procesamiento asíncrono cuando el volumen lo justifique.
- **Auditoría:** responsable, acción, fecha y hora, entidad afectada y referencia, sin almacenar contraseñas, tokens ni secretos.
- **Calidad y operación:** paginación, filtros y búsqueda, manejo uniforme de errores, logs, pruebas automatizadas, documentación y despliegue reproducible.

El detalle numerado y priorizado con MoSCoW está en [requirements.md](./requirements.md) y la secuencia de entrega en [scope-and-milestones.md](./scope-and-milestones.md).

## 6. Exclusiones

Quedan explícitamente fuera del MVP. Algunas se planifican para versiones posteriores; otras están descartadas por completo.

| Exclusión | Motivo | Fase |
| --- | --- | --- |
| Multi-organización (multi-tenant) | El MVP es una organización; el modelo se prepara pero no se implementa el aislamiento por inquilino. | V2 |
| Facturación fiscal y contabilidad integral | No pertenece al alcance inicial; requiere dominio fiscal ajeno al inventario. | Futuro |
| Lotes, números de serie y fechas de vencimiento | Requieren trazabilidad por lote; se posponen para no sobrecargar el núcleo. | V1 |
| Órdenes de compra y gestión de proveedores | Añaden un ciclo de aprobación y terceros; fuera del núcleo de inventario. | V1 |
| Costeo FIFO por capas | Requiere capas de costo; el MVP usa promedio ponderado (ver dominio de inventario). | V1/V2 |
| Lector de códigos de barras en interfaz | El campo de código de barras existe, pero la captura móvil no es del MVP. | V1 |
| Redis, microservicios, Kubernetes, event sourcing | No se incorporan por moda; se evalúa su necesidad y no se justifican en el MVP. | No |
| Aplicación móvil nativa | La web responsive cubre el MVP. | Futuro |
| MFA/SSO y autenticación social | La autenticación por credenciales con tokens cubre el MVP. | V1 |

## 7. Riesgos

| ID | Riesgo | Probabilidad | Impacto | Mitigación |
| --- | --- | --- | --- | --- |
| R-01 | Condiciones de carrera que produzcan saldos negativos o sobreventa. | Media | Alto | Bloqueo `SELECT ... FOR UPDATE` en orden consistente, validación de disponibilidad dentro de la transacción, pruebas de concurrencia. |
| R-02 | Interbloqueos (deadlocks) con bloqueos en orden inconsistente. | Media | Medio | Adquirir bloqueos siempre en el mismo orden (almacén, producto), reintento automático de la transacción. |
| R-03 | Movimientos duplicados por reintentos de red. | Media | Medio | Clave de idempotencia única por documento; el servidor rechaza duplicados. |
| R-04 | Deriva entre el kardex y las existencias. | Baja | Alto | Escritura de movimientos y actualización de existencias en la misma transacción; verificación por prueba que suma el kardex. |
| R-05 | Sobrediseño prematuro (complejidad innecesaria). | Media | Medio | Regla explícita: nada de Redis, Kubernetes, microservicios ni event sourcing en el MVP. |
| R-06 | Errores de valorización por redondeo o tipos incorrectos. | Media | Medio | DECIMAL (nunca flotantes) para cantidades, costos y totales; estrategia de redondeo documentada. |
| R-07 | Dependencia de herramientas locales ausentes (Composer, MySQL, Angular CLI). | Alta | Bajo | Docker Compose reproducible como entorno canónico; documentar la instalación local. |
| R-08 | Brechas de seguridad por confiar en validaciones del frontend. | Media | Alto | Toda regla de negocio y validación se aplica en el backend; el frontend solo mejora la experiencia. |
| R-09 | Sobreajuste al aprendizaje: código correcto pero no profesional. | Baja | Medio | Criterios de calidad y pruebas obligatorias por fase; revisión de arquitectura por el mentor. |
| R-10 | Recursos de hardware limitados (RAM ajustada) al construir frontend y contenedores. | Media | Bajo | Configurar límites en Docker; documentar requisitos; evitar imágenes pesadas. |

## 8. Criterios generales de éxito

- **CG-01.** Un usuario puede registrar una entrada, una salida y una transferencia, y el kardex y las existencias quedan coherentes.
- **CG-02.** Una salida mayor al stock disponible es rechazada por el backend con un error claro, incluso bajo operaciones concurrentes.
- **CG-03.** La suite de pruebas (unitaria, integración, autorización y concurrencia) pasa en integración continua.
- **CG-04.** El sistema es desplegable de forma reproducible con un solo comando documentado.
- **CG-05.** Un usuario sin permiso sobre un almacén no puede operar ni consultar datos de ese almacén, verificado en el servidor.
- **CG-06.** El autor puede explicar el flujo completo de una operación de inventario, de la interfaz a la base de datos y de vuelta.

## 9. Supuestos y decisiones tomadas por defecto

Las siguientes son decisiones que este acta fija por defecto, justificadas técnicamente, sujetas a confirmación de Master si cambian el alcance. [DECISIÓN]

- **SD-01.** Una sola organización en el MVP; la columna de organización se pospone pero el modelo no la bloquea.
- **SD-02.** Multi-almacén desde el inicio (es parte del núcleo, no una extensión).
- **SD-03.** Valorización por promedio ponderado móvil (comparado con FIFO en [../architecture/inventory-domain.md](../architecture/inventory-domain.md)).
- **SD-04.** Unidades de medida como catálogo con soporte de decimales opcional por unidad.
- **SD-05.** Roles y permisos con RBAC nativo (usuarios, roles, permisos y su tabla pivote) más alcance por almacén vía tabla pivote usuario-almacén.
- **SD-06.** Idioma de la aplicación y de la documentación: español. Código y nombres técnicos en inglés (convención Laravel/Angular).
- **SD-07.** Zona horaria de referencia: America/Bogota.

## 10. Nombres canónicos y glosario

**Nombres canónicos (no usar sinónimos en otras fases):** las tablas se nombran en snake_case plural; el detalle exacto está en [../architecture/database-design.md](../architecture/database-design.md). La base de la API es `/api/v1`. Los estados de documento de inventario son `borrador`, `confirmado` y `revertido`. Los tipos de documento de inventario son `entrada`, `salida`, `ajuste` y `transferencia`.

- **ERP:** sistema de planificación de recursos empresariales.
- **SKU:** identificador único de un producto (Stock Keeping Unit).
- **Kardex:** libro histórico de todos los movimientos de un producto, con saldo resultante.
- **Existencia (stock):** cantidad disponible de un producto en un almacén.
- **Movimiento:** efecto atómico de un documento confirmado sobre la existencia de un producto en un almacén.
- **Documento de inventario:** la operación de negocio (entrada, salida, ajuste o transferencia) que agrupa líneas y genera movimientos al confirmarse.
- **Transferencia:** documento que descuenta de un almacén de origen y acredita un almacén de destino de forma atómica.
- **Reversión:** documento que compensa un documento confirmado sin borrarlo ni modificarlo.
- **Idempotencia:** propiedad por la cual repetir la misma petición no genera un segundo efecto.
- **Valorización:** cálculo del valor monetario del inventario según su método de costeo.
- **RBAC:** control de acceso basado en roles.

## 11. Siguiente documento

Continuar con [requirements.md](./requirements.md) para los requisitos funcionales y no funcionales numerados, o con [scope-and-milestones.md](./scope-and-milestones.md) para la secuencia de fases.
