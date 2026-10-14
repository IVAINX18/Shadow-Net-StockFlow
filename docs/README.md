# Índice de documentación de Shadow-Net StockFlow

## Propósito

Este índice es la puerta de entrada a la especificación de Shadow-Net StockFlow. Enumera los documentos del proyecto, explica a quién va dirigido cada uno y propone rutas de lectura según el perfil del lector. No contiene decisiones nuevas: solo organiza y enlaza la documentación existente. La síntesis completa está en [SHADOW_NET_STOCKFLOW_SPECIFICATION.md](./SHADOW_NET_STOCKFLOW_SPECIFICATION.md).

## Estado del proyecto

El proyecto está en desarrollo. Fecha de esta revisión: 13/10/2026.

Las **fases 0, 1 y 2 están completadas y verificadas**. La Fase 0 (fundaciones) dejó `backend/` (Laravel 13.35) y `frontend/` (Angular 22.2), el entorno se levanta con `podman-compose up -d --build` y la integración continua pasa en la rama principal. La Fase 1 entregó la autenticación con Sanctum, el RBAC nativo y los almacenes con alcance por usuario. La Fase 2 entregó el catálogo (categorías jerárquicas, unidades de medida y productos con configuración por almacén) y su importación con plantilla, validación previa y aplicación atómica e idempotente, en la API y en la SPA. El siguiente paso es la Fase 3 (inventario y kardex). El avance real, unidad por unidad, se registra en la [bitácora](./logbook/development-log.md).

## Tabla de documentos

| Documento | Descripción | Ruta | Para quién |
| --- | --- | --- | --- |
| Acta de constitución | Problema, usuarios, objetivos, alcance, exclusiones, riesgos y éxito | [./planning/project-charter.md](./planning/project-charter.md) | fundador/ingeniero |
| Requisitos (maestro) | Metodología, MoSCoW, reglas de negocio, historias y trazabilidad | [./planning/requirements.md](./planning/requirements.md) | ingeniero |
| Requisitos funcionales | RF-001..RF-125 detallados por módulo | [./planning/functional-requirements.md](./planning/functional-requirements.md) | ingeniero |
| Requisitos no funcionales | RNF-001..RNF-030 de calidad y operación | [./planning/non-functional-requirements.md](./planning/non-functional-requirements.md) | ingeniero |
| Alcance y hitos | Fases 0..7 con criterios de salida y dependencias | [./planning/scope-and-milestones.md](./planning/scope-and-milestones.md) | fundador/ingeniero |
| Backlog | Épicas, tareas técnicas, dependencias y orden de implementación | [./planning/backlog.md](./planning/backlog.md) | ingeniero |
| Criterios de aceptación | Criterios verificables (AC-###) por módulo e historia | [./planning/acceptance-criteria.md](./planning/acceptance-criteria.md) | ingeniero |
| Arquitectura del sistema | Componentes, flujos, patrones y despliegue lógico | [./architecture/system-architecture.md](./architecture/system-architecture.md) | ingeniero |
| Decisiones de arquitectura | Registro ADR-001..ADR-017 con versiones fijadas | [./architecture/architecture-decisions.md](./architecture/architecture-decisions.md) | ingeniero |
| Diseño de base de datos | Tablas, tipos, índices, restricciones y migraciones | [./architecture/database-design.md](./architecture/database-design.md) | ingeniero |
| Diagrama entidad-relación | Modelo ER en Mermaid y cardinalidades | [./architecture/er-diagram.md](./architecture/er-diagram.md) | ingeniero |
| Dominio de inventario | Reglas, estados, transacciones, concurrencia y valorización | [./architecture/inventory-domain.md](./architecture/inventory-domain.md) | ingeniero |
| Diseño de API | Endpoints `/api/v1`, validaciones, errores y paginación | [./architecture/api-design.md](./architecture/api-design.md) | ingeniero |
| Seguridad | Modelo de amenazas, controles y checklist pre-lanzamiento | [./architecture/security-design.md](./architecture/security-design.md) | ingeniero |
| Arquitectura backend | Capas Laravel, casos de uso y ciclo de petición | [./architecture/backend-architecture.md](./architecture/backend-architecture.md) | ingeniero |
| Arquitectura frontend | Angular standalone, Signals, interceptores y guardias | [./architecture/frontend-architecture.md](./architecture/frontend-architecture.md) | ingeniero |
| Sistema de diseno | Tokens, temas claro y oscuro, tipografia, densidad, estados y verificacion visual | [./architecture/design-system.md](./architecture/design-system.md) | ingeniero |
| Arquitectura de despliegue | Podman, entornos, CI/CD y respaldos | [./architecture/deployment-architecture.md](./architecture/deployment-architecture.md) | ingeniero |
| Estándares de código | Convenciones PHP/TypeScript y política de comentarios | [./development/coding-standards.md](./development/coding-standards.md) | ingeniero |
| Estrategia de pruebas | Pirámide, pruebas de inventario y concurrencia | [./development/testing-strategy.md](./development/testing-strategy.md) | ingeniero |
| Flujo de trabajo | Ramas, commits, entornos y definición de terminado | [./development/development-workflow.md](./development/development-workflow.md) | ingeniero |
| Bitácora | Registro de cambios por unidad de trabajo | [./logbook/development-log.md](./logbook/development-log.md) | ingeniero |
| Especificación maestra | Síntesis con enlaces a cada documento | [./SHADOW_NET_STOCKFLOW_SPECIFICATION.md](./SHADOW_NET_STOCKFLOW_SPECIFICATION.md) | fundador/ingeniero |

## Rutas de lectura recomendadas

### Nuevo en el proyecto

1. Este índice para orientarse.
2. La [especificación maestra](./SHADOW_NET_STOCKFLOW_SPECIFICATION.md) para la visión completa.
3. El [acta de constitución](./planning/project-charter.md) para el marco del producto.
4. La [arquitectura del sistema](./architecture/system-architecture.md) para el cómo técnico.

### Ingeniero implementando el MVP

1. [Arquitectura del sistema](./architecture/system-architecture.md) y [decisiones de arquitectura](./architecture/architecture-decisions.md).
2. [Dominio de inventario](./architecture/inventory-domain.md) (el núcleo) y [diseño de base de datos](./architecture/database-design.md).
3. [Diseño de API](./architecture/api-design.md) y [seguridad](./architecture/security-design.md).
4. [Requisitos funcionales](./planning/functional-requirements.md), [criterios de aceptación](./planning/acceptance-criteria.md) y [backlog](./planning/backlog.md).
5. [Estrategia de pruebas](./development/testing-strategy.md) y [estándares de código](./development/coding-standards.md).

### Fundador evaluando el proyecto

1. [Acta de constitución](./planning/project-charter.md) para problema, usuarios y alcance.
2. [Alcance y hitos](./planning/scope-and-milestones.md) para la secuencia de fases.
3. [Backlog](./planning/backlog.md) para el orden de construcción.

## Cómo mantener la documentación

- Cada documento tiene un único responsable de edición: se edita su propio archivo y nunca el de otro.
- Los enlaces son relativos al archivo que los contiene (por ejemplo, desde `docs/` se usa `./planning/...`).
- Mantener un solo `#` H1 por archivo, secciones con `##`, fechas en formato DD/MM/AAAA.
- No usar emojis ni marcadores de pendiente en ningún documento.
- Etiquetar cada dato, estimación o afirmación legal con `[DATO]`, `[HIPÓTESIS]`, `[ESTIMACIÓN]` o `[DECISIÓN]` y `Fuente:` cuando corresponda.
- Actualizar la [bitácora](./logbook/development-log.md) al cerrar cada unidad de trabajo.
- Este índice se actualiza solo si se añade, elimina o renombra un documento.

## Glosario

- **ERP:** sistema de planificación de recursos empresariales.
- **SKU:** identificador único de producto.
- **Kardex:** historial de movimientos con saldo resultante.
- **Documento de inventario:** operación de negocio (entrada, salida, ajuste o transferencia).
- **Movimiento:** efecto atómico e inmutable sobre la existencia de un producto en un almacén.
- **Valorización:** cálculo del valor monetario del inventario (promedio ponderado en el MVP).
- **Idempotencia:** propiedad por la cual repetir una petición no duplica su efecto.
- **RBAC:** control de acceso basado en roles.
- **MVP:** producto mínimo viable: una organización, multi-almacén, con inventario transaccional.
