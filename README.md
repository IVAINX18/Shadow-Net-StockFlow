# Shadow-Net StockFlow

Shadow-Net StockFlow es un sistema ERP web de gestión de inventarios y logística multi-almacén, desarrollado como producto del ecosistema Shadow-Net Technologies y proyecto central del portafolio de Ingeniería de Sistemas de su autor. Permite a una organización administrar productos, existencias, almacenes, movimientos de mercancía, transferencias, usuarios, permisos, reportes y auditoría desde una plataforma centralizada, con integridad transaccional garantizada por el backend.

El núcleo del sistema no es una columna de stock, sino un libro de movimientos (kardex) inmutable: cada operación de inventario se confirma dentro de una transacción atómica, con bloqueo de filas para impedir la sobreventa, idempotencia para evitar duplicados y reversiones trazables para corregir sin borrar.

**Estado actual:** Fase 0 (fundaciones) **completada y verificada**; Fase 1 (autenticación, RBAC y almacenes) **en curso**, con las unidades 1 y 2 terminadas.

## Objetivos

**De producto**

- Ofrecer existencias exactas por producto y almacén, respaldadas por un kardex completo.
- Garantizar la integridad transaccional: ninguna salida consume más de lo disponible ni deja saldos negativos.
- Permitir transferencias atómicas entre almacenes con trazabilidad de origen y destino.
- Proveer reportes de existencias, movimientos y valorización, exportables a PDF y Excel.
- Registrar auditoría de cada operación relevante y controlar el acceso por rol y por almacén.

**De aprendizaje**

- Comprender y aplicar arquitectura limpia y separación de responsabilidades.
- Dominar transacciones, bloqueo de filas e idempotencia en MySQL con Laravel.
- Construir una API REST versionada, segura y documentada con OpenAPI.
- Desarrollar un frontend Angular con componentes standalone, Signals y patrón de servicios.
- Establecer pruebas unitarias, de integración, de autorización y de concurrencia.

## Nombres canónicos

**Nombres canónicos (no usar sinónimos en otras fases):** el detalle de tablas está en [docs/architecture/database-design.md](docs/architecture/database-design.md). La base de la API es `/api/v1`. Los tipos de documento de inventario son `entrada`, `salida`, `ajuste` y `transferencia`; los estados son `borrador`, `confirmado`, `revertido` y `descartado`. La valorización del MVP es por promedio ponderado móvil.

## Stack en uso (versiones verificadas)

| Capa | Tecnología | Versión |
| --- | --- | --- |
| Backend | PHP / Laravel / Sanctum | 8.4.26 / 13.35.0 / 4.3.3 |
| Base de datos | MySQL LTS (InnoDB) | 8.4.11 |
| Pruebas y estilo | Pest / Laravel Pint | 5.3.1 / 1.32.1 |
| Frontend | Angular / TypeScript | 22.2.2 / 6.0.3 |
| Runtime del frontend | Node.js LTS (instalado con nvm) | 22.23.3 |
| Entorno reproducible | Podman / Buildah / podman-compose | 5.4.2 / 1.39.3 / 1.6.0 |
| Gestor de dependencias PHP | Composer | 2.10.3 |
| Integración continua | GitHub Actions | — |

La justificación de cada elección, con las alternativas descartadas, está en [docs/architecture/architecture-decisions.md](docs/architecture/architecture-decisions.md) (ADR-001 a ADR-017).

## Estructura del repositorio

```text
Shadownet_Stockflow/
├── README.md                 Este archivo
├── compose.yaml              Servicios mysql, backend y frontend (podman-compose)
├── .env.example              Variables de entorno para la composición
├── .github/workflows/ci.yml  Integración continua: Pint, Pest y build de Angular
├── backend/                  API Laravel 13
├── frontend/                 SPA Angular 22
└── docs/                     Especificación completa del proyecto
```

## Puesta en marcha (entorno local)

1. Copiar el archivo de ejemplo y completar los valores:

```bash
cp .env.example .env
php backend/artisan key:generate --show   # pegar el resultado en APP_KEY
```

2. Levantar los servicios:

```bash
podman-compose up -d --build
```

3. Aplicar migraciones y comprobar la API:

```bash
podman-compose exec -T backend php artisan migrate --force
curl http://localhost:8000/api/v1/health
```

| Servicio | URL |
| --- | --- |
| API (salud) | http://localhost:8000/api/v1/health |
| Frontend | http://localhost:4200 |
| MySQL | localhost:3306 |

4. Ejecutar las pruebas del backend (requieren el contenedor de MySQL levantado):

```bash
cd backend && php artisan test
```

## Estado de las fases

| Fase | Alcance | Estado |
| --- | --- | --- |
| Fase 0 | Fundaciones: repositorio, contenedores, esqueleto Laravel y Angular, CI | Completada |
| Fase 1 | Autenticación, RBAC y almacenes | En curso (unidades 1 y 2 completadas) |
| Fase 2 | Catálogo: categorías, unidades, productos e importación de catálogo | Pendiente |
| Fase 3 | Inventario: documentos, movimientos, kardex, reversos y carga inicial de existencias | Pendiente |
| Fases 4 a 7 | Tablero, reportes, auditoría, calidad y despliegue | Pendientes |

El detalle de cada fase, con sus criterios de salida medibles, está en [docs/planning/scope-and-milestones.md](docs/planning/scope-and-milestones.md).

## Requisitos del entorno de desarrollo

| Herramienta | Estado en el equipo de referencia | Acción |
| --- | --- | --- |
| PHP 8.4 | Instalado (8.4.26, con `intl`) | Ninguna. |
| Composer 2.10 | Instalado (2.10.3, a nivel de usuario) | Ninguna. |
| Node.js | Instalado (22.23.3, con nvm) | Ninguna. |
| MySQL 8.4 LTS | No instalado en el host | Se usa el contenedor de Podman. |
| Podman / podman-compose | Instalado (5.4.2 / 1.6.0 vía pipx) | Ninguna. Docker sigue instalado pero su demonio está apagado y no se usa (ADR-017). |
| Angular CLI | Dependencia del proyecto en `frontend/` | `npm install` dentro de `frontend/`. |

## Cómo leer la documentación

1. [Índice de documentación](docs/README.md) para orientarse.
2. [Especificación maestra](docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md) para la visión completa.
3. [Acta de constitución](docs/planning/project-charter.md) y [alcance y hitos](docs/planning/scope-and-milestones.md) para el qué y el cuándo.
4. [Dominio de inventario](docs/architecture/inventory-domain.md) para el corazón del sistema.
5. [Bitácora de desarrollo](docs/logbook/development-log.md) para el avance real, unidad por unidad.

## Autor

Shadow-Net Technologies. Proyecto de aprendizaje y portafolio de Ingeniería de Sistemas.
