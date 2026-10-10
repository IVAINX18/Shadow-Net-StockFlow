# Flujo de desarrollo - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento define cómo organizo el repositorio, cómo trabajo por ramas, cómo propongo y reviso cambios, en qué entornos ejecuto el sistema, cómo preparo mi máquina, cómo corre la integración continua y cuándo una tarea queda terminada. Complementa los estándares de [coding-standards.md](./coding-standards.md) y la estrategia de [testing-strategy.md](./testing-strategy.md). Las decisiones que lo sustentan están en [architecture-decisions.md](../architecture/architecture-decisions.md) (ADR-017, ADR-016) y el marco general en [project-charter.md](../planning/project-charter.md).

## 1. Estructura del repositorio

Uso un solo repositorio monorepo, según ADR-016. Hoy solo existe `docs/`; `backend/` y `frontend/` nacen en sus fases correspondientes:

```
Shadownet_Stockflow/
├── docs/
│   ├── planning/        # Acta, requisitos, alcance y fases
│   ├── architecture/    # Dominio, decisiones, base de datos, API, seguridad
│   ├── development/     # Este documento y sus compañeros
│   ├── logbook/         # Bitácora (development-log.md)
│   └── tasks/           # Notas de trabajo por unidad
├── backend/             # API Laravel 13 (fase posterior)
└── frontend/            # SPA Angular 22 (fase posterior)
```

Una sola historia de versiones y una sola bitácora para todo el sistema. Nada de repositorios separados.

## 2. Estrategia de ramas

Mantengo `main` siempre desplegable: cada commit en `main` pasa la integración continua y podría desplegarse. Trabajo en ramas cortas de funcionalidad que nacen de `main` y vuelven a `main` por pull request.

Nombro las ramas por tipo y tema, en inglés y minúsculas: `feat/transfer-confirmation`, `fix/negative-stock-guard`, `docs/development-workflow`, `test/concurrency-c01`. Ninguna rama vive más de unos pocos días; si crece, la divido. Nunca subo cambios directamente a `main`: todo cambio pasa por pull request con revisión.

## 3. Proceso de commit y pull request

Escribo cada commit según la convención de [coding-standards.md](./coding-standards.md): Conventional Commits en inglés, un cambio lógico por commit, mensaje que explica el motivo además del contenido.

Cada pull request incluye: descripción breve del cambio, enlace a la fase o requisito que cubre, lista de pruebas ejecutadas con su resultado y nota de riesgos si toca el núcleo transaccional. El revisor verifica tres cosas: que el código sigue los estándares, que la suite afectada está en verde y que la documentación cambia junto al código cuando el comportamiento cambia.

Fusiono con squash hacia `main` para dejar un historial lineal y legible, un commit por unidad de trabajo. Borro la rama tras fusionar.

## 4. Entornos

- **Local.** Mi máquina o contenedores Podman con podman-compose en mi máquina. Datos de prueba desechables, credenciales de desarrollo por variables de entorno.
- **Staging.** Réplica del despliegue productivo con datos de prueba. Aquí verifico cada pull request fusionado antes de considerar la fase estable.
- **Producción.** Despliegue documentado con un solo comando, según CG-04 del acta. Solo recibe lo que ya pasó por staging con la suite en verde.

La paridad entre entornos la garantiza Podman con podman-compose como entorno canónico (ADR-017): funciona sin demonio en segundo plano. Las diferencias inevitables (puertos, credenciales, volúmenes) viven en variables de entorno, nunca en el código.

## 5. Preparación del entorno local

Necesito tres piezas en el host, con las versiones fijadas en [architecture-decisions.md](../architecture/architecture-decisions.md): PHP 8.4 con Composer 2.10.3 instalado a nivel de usuario mediante el instalador oficial, Node 22 LTS instalado vía nvm y MySQL 8.4 servido por Podman. Como alternativa, podman-compose 1.6.0 (instalado vía pipx) provee las tres sin tocar el host, opción recomendada si la máquina tiene limitaciones de memoria o si Master prefiere no modificar el entorno (ADR-006, riesgo R-07 y R-10 del acta). La composición vive en `compose.yaml` y los comandos canónicos son `podman-compose up -d --build`, `podman-compose exec -T backend php artisan migrate --force` y `podman-compose down`, tras copiar `.env.example` a `.env`.

Pasos de preparación:

1. Instalo Composer con el instalador oficial de getcomposer.org y verifico con `composer --version`.
2. Instalo Node 22 LTS y verifico con `node --version`; el proyecto exige Node 22 o superior para Angular 22.
3. Levanto MySQL 8.4, bien en el host o bien con `podman-compose up -d mysql`, y creo la base y el usuario de desarrollo.
4. En `backend/`, ejecuto `composer install`, copio `.env.example` a `.env`, genero la clave y ejecuto migraciones y seeders en el orden de [database-design.md](../architecture/database-design.md) (§7).
5. En `frontend/`, ejecuto `npm install` y `ng serve` para comprobar que la aplicación compila.

Documento cualquier desviación de estos pasos en la bitácora para que el siguiente arranque sea reproducible.

## 6. Pipeline de CI/CD con GitHub Actions

Cada push y cada pull request disparan el pipeline de GitHub Actions. El pipeline tiene tres etapas obligatorias, en este orden:

1. **Lint.** Laravel Pint en modo inspección para el backend y el compilador de TypeScript en modo estricto más el linter configurado para el frontend. Si el estilo falla, el pipeline se detiene aquí.
2. **Tests.** Suite Pest del backend contra un servicio MySQL 8.4 real (nunca SQLite para el núcleo, según [testing-strategy.md](./testing-strategy.md)), más las pruebas unitarias del frontend. Publico el resumen de resultados en el pull request.
3. **Build.** Compilación del backend (autoloader optimizado, sin dependencias de desarrollo) y del frontend (`ng build` en modo producción). El artefacto resultante es el que viaja a staging.

Solo un pipeline en verde habilita la fusión. El despliegue a staging es automático tras fusionar a `main`; el despliegue a producción es manual y documentado, con un solo comando según CG-04.

## 7. Definición de terminado

Una unidad de trabajo queda terminada cuando cumple todo lo siguiente:

- El código sigue [coding-standards.md](./coding-standards.md), con comentarios actualizados si cambió el comportamiento.
- Las pruebas exigidas por [testing-strategy.md](./testing-strategy.md) para su nivel están escritas y en verde.
- El pipeline de CI está en verde para la rama.
- La documentación afectada (arquitectura, API, bitácora) está actualizada en el mismo pull request.
- El revisor aprobó y la rama se fusionó a `main` con squash.

Sin estos cinco puntos, la tarea sigue abierta aunque el código funcione en mi máquina.

## 8. Regla de la bitácora

Después de cada unidad de trabajo, actualizo `docs/logbook/development-log.md` con una entrada que contiene: fecha, fase, archivos tocados, cambios realizados, motivo, pruebas ejecutadas, resultado y pendientes. Escribo la entrada el mismo día del cambio, con el detalle suficiente para que un revisor reconstruya lo ocurrido sin preguntarme.

Formato de cada entrada:

```markdown
## 10/10/2026 - Fase 2 - Confirmación de transferencias
- **Archivos:** backend/app/Services/Inventory/ConfirmTransfer.php, backend/tests/Feature/TransferTest.php
- **Cambios:** implemento la confirmación atómica con bloqueos en orden (warehouse_id, product_id).
- **Motivo:** evitar interbloqueos en transferencias cruzadas (C-02).
- **Pruebas:** Pest unitarias y C-02 en MySQL real.
- **Resultado:** 14 pruebas en verde.
- **Pendientes:** escenario e2e de transferencia en frontend.
```

La bitácora es obligatoria, no opcional: es la memoria del proyecto y la evidencia de que cada decisión quedó registrada cuando se tomó.
