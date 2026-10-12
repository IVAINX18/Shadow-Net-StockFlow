# Especificación maestra de Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento es la puerta de entrada a toda la especificación de Shadow-Net StockFlow, un sistema ERP web de gestión de inventarios y logística multi-almacén. Cada sección resume en pocos párrafos el contenido del documento fuente y enlaza a él para el detalle completo. Nada aquí duplica los documentos individuales: este maestro orienta y los documentos desarrollan. El lector sin contexto previo entiende el producto, las decisiones y los próximos pasos con solo leer estas páginas, y profundiza siguiendo los enlaces. Los enlaces son relativos a este archivo. Las hipótesis y estimaciones se marcan con `[HIPÓTESIS]` o `[ESTIMACIÓN]`; las decisiones con `[DECISIÓN]`.

## 1. Visión

Queremos que una organización sepa, en todo momento y sin discusión, cuánto tiene, dónde lo tiene y por qué cambió. Hoy el inventario de muchas operaciones vive en hojas de cálculo donde las existencias y la realidad divergen, no hay control por ubicación, los saldos pueden volverse negativos y nadie puede rendir cuentas de quién movió qué. StockFlow centraliza productos, existencias, almacenes, movimientos, transferencias, usuarios, permisos, reportes y auditoría en una sola plataforma.

El principio que no cambia con las fases es la integridad: el sistema no actualiza una columna de stock a ciegas, sino que mantiene un libro de movimientos inmutable como fuente de verdad, con operaciones atómicas, bloqueos que impiden la sobreventa y correcciones que dejan rastro. La visión de largo plazo es un ERP de inventario completo (lotes, órdenes de compra, multi-organización) construido sobre esa base sólida, sin pagar hoy la complejidad que solo el futuro exige.

Detalle completo en el [acta de constitución](./planning/project-charter.md).

## 2. Problema

El problema se descompone en cuatro causas concretas: las existencias y el registro divergen porque no hay historial confiable; no existe control por ubicación cuando la mercancía vive en varios almacenes; dos operaciones simultáneas pueden consumir el mismo stock y dejar saldos negativos; y no hay trazabilidad de responsable, acción y motivo, lo que impide auditar. StockFlow ataca las cuatro con un kardex, existencias por producto y almacén, transacciones con bloqueo de filas e idempotencia, y una bitácora de auditoría sin secretos.

Detalle completo en el [acta de constitución](./planning/project-charter.md) (§2).

## 3. Solución

StockFlow es una aplicación web con una API versionada (`/api/v1`) sobre Laravel 13 y un frontend Angular 22. El usuario administra almacenes, categorías, unidades y productos; registra entradas, salidas, ajustes y transferencias; consulta el kardex y la valorización; y todo queda auditado. Cada operación de inventario se modela como un documento con líneas que, al confirmarse, genera movimientos dentro de una transacción atómica.

La solución es deliberadamente enfocada: una sola organización con múltiples almacenes, valorización por promedio ponderado, sin lotes ni facturación en el MVP. La arquitectura deja preparada la evolución (multi-organización, lotes, FIFO) sin introducir esa complejidad ahora.

Detalle completo en el [dominio de inventario](./architecture/inventory-domain.md) y la [arquitectura del sistema](./architecture/system-architecture.md).

## 4. Usuarios objetivo

Los usuarios de la organización son cuatro roles: Administrador (configura la organización, usuarios, roles y almacenes), Jefe de almacén (gestiona sus almacenes), Operador de almacén (ejecuta movimientos del día a día) y Auditor (consulta en modo solo lectura). El alcance por almacén se modela aparte del rol: un usuario accede únicamente a los almacenes que se le autorizan.

El proyecto también se dirige a su autor como vehículo de aprendizaje: cada entregable explica el flujo de ejecución, las decisiones técnicas y los patrones, para que pueda comprenderlos y defenderlos profesionalmente.

Detalle completo en el [acta de constitución](./planning/project-charter.md) (§3).

## 5. Objetivos y criterios de éxito

Los objetivos de producto son existencias exactas respaldadas por kardex, integridad transaccional, transferencias atómicas, reportes y valorización, auditoría y control de acceso validado en el servidor. Los objetivos de aprendizaje cubren arquitectura limpia, transacciones y bloqueos en MySQL, API REST segura, frontend Angular moderno y pruebas automatizadas.

Los criterios generales de éxito incluyen: una entrada, salida y transferencia dejan kardex y existencias coherentes; una salida mayor al stock se rechaza incluso bajo concurrencia; la suite de pruebas pasa en integración continua; el sistema es desplegable con un comando; un usuario sin permiso sobre un almacén no puede operar en él; y el autor puede explicar el flujo completo de una operación.

Detalle completo en el [acta de constitución](./planning/project-charter.md) (§4, §8).

## 6. Alcance (MVP, V1, V2 y descartado)

El MVP (MUST HAVE) entrega una organización con multi-almacén: autenticación y RBAC con alcance por almacén; CRUD de almacenes, categorías, unidades y productos; inventario con entradas, salidas, ajustes, transferencias, kardex y reversiones; tablero con indicadores reales; reportes de existencias, movimientos y valorización exportables a PDF y Excel; auditoría; y calidad operativa (paginación, filtros, errores uniformes, logs, pruebas, documentación y despliegue).

Fuera del MVP (WON'T) quedan multi-organización, facturación fiscal, contabilidad, lotes y series, órdenes de compra, costeo FIFO, lector de códigos de barras, Redis, microservicios, Kubernetes, event sourcing, MFA/SSO y app nativa. La importación de catálogo y la carga inicial de existencias sí pertenecen al MVP; la captura por OCR, el intercambio EDI, la integración POS y la captura por código de barras siguen fuera del alcance. V1 (SHOULD) incorpora recuperación de contraseña, lotes y vencimientos, órdenes de compra y mejoras de exportación. V2 (COULD) incorpora multi-organización y costeo por capas.

Detalle completo en el [alcance y hitos](./planning/scope-and-milestones.md) y los [requisitos](./planning/requirements.md).

## 7. Requisitos

Los requisitos funcionales se numeran RF-001 a RF-137 agrupados en diez módulos (autenticación y usuarios, almacenes, categorías y unidades, productos, inventario, tablero, reportes, auditoría, calidad e importación de datos). El módulo de importación cubre la plantilla, la validación previa en modo dry-run, la importación de catálogo y la carga inicial de existencias. Los no funcionales se numeran RNF-001 a RNF-030 y cubren rendimiento, seguridad, disponibilidad, mantenibilidad, verificabilidad, portabilidad, accesibilidad (WCAG 2.1 AA), observabilidad e integridad. La prioridad usa la escala MoSCoW, con la regla de que ningún MUST pertenece a una fase posterior.

El documento de requisitos actúa como maestro: define la metodología, las reglas de negocio (RN-INV, RN-GEN y RN-IMP), las historias de usuario (HU-001 a HU-036) y la matriz de trazabilidad que vincula módulo, RF, HU y criterios de aceptación. El detalle funcional y no funcional vive en sus documentos respectivos.

Detalle completo en [requisitos](./planning/requirements.md), [requisitos funcionales](./planning/functional-requirements.md) y [requisitos no funcionales](./planning/non-functional-requirements.md).

## 8. Historias y criterios de aceptación

Las 36 historias de usuario agrupan el trabajo por módulo con formato «Como… quiero… para…». Los criterios de aceptación (AC-001 a AC-070) son verificables en formato Dado/Cuando/Entonces e incluyen los casos críticos: sobreventa rechazada, transferencia atómica, prevención de stock negativo, idempotencia, reversión trazable, autorización por almacén, paginación y filtrado, formato uniforme de errores, importación atómica e idempotente y carga inicial que cuadra con el kardex. Los escenarios de concurrencia C-01 a C-04 del dominio de inventario se reflejan como criterios.

Detalle completo en los [criterios de aceptación](./planning/acceptance-criteria.md).

## 9. Modelo de dominio del inventario (el núcleo)

El corazón del sistema separa la intención del efecto: un **documento** de inventario (tipo `entrada`, `salida`, `ajuste` o `transferencia`) agrupa **líneas** (producto y cantidad); al confirmarse, genera **movimientos** inmutables (el kardex) y actualiza el saldo materializado, todo dentro de una transacción. Los documentos tienen estados con transiciones definidas (`borrador` → `confirmado` → `revertido`, o `borrador` → `descartado`) y jamás se editan ni se borran: las correcciones se hacen con reversiones que dejan rastro.

La integridad se garantiza con bloqueo pesimista (`SELECT ... FOR UPDATE`) adquirido siempre en orden consistente por `(warehouse_id, product_id)` para evitar interbloqueos, con reintento ante deadlock; idempotencia mediante una clave única por documento; y una máquina de estados que impide confirmar dos veces. La valorización del MVP es por **promedio ponderado móvil** (se comparó con FIFO y se pospuso FIFO por no haber lotes). Se definen siete invariantes del dominio (I-01 a I-07), entre ellas que la existencia nunca es negativa y que el saldo materializado siempre iguala la suma del kardex.

Detalle completo en el [dominio de inventario](./architecture/inventory-domain.md).

## 10. Arquitectura

La arquitectura es un monolito modular con API-first: una API Laravel organizada por capas (controladores delgados, casos de uso transaccionales, dominio, modelos, políticas) y una SPA Angular que la consume. No hay microservicios, Kubernetes, Redis ni event sourcing: se descartaron por principio de no introducir infraestructura sin necesidad justificada.

En el backend, el controlador solo traduce HTTP; la transacción y las reglas viven en un servicio de caso de uso que no conoce HTTP, de modo que las mismas reglas aplican sin importar el origen de la operación. En el frontend, el acceso a la API se centraliza en servicios, el estado local usa Signals, y los interceptores funcionales aplican autenticación y manejo de errores. Las guardias de ruta son ayuda de navegación; la autorización real siempre ocurre en el backend.

Detalle completo en la [arquitectura del sistema](./architecture/system-architecture.md), la [arquitectura backend](./architecture/backend-architecture.md) y la [arquitectura frontend](./architecture/frontend-architecture.md).

## 11. Stack tecnológico

El stack aprobado, con versiones estables verificadas el 10/10/2026: PHP 8.4 y Laravel 13.35 para el backend; Laravel Sanctum 4.3 para autenticación; MySQL 8.4 LTS con InnoDB para persistencia; Angular 22.2 con TypeScript 6.0, Signals y Reactive Forms para el frontend; Pest 5 sobre PHPUnit 13 para pruebas; Laravel Pint para estilo; barryvdh/laravel-dompdf 3.1 y maatwebsite/excel 4.0 para exportación; Podman 5.4.2 con Buildah 1.39.3 y podman-compose 1.6.0 para el entorno local sin demonio (ADR-017); GitHub Actions para integración continua; y OpenAPI para el contrato de la API.

Cada elección documenta su alternativa evaluada y su razón. Se descartaron `spatie/laravel-permission` (se prefirió RBAC nativo por el alcance por almacén y por valor pedagógico), Redis (se usa la cola de base de datos), SQLite para pruebas de concurrencia (no reproduce el bloqueo de InnoDB) y tipos flotantes para dinero (se usan DECIMAL). **Existe una incompatibilidad de entorno pendiente de confirmación:** Angular 22 exige Node 22/24 LTS, pero el equipo tiene Node 20.19.2, que alcanzó fin de soporte el 30/04/2026.

Detalle completo en las [decisiones de arquitectura](./architecture/architecture-decisions.md).

## 12. Modelo de datos

El modelo tiene cuatro grupos de tablas. Seguridad y acceso: `users`, `roles`, `permissions`, `role_user`, `permission_role`, `warehouses`, `warehouse_user`. Catálogo: `categories` (jerárquica), `units_of_measure`, `products`, `product_warehouse` (mínimo y máximo por almacén). Inventario: `inventories` (saldo materializado, con restricción única por producto y almacén), `inventory_documents`, `inventory_document_lines` e `inventory_movements` (append-only). Auditoría: `audit_logs`.

Las cantidades usan `DECIMAL(15,3)` y los montos `DECIMAL` con precisión interna; nunca tipos flotantes. Se fijan restricciones únicas (SKU, código de almacén, uuid, doc_number, idempotency_key, par producto-almacén), claves foráneas, restricciones `CHECK` como última red (cantidad positiva, existencia no negativa, enums válidos) e índices para el kardex. Las reglas que MySQL no puede garantizar (disponibilidad, atomicidad, transiciones, promedio ponderado) se aplican en el servicio transaccional.

Detalle completo en el [diseño de base de datos](./architecture/database-design.md) y el [diagrama entidad-relación](./architecture/er-diagram.md).

## 13. API

La API se versiona bajo `/api/v1`, con autenticación Sanctum (cookie y CSRF para la SPA propia, tokens con habilidades para integraciones futuras). Convenciones uniformes: JSON, fechas ISO-8601 en UTC, paginación por página, filtrado, ordenamiento y búsqueda, formato de error uniforme y cabecera `Idempotency-Key` en la creación y confirmación de documentos. Los endpoints cubren autenticación, usuarios, roles y permisos, almacenes, categorías, unidades, productos, documentos de inventario (crear, confirmar, descartar, revertir), kardex, tablero, reportes y auditoría.

Los errores de negocio del inventario son explícitos, por ejemplo `409 STOCK_INSUFICIENTE`, `409` por idempotencia, `422` por validación y `403` por falta de alcance sobre el almacén. La especificación se publica con OpenAPI.

Detalle completo en el [diseño de API](./architecture/api-design.md).

## 14. Seguridad

La seguridad sigue mínimo privilegio, defensa en profundidad y trazabilidad sin datos sensibles. El frontend nunca se considera confiable para la autorización; toda validación y decisión de acceso ocurre en el servidor. La autorización combina Policies y Gates de Laravel con el alcance por almacén (`warehouse_user`), denegando por defecto. Se documenta un modelo de amenazas estilo STRIDE (robo de sesión, IDOR y salto de alcance, asignación masiva, carreras de stock, replay de idempotencia, fuerza bruta, fuga de secretos, registro sensible), controles, mapeo al OWASP Top 10, respuesta a incidentes y una lista de verificación previa al lanzamiento.

Los secretos viven en variables de entorno y nunca en el repositorio; la auditoría nunca almacena contraseñas, tokens ni secretos.

Detalle completo en el [diseño de seguridad](./architecture/security-design.md).

## 15. Frontend y experiencia

El frontend es una SPA Angular 22 con componentes standalone (zoneless por defecto), estado local con Signals y `computed()`, RxJS solo donde los flujos asíncronos lo justifican, Reactive Forms para formularios complejos (como la creación de documentos con líneas) y `provideHttpClient` con interceptores funcionales para autenticación y manejo centralizado de errores. La estructura separa `core`, `shared` y `features/*`. El acceso a la API se centraliza en servicios; los componentes no conocen los detalles de comunicación.

Detalle completo en la [arquitectura frontend](./architecture/frontend-architecture.md).

## 16. Backend y patrones

El backend es Laravel 13.35 con un controlador delgado por endpoint, Form Requests para validación, API Resources para las respuestas, casos de uso (servicios) para la lógica transaccional, modelos Eloquent, Policies y Gates para autorización, eventos y listeners, trabajos en cola (driver de base de datos) y notificaciones. La confirmación de documentos concentra la transacción, el bloqueo, las validaciones de negocio y la escritura del kardex.

Detalle completo en la [arquitectura backend](./architecture/backend-architecture.md).

## 17. Despliegue y operación

El entorno canónico es Podman con podman-compose (`compose.yaml`) y servicios de MySQL, backend y frontend, con Dockerfiles ligeros construidos por Buildah, entornos local/staging/producción, integración continua en tres etapas (lint con Pint, pruebas con Pest, build de Angular) y despliegue condicionado. Los secretos se inyectan por variables de entorno, hay respaldos de base de datos y observabilidad por logs. Se documenta la restricción de recursos del equipo local (RAM ajustada) como consideración operativa.

Detalle completo en la [arquitectura de despliegue](./architecture/deployment-architecture.md) y el [flujo de trabajo de desarrollo](./development/development-workflow.md).

## 18. Pruebas y calidad

La estrategia sigue el patrón de pirámide: pruebas unitarias de la lógica de costeo y estados; pruebas de integración contra MySQL 8.4 real (nunca SQLite cuando la concurrencia importa) que verifican la atomicidad y las invariantes I-01 a I-07; pruebas de autorización por almacén; pruebas de concurrencia C-01 a C-04 (sobreventa, transferencias cruzadas, primera inserción concurrente, doble confirmación); y pruebas de extremo a extremo. La cobertura objetivo es alta en el núcleo y razonable en el resto. La verificación de la invariante «kardex = saldo» se hace con una consulta SQL que falla si hay deriva.

Detalle completo en la [estrategia de pruebas](./development/testing-strategy.md) y los [estándares de código](./development/coding-standards.md).

## 19. Fases, backlog y orden de implementación

El trabajo se ordena en ocho fases: Fase 0 fundaciones (repositorio, Podman, esqueleto, CI); Fase 1 autenticación, RBAC y almacenes; Fase 2 catálogo (categorías, unidades, productos e importación de catálogo); Fase 3 inventario (documentos, movimientos, kardex, reversos y carga inicial de existencias); Fase 4 tablero; Fase 5 reportes y exportación; Fase 6 auditoría; Fase 7 calidad y despliegue. Cada fase define criterios de salida medibles. Las duraciones son `[ESTIMACIÓN]`, sin fechas calendario comprometidas.

El backlog descompone el alcance en trece épicas (EP-001 a EP-013) con tareas técnicas, dependencias y prioridad MoSCoW. El orden recomendado comienza por autenticación y almacenes, porque todo lo demás exige autorización por almacén, y culmina con el inventario, que es el núcleo de mayor riesgo.

Detalle completo en [alcance y hitos](./planning/scope-and-milestones.md) y el [backlog](./planning/backlog.md).

## 20. Riesgos y decisiones pendientes de confirmación

Los riesgos principales son las condiciones de carrera en el inventario (mitigadas con bloqueo en orden consistente y pruebas), los interbloqueos (reintento y orden global), la duplicación por reintentos (idempotencia), la deriva kardex-saldo (transacción única y consulta de verificación), el sobrediseño (regla explícita contra infraestructura por moda), los errores de valorización (DECIMAL y estrategia de redondeo) y las herramientas locales ausentes (Podman como entorno canónico, sin demonio).

Quedan tres decisiones que requieren confirmación de Master porque cambian el entorno o el alcance:
- **ADR-006 (entorno Node):** subir Node a 22/24 LTS y usar Angular 22, o mantener Node 20 con Angular 21. Recomendación: subir Node.
- **ADR-010 (ejecutor de pruebas):** Pest 5 (recomendado) o PHPUnit 13.
- **Confirmación general del alcance del MVP** antes de abrir la Fase 0.

Detalle completo en las [decisiones de arquitectura](./architecture/architecture-decisions.md) y el [acta de constitución](./planning/project-charter.md) (§7).

## 21. Estado del proyecto y siguiente paso

El proyecto está en fase de planificación. El repositorio contiene únicamente esta especificación en `docs/`, sin código de aplicación, sin `backend/` ni `frontend/` y sin repositorio Git inicializado todavía. La bitácora de esta fase está en [la bitácora](./logbook/development-log.md).

El siguiente paso recomendado es que Master revise y apruebe esta documentación, confirme las decisiones pendientes de la sección 20, y autorice el inicio de la Fase 0 (fundaciones) con la instalación de Composer y MySQL 8.4 LTS (o el uso de podman-compose con Podman), la subida de Node a 22/24 LTS y la inicialización del repositorio Git.

Índice completo de la documentación en [docs/README.md](./README.md).
