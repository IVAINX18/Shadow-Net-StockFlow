# Alcance y fases del proyecto - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento define el alcance por fases y la secuencia de hitos del ERP Shadow-Net StockFlow, un sistema de inventario multi-almacén para una sola organización. El marco general vive en [project-charter.md](./project-charter.md), el detalle funcional en [requirements.md](./requirements.md) y las decisiones técnicas en [../architecture/architecture-decisions.md](../architecture/architecture-decisions.md). Nada aquí duplica el texto detallado de los requisitos: este documento fija qué entra en cada fase, en qué orden se construye y cómo se verifica cada hito.

## 1. Alcance por fases (MoSCoW)

La tabla siguiente es coherente con el alcance del MVP y las exclusiones del acta de constitución. Fuente: docs/planning/project-charter.md §5 y §6.

| Área | MVP (Must) | V1 (Should) | V2 (Could) | Fuera del horizonte (Won't) |
| --- | --- | --- | --- | --- |
| Organización | Una sola organización, sin aislamiento multi-inquilino [DECISIÓN] Fuente: docs/planning/project-charter.md §9 (SD-01) | Preparación del modelo para multi-organización sin activarla | Multi-organización con aislamiento por inquilino | — |
| Autenticación y usuarios | Inicio y cierre de sesión, perfil, activación y desactivación de cuentas, RBAC nativo con alcance por almacén, validación en el servidor | Recuperación de contraseña, MFA y SSO, autenticación social | Políticas de acceso delegadas entre organizaciones | Confiar la autorización al frontend [DECISIÓN] Fuente: docs/architecture/inventory-domain.md §1 |
| Almacenes | Crear, consultar, actualizar y desactivar; código único, dirección, ciudad, responsable y usuarios autorizados | Jerarquías o zonas dentro del almacén [HIPÓTESIS] Fuente: necesidades de operación por confirmar con Master | Traslados programados entre organizaciones | — |
| Catálogo | SKU único, categorías jerárquicas, unidad de medida con decimales opcionales, estado activo o inactivo, stock mínimo y máximo por almacén, importación de catálogo (plantilla, validación previa y aplicación) | Lotes, números de serie y fechas de vencimiento; lector de códigos de barras en interfaz | Atributos extendidos de producto por organización | — |
| Inventario | Entradas, salidas, ajustes justificados, transferencias atómicas, kardex, reversiones auditables, sin saldos negativos, idempotencia, carga inicial de existencias como documento de entrada confirmado | Costeo FIFO por capas; ajustes de valor (el MVP solo ajusta cantidades) [DECISIÓN] Fuente: docs/architecture/inventory-domain.md §4.3 | Reservas de stock y pedidos pendientes | Eliminar o editar documentos confirmados o movimientos [DECISIÓN] Fuente: docs/architecture/inventory-domain.md §10 (I-04) |
| Compras y proveedores | — | Órdenes de compra y gestión de proveedores | Aprobaciones multi-nivel de compras | — |
| Dashboard | Totales de productos activos, existencias por almacén, productos bajo stock mínimo, movimientos recientes y valorización, alimentado por la API real | Alertas configurables y tableros por rol | Tableros comparativos entre organizaciones | — |
| Reportes | Existencias por almacén, historial de movimientos, valorización, exportación a PDF y Excel, con colas de base de datos cuando el volumen lo justifique | Reportes programados y plantillas personalizadas | Consolidación multi-organización | Generación de reportes en el frontend duplicando la lógica [DECISIÓN] Fuente: docs/architecture/architecture-decisions.md §ADR-015 |
| Auditoría | Responsable, acción, fecha y hora, entidad y referencia, sin secretos | Retención y purga configurables de la bitácora [HIPÓTESIS] Fuente: requisitos legales por confirmar con Master | Correlación de auditoría entre organizaciones | Borrado de evidencias de auditoría [DECISIÓN] Fuente: docs/architecture/database-design.md §4 |
| Calidad y operación | Paginación, filtros, errores uniformes, registros, pruebas automatizadas, documentación y despliegue reproducible con Podman (podman-compose) | Cobertura ampliada y pruebas de carga | Operación multi-región | Redis, microservicios, Kubernetes y event sourcing [DECISIÓN] Fuente: docs/planning/project-charter.md §6; facturación fiscal, contabilidad integral y aplicación móvil nativa quedan como trabajo futuro, no del MVP |
| Costeo | Promedio ponderado móvil | FIFO por capas, condicionado a la existencia de lotes [DECISIÓN] Fuente: docs/architecture/architecture-decisions.md §ADR-007 | Métodos mixtos por organización | Tipos flotantes para dinero o cantidades [DECISIÓN] Fuente: docs/architecture/inventory-domain.md §8.3 |

## 2. Fases e hitos

Las duraciones indicadas son [ESTIMACIÓN] Fuente: juicio del equipo de planificación, sujetas a la velocidad real del desarrollo. Ninguna fase se considera cerrada sin cumplir todos sus criterios de salida.

### Fase 0. Fundaciones (repo, Podman, migraciones base, esqueleto Laravel y Angular, CI)

Duración: [ESTIMACIÓN] 1 semana. Fuente: juicio del equipo de planificación.

- **Objetivo:** dejar un monorepo documentación-primero compilable, reproducible y verificado por integración continua antes de escribir lógica de negocio.
- **Entregables:** estructura `docs/`, `backend/` (Laravel 13 sobre PHP 8.4) y `frontend/` (Angular 22) según ADR-016 y ADR-002; `compose.yaml` con MySQL 8.4, backend y frontend según ADR-017; migraciones base de usuarios y catálogos según el orden de docs/architecture/database-design.md §7; especificación OpenAPI inicial según ADR-012; flujo de CI que ejecuta estilo (Pint), tipos y pruebas vacías en verde.
- **Criterios de salida:** un clon limpio levanta todo el entorno con un solo comando documentado; `podman-compose up -d --build` expone backend, frontend y MySQL sin errores; la CI pasa en la rama principal; la API responde su ruta de salud y la especificación OpenAPI es válida.
- **Dependencias:** ninguna, salvo la confirmación de la versión de Node para Angular 22 (ADR-006, pendiente de Master).
- **Riesgos:** herramientas locales ausentes (R-07) y RAM ajustada al construir contenedores y frontend (R-10). Fuente: docs/planning/project-charter.md §7.

### Fase 1. Autenticación, RBAC y almacenes

Duración: [ESTIMACIÓN] 2 semanas. Fuente: juicio del equipo de planificación.

- **Objetivo:** controlar quién entra al sistema y a qué almacenes puede acceder, con toda la autorización aplicada en el servidor.
- **Entregables:** endpoints de login, logout y perfil con Sanctum en modo estatal para la SPA (ADR-003); RBAC nativo con roles, permisos, Gates y Policies más el pivote usuario-almacén (ADR-004); CRUD de almacenes con código único y desactivación lógica; semillas de roles, permisos y usuario administrador de desarrollo.
- **Criterios de salida:** las pruebas de autorización verifican que un usuario sin acceso a un almacén recibe 403 en todos los endpoints de ese almacén; un auditor solo lee; la cobertura de este módulo es total en reglas de autorización; ningún secreto (contraseñas o tokens) aparece en respuestas ni registros.
- **Dependencias:** Fase 0 (esqueleto, base de datos, CI).
- **Riesgos:** brechas por validaciones solo en el frontend (R-08); complejidad creciente del RBAC nativo frente a un paquete externo (ADR-004). Fuente: docs/planning/project-charter.md §7 y docs/architecture/architecture-decisions.md §ADR-004.

### Fase 2. Catálogo (categorías, unidades y productos)

Duración: [ESTIMACIÓN] 3 semanas. Fuente: juicio del equipo de planificación.

- **Objetivo:** disponer del catálogo maestro que el inventario necesita antes de mover una sola unidad.
- **Entregables:** CRUD de categorías jerárquicas y de unidades de medida; CRUD de productos con SKU único, código de barras opcional, costos y precios en DECIMAL, método de costeo con valor `promedio_ponderado` por defecto; configuración de stock mínimo, máximo y punto de reorden por almacén (`product_warehouse`); paginación, filtros y búsqueda en todos los listados; importación de catálogo (plantilla, validación previa y aplicación).
- **Criterios de salida:** el SKU duplicado y el código de barras duplicado son rechazados por restricción única; un producto inactivo no admite movimientos nuevos (RN-INV-09); todas las cantidades y montos usan DECIMAL sin flotantes; las pruebas de integración del catálogo pasan en CI; una importación de 200 productos de prueba se valida, se aplica, queda auditada y es idempotente.
- **Dependencias:** Fase 1 (usuarios y autorización para proteger el catálogo).
- **Riesgos:** errores de valorización por tipos incorrectos (R-06); categorías con ciclos jerárquicos si no se valida el padre. Fuente: docs/planning/project-charter.md §7 y docs/architecture/database-design.md §5.2.

### Fase 3. Inventario (documentos, movimientos, kardex y reversos)

Duración: [ESTIMACIÓN] 4 semanas. Fuente: juicio del equipo de planificación. Es la fase más exigente del proyecto y no admite atajos.

- **Objetivo:** garantizar la integridad transaccional del inventario: transferencias atómicas, ningún saldo negativo, idempotencia y correcciones trazables mediante reversión.
- **Entregables:** servicio de dominio transaccional documento-línea-movimiento según ADR-008 con máquina de estados `borrador`, `confirmado`, `revertido` y `descartado`; bloqueo pesimista `SELECT ... FOR UPDATE` en orden `(warehouse_id, product_id)` con reintento ante interbloqueos (ADR-009); clave de idempotencia única; cálculo de promedio ponderado móvil; kardex consultable por producto y almacén; reversiones con movimientos compensatorios; validación de ajustes con motivo obligatorio; carga inicial de existencias como documento de entrada confirmado.
- **Criterios de salida:** las invariantes I-01 a I-07 se verifican en pruebas de integración, incluida la consulta de coherencia kardex-saldo sin filas divergentes; la prueba de sobreventa (existencia 10, dos salidas concurrentes de 8) deja existencia final 2 con exactamente una operación confirmada; las transferencias cruzadas paralelas no dejan saldos negativos ni interbloqueos sin resolver; la doble confirmación con la misma clave de idempotencia produce un solo efecto; una salida mayor al stock es rechazada con error de negocio claro; la carga inicial deja las existencias iguales a la suma del kardex.
- **Dependencias:** Fase 2 (productos y almacenes activos y bloqueables); motor MySQL 8.4 con InnoDB verificado en el entorno de pruebas, nunca SQLite para concurrencia (ADR-005).
- **Riesgos:** condiciones de carrera y saldos negativos (R-01); interbloqueos por orden inconsistente (R-02); duplicados por reintentos de red (R-03); deriva kardex-existencias (R-04). Fuente: docs/planning/project-charter.md §7 y docs/architecture/inventory-domain.md §11.4.

### Fase 4. Dashboard

Duración: [ESTIMACIÓN] 1 semana. Fuente: juicio del equipo de planificación.

- **Objetivo:** ofrecer una vista operativa del estado del inventario alimentada exclusivamente por la API real.
- **Entregables:** pantalla principal con total de productos activos, existencias por almacén, productos bajo stock mínimo, movimientos recientes e indicadores de valorización; componentes standalone con Signals y servicios Angular según ADR-014; guardias de ruta solo como ayuda de navegación.
- **Criterios de salida:** cada indicador del dashboard coincide con la respuesta directa de su endpoint con los mismos filtros; ningún indicador usa datos simulados; la pantalla carga con paginación y estados de error uniformes; las pruebas del frontend verifican los servicios contra contratos OpenAPI.
- **Dependencias:** Fases 1 a 3 (API de autenticación, catálogo e inventario funcionales).
- **Riesgos:** tentar la autorización en el frontend en lugar del backend (R-08); consultas pesadas del dashboard sin índices del kardex. Fuente: docs/architecture/database-design.md §5.3.

### Fase 5. Reportes y exportación

Duración: [ESTIMACIÓN] 2 semanas. Fuente: juicio del equipo de planificación.

- **Objetivo:** permitir la consulta y exportación de existencias, movimientos y valorización con las mismas reglas de autorización que la API.
- **Entregables:** reportes de existencias por almacén, historial de movimientos y valorización; exportación a PDF (dompdf) y Excel (maatwebsite/excel) en el backend según ADR-015; trabajos en colas de base de datos para exportaciones voluminosas según ADR-013.
- **Criterios de salida:** un reporte exportado contiene exactamente las filas visibles en su vista filtrada; una exportación solicitada por un usuario sin acceso al almacén es rechazada con 403; una exportación grande se procesa en cola sin bloquear la petición y notifica su resultado; los montos exportados usan dos decimales con el costo interno intacto.
- **Dependencias:** Fase 3 (kardex y valorización) y Fase 1 (autorización por almacén).
- **Riesgos:** sobrediseño con infraestructura de colas externa (R-05, Redis descartado en el MVP); redondeo inconsistente en totales exportados (R-06).

### Fase 6. Auditoría

Duración: [ESTIMACIÓN] 1 semana. Fuente: juicio del equipo de planificación.

- **Objetivo:** dejar constancia consultable de quién hizo qué, cuándo y sobre qué, sin almacenar secretos.
- **Entregables:** registro de auditoría en `audit_logs` para crear, confirmar, revertir y descartar documentos, más la gestión de catálogo y accesos; visor de solo lectura para el rol Auditor; garantías de inmutabilidad (sin borrado) y exclusión de contraseñas, tokens y secretos.
- **Criterios de salida:** cada documento confirmado o revertido tiene su entrada de auditoría con responsable, acción, entidad y referencia; una revisión automatizada confirma que ningún registro contiene campos secretos; un auditor puede consultar kardex y bitácora pero recibe 403 ante cualquier escritura.
- **Dependencias:** Fases 1, 2, 3 y 5 (todas las operaciones auditables ya existen).
- **Riesgos:** registros incompletos si la escritura de auditoría queda fuera de la transacción de negocio; filtración accidental de datos sensibles en `old_values` o `new_values`. Fuente: docs/architecture/inventory-domain.md §9.

### Fase 7. Calidad (cobertura, pruebas de extremo a extremo) y despliegue

Duración: [ESTIMACIÓN] 2 semanas. Fuente: juicio del equipo de planificación.

- **Objetivo:** cerrar el MVP con una suite verde en CI y un despliegue reproducible documentado.
- **Entregables:** pruebas unitarias, de integración, de autorización y de concurrencia con Pest según ADR-010 y la estrategia de pruebas; pruebas de extremo a extremo de los flujos entrada, salida, transferencia y reversión; informe de cobertura; guía de despliegue con un solo comando documentado; verificación de los criterios generales de éxito CG-01 a CG-06.
- **Criterios de salida:** toda la suite pasa en CI sobre MySQL real; la cobertura mínima acordada se cumple sin exclusiones silenciosas; un despliegue limpio desde cero reproduce el sistema con el comando documentado; el autor puede explicar el flujo completo de una operación de inventario de la interfaz a la base de datos y de vuelta (CG-06).
- **Dependencias:** Fases 0 a 6 (todo el producto construido).
- **Riesgos:** pruebas de concurrencia ejecutadas sobre SQLite que ocultan defectos reales (ADR-005); código correcto pero no defendible profesionalmente (R-09). Fuente: docs/planning/project-charter.md §7 y §8.

## 3. Secuencia recomendada y regla de avance

El orden recomendado es estrictamente secuencial de la Fase 0 a la Fase 7, con la Fase 3 como núcleo que no se comprime: el inventario solo se construye cuando el catálogo básico ya existe y está protegido por autorización. Cada fase comienza únicamente cuando la anterior cumplió sus criterios de salida. [DECISIÓN] Fuente: juicio del equipo de planificación sobre la dependencia técnica entre catálogo, movimientos y reportes.

La integridad del inventario es la parte más difícil del proyecto y por eso se programa después de los fundamentos del catálogo: mover existencias sin productos, almacenes y autorización estables multiplica los defectos transaccionales. Cualquier desvío de esta secuencia requiere aprobación de Master.

[DECISIÓN] La importación de catálogo y la carga inicial de existencias amplían el MVP (la duración total estimada crece) porque sin ellos la adopción exige teclear el catálogo completo a mano. Fuente: juicio del equipo de planificación.
