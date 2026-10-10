# Bitácora de desarrollo - Shadow-Net StockFlow

**Autor:** Shadow-Net Technologies. **Zona horaria:** America/Bogota. **Formato de fecha:** DD/MM/AAAA.

Esta bitácora registra cada unidad de trabajo del proyecto: fecha, fase, archivos afectados, cambios, motivo, pruebas ejecutadas, resultado y pendientes. Se actualiza al cerrar cada unidad y nunca se reescribe el pasado: una corrección añade una entrada nueva.

## Resumen de entradas

| # | Fecha | Fase | Resultado |
| --- | --- | --- | --- |
| 1 | 10/10/2026 | Fase 0 - Planificación | Completada |

---

## Entrada 1 - 10/10/2026 - Inspección del repositorio y planificación integral

**Fase:** Fase 0 - Planificación (documentación, sin código de aplicación).

**Archivos afectados (creados):**
- `.gitignore`
- `docs/planning/project-charter.md`
- `docs/planning/requirements.md`
- `docs/planning/functional-requirements.md`
- `docs/planning/non-functional-requirements.md`
- `docs/planning/scope-and-milestones.md`
- `docs/planning/backlog.md`
- `docs/planning/acceptance-criteria.md`
- `docs/architecture/system-architecture.md`
- `docs/architecture/architecture-decisions.md`
- `docs/architecture/database-design.md`
- `docs/architecture/er-diagram.md`
- `docs/architecture/inventory-domain.md`
- `docs/architecture/api-design.md`
- `docs/architecture/security-design.md`
- `docs/architecture/frontend-architecture.md`
- `docs/architecture/backend-architecture.md`
- `docs/architecture/deployment-architecture.md`
- `docs/development/coding-standards.md`
- `docs/development/testing-strategy.md`
- `docs/development/development-workflow.md`
- `docs/README.md`
- `docs/SHADOW_NET_STOCKFLOW_SPECIFICATION.md`
- `README.md`

**Cambios efectuados:**
- Inspección del repositorio: se confirmó que estaba vacío (solo existía el directorio `.codegraph`), sin control de versiones inicializado y sin código de aplicación.
- Verificación del entorno local: PHP 8.4.26 con extensiones `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `zip`; Node 20.19.2 y npm 9.2.0; Docker 26.1.5 y Compose v5.6.0; Git 2.47.3. Ausentes: Composer, cliente/servidor MySQL y Angular CLI.
- Se detectó una incompatibilidad de entorno: Angular 22.2.x exige Node `^22.22.3 || ^24.15.0 || >=26` y el Node local (20.19.2) alcanzó fin de soporte el 30/04/2026. Se registró como ADR-006 y como riesgo R-07/R-11, con recomendación de subir a Node 22 LTS o 24 LTS.
- Se verificaron las versiones estables de todo el stack contra fuentes oficiales (Packagist, npm, docs de Laravel/Angular/MySQL) el 10/10/2026.
- Se elaboró la documentación de planificación completa: acta, requisitos, alcance, backlog, arquitectura, modelo de datos, dominio de inventario, API, seguridad, estándares, pruebas y flujo de trabajo.
- Se fijó el modelo canónico del inventario: documento, líneas y movimientos (libro contable), valorización por promedio ponderado móvil, bloqueo pesimista en orden consistente e idempotencia.

**Motivo:** la primera tarea del proyecto exige planificar antes de programar: inspeccionar el estado real, definir el alcance, diseñar la arquitectura y el modelo de datos, y establecer requisitos, pruebas y criterios de aceptación antes de escribir la primera línea de código de aplicación.

**Pruebas ejecutadas:**
- Inspección del sistema de archivos (`ls`, `find`) y del estado de Git (`git status`).
- Verificación de versiones de herramientas (`php -v`, `node -v`, `npm -v`, `docker --version`, `git --version`).
- Listado de extensiones de PHP (`php -m`).
- Consultas de versiones de dependencias contra Packagist y fuentes oficiales mediante el agente de investigación.

**Resultado:** no se ejecutó código de aplicación (no aplica suite de pruebas todavía). La planificación quedó documentada y coherente; no se afirma haber probado funcionalidad inexistente.

**Pendientes:**
- Confirmar con Master la decisión de entorno ADR-006 (subir Node a 22/24 LTS o mantener Angular 21 en Node 20).
- Confirmar la elección de ejecutor de pruebas (Pest 5 propuesto en ADR-010).
- Instalar Composer, MySQL 8.4 LTS (o usar Docker Compose) al iniciar la Fase 1.
- Inicializar el repositorio Git y establecer la rama principal al abrir la Fase 1.
- Comenzar la Fase 1 (fundaciones) tras la aprobación de la documentación.

**Nota:** esta entrada no registra pruebas de funcionalidad porque, por diseño de esta fase, no existe todavía código ejecutable. Se evita declarar verificaciones no realizadas.
