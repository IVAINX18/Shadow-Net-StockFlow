# Shadow-Net StockFlow

Shadow-Net StockFlow es un sistema ERP web de gestión de inventarios y logística multi-almacén, desarrollado como producto del ecosistema Shadow-Net Technologies y proyecto central del portafolio de Ingeniería de Sistemas de su autor. Permite a una organización administrar productos, existencias, almacenes, movimientos de mercancía, transferencias, usuarios, permisos, reportes y auditoría desde una plataforma centralizada, con integridad transaccional garantizada por el backend.

El núcleo del sistema no es una columna de stock, sino un libro de movimientos (kardex) inmutable: cada operación de inventario se confirma dentro de una transacción atómica, con bloqueo de filas para impedir la sobreventa, idempotencia para evitar duplicados y reversiones trazables para corregir sin borrar.

Estado actual: **fase de planificación - documentación**.

Documentación: ver [docs/](docs/) y la [especificación maestra](docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md).

Nota: todavía NO hay código de aplicación en este repositorio. No existen `backend/` ni `frontend/`; solo la especificación en `docs/`. El desarrollo del MVP comenzará cuando se apruebe la documentación.

## Nombres canónicos

**Nombres canónicos (no usar sinónimos en otras fases):** el detalle de tablas está en [docs/architecture/database-design.md](docs/architecture/database-design.md). La base de la API es `/api/v1`. Los tipos de documento de inventario son `entrada`, `salida`, `ajuste` y `transferencia`; los estados son `borrador`, `confirmado`, `revertido` y `descartado`. La valorización del MVP es por promedio ponderado móvil.

## Stack objetivo (versiones verificadas el 10/10/2026)

- **Backend:** PHP 8.4, Laravel 13.35, Laravel Sanctum 4.3, API REST versionada en `/api/v1`.
- **Frontend:** Angular 22.2 (standalone, zoneless), TypeScript 6.0, Signals, Reactive Forms.
- **Base de datos:** MySQL 8.4 LTS con InnoDB.
- **Calidad y despliegue:** Pest 5, Laravel Pint, Docker Compose, GitHub Actions, OpenAPI.

El detalle y la justificación de cada elección están en [docs/architecture/architecture-decisions.md](docs/architecture/architecture-decisions.md).

## Requisitos del entorno de desarrollo

| Herramienta | Estado en el equipo de referencia | Acción |
| --- | --- | --- |
| PHP 8.4 | Instalado (8.4.26) | Ninguna. |
| Composer 2.10 | No instalado | Instalar con el instalador oficial. |
| Node.js | 20.19.2 (EOL) | Subir a Node 22 LTS o 24 LTS (ver ADR-006). |
| MySQL 8.4 LTS | No instalado | Instalar localmente o usar el contenedor de Docker Compose. |
| Docker / Compose | Instalado (26.1.5 / v5.6.0) | Ninguna. |
| Angular CLI | No instalado | Se instala en la Fase 1. |

## Cómo leer la documentación

1. [Índice de documentación](docs/README.md) para orientarse.
2. [Especificación maestra](docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md) para la visión completa.
3. [Acta de constitución](docs/planning/project-charter.md) y [alcance y hitos](docs/planning/scope-and-milestones.md) para el qué y el cuándo.
4. [Dominio de inventario](docs/architecture/inventory-domain.md) para el corazón del sistema.

## Autor

Shadow-Net Technologies. Proyecto de aprendizaje y portafolio de Ingeniería de Sistemas.
