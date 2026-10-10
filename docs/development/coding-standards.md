# Estándares de codificación - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento define cómo escribo el código del proyecto: comentarios, convenciones de PHP y Laravel, convenciones de TypeScript y Angular, nomenclatura, mensajes de commit y organización de ficheros. Aplica a todo el código de `backend/` y `frontend/`. Las decisiones técnicas que lo sustentan viven en [architecture-decisions.md](../architecture/architecture-decisions.md), las reglas del dominio que el código debe reflejar en [inventory-domain.md](../architecture/inventory-domain.md), el modelo físico en [database-design.md](../architecture/database-design.md) y el marco del proyecto en [project-charter.md](../planning/project-charter.md).

## 1. Regla de comentarios del proyecto

Escribo los comentarios explicativos del código en español, en primera persona, sin emojis. Explico el PORQUÉ, no el QUÉ: la lógica de negocio que aplico, el sentido de cada transacción, el motivo de cada bloqueo, la razón de cada validación, las implicaciones de seguridad, el comportamiento del estado reactivo y cualquier decisión no evidente que tomo.

Documento los contratos con PHPDoc en el backend y con TSDoc en el frontend: parámetros, valores de retorno, excepciones e invariantes que garantizo. No escribo comentarios triviales ni redundantes que repitan lo que el código ya dice. No comento ficheros generados ni dependencias de terceros. Cuando modifico el código, actualizo sus comentarios para que sigan describiendo la realidad.

Ejemplos de lo que sí comento: por qué bloqueo las filas en orden ascendente por `(warehouse_id, product_id)`, por qué valido la disponibilidad dentro de la transacción y no antes, por qué una salida rechaza el stock insuficiente con rollback total, por qué la autorización se verifica en el servidor aunque el frontend oculte un botón.

Ejemplos de lo que no comento: asignaciones obvias, getters simples, importaciones, código generado por Artisan o por Angular CLI.

## 2. Convenciones de PHP y Laravel

Sigo PSR-12 como estilo base y lo verifico con Laravel Pint antes de cada commit. Configuro Pint con el preset de Laravel y no discuto su salida: si Pint reformatea algo, acepto el cambio.

Mantengo los controladores delgados. Cada controlador traduce HTTP a una llamada a un caso de uso y traduce el resultado a una respuesta JSON. Ninguna regla de negocio vive en el controlador, tal como exige la sección 5.2 de [inventory-domain.md](../architecture/inventory-domain.md). La transacción, las validaciones de dominio, los bloqueos `SELECT ... FOR UPDATE` y el cálculo del promedio ponderado viven en servicios de dominio que no conocen HTTP ni JSON.

Formato de nombres en PHP: clases en `PascalCase`, métodos y variables en `camelCase`, constantes en `UPPER_SNAKE_CASE`. Los servicios de inventario llevan nombres de caso de uso explícitos (por ejemplo, `ConfirmInventoryDocument`, `ReverseInventoryDocument`). Uso tipado estricto en firmas (`declare(strict_types=1)`), tipos de retorno declarados y `DECIMAL` mapeado a string o a objetos de valor, nunca a `float`, según la sección 8.3 de [inventory-domain.md](../architecture/inventory-domain.md).

## 3. Convenciones de TypeScript y Angular

Trabajo con TypeScript en modo estricto (`strict: true`), sin excepciones. Prohíbo `any` implícito: todo dato de la API viaja en interfaces tipadas que reflejan el contrato OpenAPI.

Uso componentes standalone en todo el frontend, sin `NgModule` salvo que una librería lo exija. Gestiono el estado local con Signals y derivo valores con `computed()`. Reservo los formularios reactivos (`Reactive Forms`) para los formularios complejos, como la creación de documentos con líneas, y uso `provideHttpClient` con interceptores funcionales para la autenticación y el manejo centralizado de errores, según ADR-014 de [architecture-decisions.md](../architecture/architecture-decisions.md).

Formato de nombres en Angular: componentes, servicios y modelos en inglés, con sufijos explícitos (`.component.ts`, `.service.ts`, `.model.ts`, `.guard.ts`). Los servicios de datos se nombran por recurso (`InventoryService`, `WarehouseService`, `AuthService`). Las señales que representan estado llevan nombres descriptivos de estado, no de widget.

Recuerdo la regla permanente del dominio: ningún guardia de ruta sustituye la autorización del backend. Los guardias solo mejoran la navegación.

## 4. Nomenclatura de base de datos y API

Para tablas, columnas, claves e índices aplico sin cambios las convenciones de la sección 1 de [database-design.md](../architecture/database-design.md): tablas en snake_case plural, columnas en snake_case, foráneas como `<entidad_singular>_id`, índices `idx_<tabla>_<columnas>`, únicas `uq_<tabla>_<columnas>` y foráneas `fk_<tabla>_<referencia>`.

Los valores de enumeración usan los literales canónicos en español definidos en el acta: tipos `entrada`, `salida`, `ajuste`, `transferencia`; estados `borrador`, `confirmado`, `revertido`, `descartado`. No introduzco sinónimos. La base de la API es `/api/v1` y los documentos se identifican en URL por su `uuid`, nunca por el id interno, como fija [database-design.md](../architecture/database-design.md).

Los endpoints siguen el patrón `/api/v1/<recurso>` con verbos HTTP estándar y slugs de permiso con formato `<modulo>.<recurso>.<accion>` (por ejemplo, `inventory.documents.confirm`), coherentes con ADR-004.

## 5. Mensajes de commit

Adopto Conventional Commits en inglés. Elijo el inglés por tres motivos. Primero, el código y los nombres técnicos ya están en inglés por decisión SD-06 del acta, y el commit describe el código. Segundo, las herramientas (linters de commits, generadores de changelogs, plantillas de GitHub) esperan los tipos estándar (`feat`, `fix`, `docs`, `test`, `refactor`, `chore`) sin traducir. Tercero, un historial en inglés facilita la revisión por terceros fuera del equipo. La documentación y los comentarios siguen en español; solo el commit va en inglés.

Formato: `<tipo>(<ámbito>): <descripción breve>`, con cuerpo opcional que explique el motivo. Ejemplos válidos:

```
feat(inventory): add atomic transfer confirmation service
fix(auth): reject inactive users at login
docs(development): add coding standards
test(inventory): cover C-01 overselling scenario
```

## 6. Organización de ficheros

Estructura del backend (`backend/`, Laravel 13):

```
backend/
├── app/
│   ├── Http/Controllers/Api/V1/   # Controladores delgados por recurso
│   ├── Services/Inventory/        # Casos de uso y servicios de dominio
│   ├── Models/                    # Modelos Eloquent
│   └── Policies/                  # Policies y Gates por recurso
├── database/
│   ├── migrations/                # En el orden de database-design.md §7
│   └── seeders/                   # Roles, permisos, catálogos base
├── routes/api.php                 # Rutas versionadas v1
└── tests/                         # Suite Pest (ver testing-strategy.md)
```

Estructura del frontend (`frontend/`, Angular 22):

```
frontend/
└── src/app/
    ├── core/          # Servicios singleton, interceptores, guardias
    ├── features/      # Una carpeta por funcionalidad (inventory, warehouses)
    ├── shared/        # Componentes y utilidades reutilizables
    └── models/        # Interfaces del contrato API
```

Ningún fichero supera lo necesario para una sola responsabilidad. Si un servicio de dominio crece hasta ocultar varios casos de uso, lo divido. Si un componente mezcla vista y lógica de negocio, extraigo la lógica al servicio correspondiente.

## 7. Verificación

Antes de dar por terminado un cambio, ejecuto Pint en el backend, el compilador de TypeScript en modo estricto en el frontend y la suite de pruebas afectada según [testing-strategy.md](./testing-strategy.md). Un cambio que no pasa el linter o rompe pruebas no se fusiona, sin importar su tamaño.
