# Diseño de la API REST - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento especifica el contrato de la API REST versionada del MVP: base, autenticación, convenciones, paginación, errores, idempotencia y el catálogo de endpoints por módulo. Las reglas de negocio que la API expone están en [inventory-domain.md](./inventory-domain.md), el modelo físico en [database-design.md](./database-design.md), las decisiones de plataforma en [architecture-decisions.md](./architecture-decisions.md) y el modelo de seguridad en [security-design.md](./security-design.md).

## 1. Base y versionado

- La base de la API es `/api/v1`. [DECISIÓN]
- El versionado es por URL. Un cambio incompatible exige una nueva versión mayor (`/api/v2`); los cambios compatibles (campos opcionales nuevos, nuevos endpoints) no rompen la versión vigente.
- Todas las respuestas son JSON con codificación UTF-8.
- Los documentos de inventario se identifican en la URL por su `uuid` público, nunca por el `id` interno (ver [database-design.md](./database-design.md)). Las demás entidades usan su `id` numérico.
- La especificación OpenAPI es el contrato comprobable de esta API (ADR-012 de [architecture-decisions.md](./architecture-decisions.md)): este documento la describe en lenguaje natural y la especificación OpenAPI, versionada en el repositorio, prevalece ante cualquier divergencia de detalle.

## 2. Autenticación y autorización

- Autenticación con Laravel Sanctum 4.x (ADR-003 de [architecture-decisions.md](./architecture-decisions.md)). [DECISIÓN]
- El cliente propio (SPA Angular) se autentica por cookie de sesión de estado (`statefulApi`) con protección CSRF. Las credenciales se envían solo al endpoint de login; el resto de peticiones viaja con la cookie de sesión.
- Las integraciones futuras usan tokens personales con habilidades (`abilities`, por ejemplo `inventory:write`). La SPA de primera parte no depende de tokens de API.
- La autorización real se realiza en el servidor con Policies y Gates sobre RBAC nativo más alcance por almacén (`warehouse_user`); el resultado de `tokenCan()` nunca sustituye a las Policies (ver [security-design.md](./security-design.md)).
- Sin autenticación, todo endpoint protegido responde `401`. Sin permiso suficiente (rol o alcance de almacén), responde `403`.

## 3. Convenciones de representación

- **JSON:** las peticiones con cuerpo usan `Content-Type: application/json`; las respuestas usan `application/json`.
- **Fechas y horas:** formato ISO-8601 en UTC (por ejemplo `2026-10-10T14:30:00Z`). La base de datos almacena en UTC y la presentación en `America/Bogota` es responsabilidad del cliente, según [database-design.md](./database-design.md). [DECISIÓN]
- **Números decimales:** cantidades, costos y montos se representan como **cadenas** (`"quantity": "12.500"`), nunca como flotantes JSON, para no perder precisión de los `DECIMAL` de MySQL. [DECISIÓN] La validación del formato decimal ocurre en el servidor; el cliente no debe enviar notación científica.
- **Enumeraciones:** los valores usan exactamente los literales de [database-design.md](./database-design.md): tipos `entrada`, `salida`, `ajuste`, `transferencia`; estados `borrador`, `confirmado`, `revertido`, `descartado`; motivos de ajuste `inventario_fisico`, `merma`, `robo`, `vencimiento`, `error_registro`, `donacion`, `otro`.
- **Recursos y verbos:** sustantivos en plural e inglés técnico en la ruta (`/products`, `/warehouses`); la acción la determina el verbo HTTP. Las transiciones de estado de documentos usan subrecursos de acción (`/confirm`, `/cancel`, `/reverse`).

## 4. Paginación, filtrado, orden y búsqueda

- **Paginación:** `GET` de colección acepta `page` (defecto `1`) y `per_page` (defecto `15`, máximo `100`). [DECISIÓN] La respuesta incluye el objeto `meta` con `current_page`, `per_page`, `total` y `last_page`, y el objeto `links` con `first`, `last`, `prev` y `next`.
- **Filtrado:** cada endpoint de colección documenta sus filtros permitidos como parámetros de consulta (por ejemplo `?status=confirmado&type=salida&warehouse_id=3`). Los parámetros no documentados se ignoran, nunca se interpretan como condiciones libres.
- **Ordenamiento:** parámetro `sort` con el nombre del campo, prefijo `-` para descendente (por ejemplo `?sort=-created_at`). Solo se permiten los campos listados por endpoint; cualquier otro valor produce `422`.
- **Búsqueda:** parámetro `q` para búsqueda textual en los campos documentados (por ejemplo SKU o nombre en productos). La búsqueda es insensible a mayúsculas según la colación de la base.

## 5. Formato uniforme de errores

Toda respuesta de error usa la misma envoltura, con el código HTTP correspondiente: [DECISIÓN]

```json
{
  "error": {
    "code": "STOCK_INSUFICIENTE",
    "message": "La salida supera la existencia disponible del producto.",
    "details": [
      { "field": "lines.0.quantity", "code": "STOCK_INSUFICIENTE", "message": "Disponible: 4.000, solicitado: 8.000." }
    ]
  }
}
```

| HTTP | Código | Cuándo se usa |
| --- | --- | --- |
| 401 | `NO_AUTENTICADO` | Sin sesión o token válido o ausente. |
| 403 | `SIN_PERMISO` / `FUERA_DE_ALCANCE` | Sin permiso de rol, o sin acceso al almacén solicitado (`warehouse_user`). |
| 403 | `CUENTA_INACTIVA` | Cuenta desactivada: el usuario no puede iniciar sesión ni operar. |
| 404 | `NO_ENCONTRADO` | Recurso inexistente o fuera del alcance visible del usuario. |
| 409 | `STOCK_INSUFICIENTE` | Salida, ajuste negativo o pata de origen de transferencia sin disponibilidad. |
| 409 | `ESTADO_INVALIDO` | Transición de estado no permitida (por ejemplo confirmar un documento ya confirmado). |
| 409 | `IDEMPOTENCIA_REPETIDA` | Reintento con clave ya usada cuyo resultado difiere o cuyo documento quedó descartado. |
| 400 | `SOLICITUD_INVALIDA` | Solicitud malformada o error HTTP no clasificado en otro código de esta tabla; conserva el estado real. |
| 405 | `METODO_NO_PERMITIDO` | Método HTTP no permitido para el recurso solicitado. |
| 419 | `CSRF_INVALIDO` | Token CSRF ausente o expirado; el cliente debe renovar la cookie CSRF y reintentar. |
| 422 | `VALIDACION` | Fallo de validación de parámetros, con un elemento en `details` por campo. |
| 429 | `LIMITE_EXCEDIDO` | Límite de peticiones superado (ver [security-design.md](./security-design.md)). |
| 500 | `ERROR_INTERNO` | Fallo inesperado; sin detalle técnico hacia el cliente. |

Las respuestas de validación (`422`) incluyen siempre `details` por campo; los errores de negocio (`409`) incluyen el producto o documento afectado en `details` cuando aplique.

## 6. Idempotencia

- El cliente genera y envía la cabecera `Idempotency-Key` (cadena única, por ejemplo UUID v4) en la creación de documentos (`POST /inventory-documents`) y en la confirmación (`POST /inventory-documents/{uuid}/confirm`). [DECISIÓN]
- El servidor persiste la clave en `inventory_documents.idempotency_key`, con restricción única (ver [inventory-domain.md](./inventory-domain.md) e [database-design.md](./database-design.md)).
- Ante una clave ya registrada: si el documento asociado está `confirmado`, el servidor devuelve el resultado original sin aplicar efectos de nuevo; si quedó `descartado`, responde `409` con código `IDEMPOTENCIA_REPETIDA` y no aplica nada.
- La unicidad en la base es la garantía dura frente a reintentos simultáneos con la misma clave.

## 7. Catálogo de endpoints

Salvo indicación contraria, todos los endpoints requieren autenticación. La columna «Acceso» indica el permiso RBAC exigido; el alcance por almacén (`warehouse_user`) se valida además en cada operación sobre almacenes, productos consultados por almacén y documentos.

### 7.1 Autenticación

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| POST | `/api/v1/auth/login` | Público (con límite de intentos) | `email` requerido y formato válido; `password` requerida. | Sesión creada (cookie) más objeto `user` con roles y almacenes autorizados. | 422 validación, 401 credenciales inválidas, 429 límite excedido, 403 cuenta desactivada. |
| POST | `/api/v1/auth/logout` | Autenticado | Sin cuerpo. | `204` sin contenido; sesión invalidada. | 401. |
| GET | `/api/v1/auth/me` | Autenticado | Sin parámetros. | Objeto `user` con `roles`, `permissions` y `warehouses` autorizados. | 401. |

### 7.2 Usuarios

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/users` | `users.view` | Paginación; filtros `is_active`, `role`, `warehouse_id`; búsqueda `q` (nombre, correo); `sort` por `name`, `email`, `created_at`. | Colección paginada de usuarios con roles. | 401, 403, 422 filtro u orden inválido. |
| POST | `/api/v1/users` | `users.create` | `name` requerido (máx. 150); `email` requerido, único y válido; `password` requerida con longitud mínima; `role_ids` existentes; `warehouse_ids` existentes. | `201` con el usuario creado. | 401, 403, 422, 404 rol o almacén inexistente. |
| GET | `/api/v1/users/{id}` | `users.view` | `id` numérico. | Objeto usuario con roles y almacenes. | 401, 403, 404. |
| PUT | `/api/v1/users/{id}` | `users.update` | `name`, `email` (único excepto propio), `role_ids`, `warehouse_ids`; `password` no se acepta aquí y responde 422 si llega con valor (el cambio de contraseña tendrá su propio endpoint con confirmación, RF-014). | Usuario actualizado. | 401, 403, 404, 422. |
| POST | `/api/v1/users/{id}/activate` | `users.update` | Sin cuerpo. | Usuario con `is_active` en verdadero. | 401, 403, 404, 409 si ya estaba activo. |
| POST | `/api/v1/users/{id}/deactivate` | `users.update` | Sin cuerpo; no se permite autodesactivación del último administrador. | Usuario con `is_active` en falso. | 401, 403, 404, 409 si es el último administrador. |

### 7.3 Roles y permisos

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/roles` | `roles.view` | Paginación; `sort` por `name`, `created_at`. | Colección paginada de roles con sus permisos. | 401, 403. |
| POST | `/api/v1/roles` | `roles.create` | `name` requerido; `slug` requerido y único; `permission_ids` existentes. | `201` con el rol creado. | 401, 403, 422, 404 permiso inexistente. |
| GET | `/api/v1/roles/{id}` | `roles.view` | `id` numérico. | Rol con permisos. | 401, 403, 404. |
| PUT | `/api/v1/roles/{id}` | `roles.update` | `name`, `description`, `permission_ids` existentes. | Rol actualizado. | 401, 403, 404, 422. |
| GET | `/api/v1/permissions` | `roles.view` | Filtro `module`; `sort` por `module`, `name`. | Colección paginada del catálogo de permisos. | 401, 403. |

### 7.4 Almacenes

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/warehouses` | `warehouses.view` (solo autorizados) | Paginación; filtros `is_active`, `city`; búsqueda `q` (código, nombre); `sort` por `code`, `name`, `created_at`. | Colección paginada de almacenes visibles. | 401, 403. |
| POST | `/api/v1/warehouses` | `warehouses.create` | `code` requerido y único (máx. 30); `name` requerido; `address`, `city` opcionales; `manager_user_id` existente si se envía. | `201` con el almacén creado. | 401, 403, 422. |
| GET | `/api/v1/warehouses/{id}` | `warehouses.view` + alcance | `id` numérico. | Almacén con responsable y usuarios autorizados. | 401, 403 sin permiso de almacén, 404. |
| PUT | `/api/v1/warehouses/{id}` | `warehouses.update` + alcance | `code` opcional y único (máx. 30); `name`, `address`, `city`, `manager_user_id` existente. | Almacén actualizado. | 401, 403, 404, 422. |
| POST | `/api/v1/warehouses/{id}/deactivate` | `warehouses.update` + alcance | Sin cuerpo; rechaza si tiene existencias positivas pendientes de traslado según regla de negocio. | Almacén desactivado. | 401, 403, 404, 409 con existencias. |
| POST | `/api/v1/warehouses/{id}/activate` | `warehouses.update` + alcance | Sin cuerpo. | Almacén con `is_active` en verdadero. | 401, 403, 404, 409 si ya estaba activo. |
| GET | `/api/v1/warehouses/{id}/users` | `warehouses.view` + alcance | Paginación. | Usuarios autorizados del almacén. | 401, 403, 404. |
| POST | `/api/v1/warehouses/{id}/users` | `warehouses.update` + alcance | `user_ids` existentes y activos. | Lista actualizada de autorizados. | 401, 403, 404, 422. |
| DELETE | `/api/v1/warehouses/{id}/users/{userId}` | `warehouses.update` + alcance | Ambos identificadores numéricos. | `204` sin contenido. | 401, 403, 404. |

### 7.5 Categorías

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/categories` | Autenticado | Paginación; filtros `is_active`, `parent_id`; búsqueda `q`; `sort` por `name`, `created_at`. | Colección paginada con jerarquía (`parent_id`). | 401. |
| POST | `/api/v1/categories` | `catalog.create` | `name` requerido; `parent_id` existente o nulo (sin ciclos); `slug` único si se envía. | `201` con la categoría. | 401, 403, 422. |
| GET | `/api/v1/categories/{id}` | Autenticado | `id` numérico. | Categoría con hijas y conteo de productos. | 401, 404. |

[DECISIÓN] Notas de la Unidad 1 de la Fase 2 (13/10/2026). Fuente: orden de construcción por unidades (los productos llegan en la Unidad 2).
- El detalle de la categoría expone hoy sus hijas pero **sin el conteo de productos**: no existe nada que contar hasta que la tabla `products` exista. El conteo se añade en la Unidad 2 sin romper el contrato.
- La desactivación rechaza hoy la categoría con **subcategorías activas** (409 `ESTADO_INVALIDO`); la comprobación de **productos** se añade en la Unidad 2, cuando esa tabla exista.
- Las unidades de medida **no tienen endpoints de edición ni borrado**: el contrato no los define y no se inventan. Queda como decisión pendiente si el catálogo los necesita o si las unidades son inmutables tras su creación.

[DECISIÓN] Cierre de la Unidad 2 de la Fase 2 (13/10/2026). Fuente: los dos puntos anteriores.
- El detalle de la categoría expone desde hoy `products_count` junto a sus hijas: el conteo pendiente queda implementado.
- La desactivación rechaza desde hoy la categoría con **productos activos** (409 `ESTADO_INVALIDO`), además de la guarda de subcategorías: la comprobación pendiente queda implementada.
| PUT | `/api/v1/categories/{id}` | `catalog.update` | `name`, `parent_id` sin ciclos, `is_active`. | Categoría actualizada. | 401, 403, 404, 422. |
| POST | `/api/v1/categories/{id}/deactivate` | `catalog.update` | Rechaza si tiene productos o subcategorías activas. | Categoría desactivada. | 401, 403, 404, 409 con dependientes. |

### 7.6 Unidades de medida

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/units` | Autenticado | Paginación; `sort` por `code`, `name`. | Colección paginada de unidades. | 401. |
| POST | `/api/v1/units` | `catalog.create` | `code` requerido y único (máx. 20); `name` requerido; `allows_decimals` booleano. | `201` con la unidad. | 401, 403, 422. |
| GET | `/api/v1/units/{id}` | Autenticado | `id` numérico. | Unidad solicitada. | 401, 404. |

### 7.7 Productos y configuración por almacén

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/products` | Autenticado | Paginación; filtros `is_active`, `category_id`, `warehouse_id`; búsqueda `q` (SKU, nombre, código de barras); `sort` por `sku`, `name`, `created_at`. | Colección paginada con categoría, unidad y existencias visibles. | 401, 422. |
| POST | `/api/v1/products` | `catalog.create` | `sku` requerido y único; `name` requerido; `category_id` y `unit_of_measure_id` existentes; `barcode` único si se envía; `cost_price` y `sale_price` decimales como cadena; respeta `allows_decimals` de la unidad. | `201` con el producto. | 401, 403, 422. |
| GET | `/api/v1/products/{id}` | Autenticado | `id` numérico. | Producto con existencias por almacén autorizado y configuración min/max. | 401, 404. |
| PUT | `/api/v1/products/{id}` | `catalog.update` | Igual que la creación excepto SKU inmutable o único; `is_active`. | Producto actualizado. | 401, 403, 404, 422. |
| POST | `/api/v1/products/{id}/deactivate` | `catalog.update` | Rechaza si tiene movimientos pendientes de cierre según regla de negocio; preserva el kardex. | Producto desactivado. | 401, 403, 404, 409. |
| GET | `/api/v1/products/{id}/warehouses` | Autenticado (alcance) | `id` numérico. | Configuración `product_warehouse` (`min_stock`, `max_stock`, `reorder_point` como cadenas). | 401, 404. |
| PUT | `/api/v1/products/{id}/warehouses/{warehouseId}` | `catalog.update` + alcance | `min_stock` decimal como cadena (mínimo 0); `max_stock` y `reorder_point` decimales o nulos, con `max_stock >= min_stock` si ambos se envían. | Configuración actualizada. | 401, 403 sin permiso de almacén, 404, 422. |

[DECISIÓN] Notas de la Unidad 2 de la Fase 2 (13/10/2026). Fuente: orden de construcción por fases (el kardex llega en la Fase 3).
- La ficha y el listado exponen hoy categoría, unidad y configuración min/max por almacén autorizado, pero **sin cantidades ni costo promedio**: la tabla `inventories` no existe hasta la fase de inventario y no se inventan ceros. Esos campos se añaden cuando el kardex exista, sin romper el contrato.
- La desactivación permite hoy el apagado porque la tabla de movimientos no existe y no hay nada pendiente que detectar: la comprobación de "movimientos pendientes de cierre" se añade cuando esos documentos existan.
- El SKU es inmutable en el PUT: cualquier intento de cambio responde 422, aunque el valor nuevo sea único (lectura conservadora del contrato).
- El PUT de configuración responde 200 tanto al crear la fila como al actualizarla, y los negativos de costo, precio y cantidades se rechazan con 422.

### 7.8 Documentos de inventario

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/inventory-documents` | `inventory.view` (alcance) | Paginación; filtros `type`, `status`, `origin_warehouse_id`, `destination_warehouse_id`, `from`, `to`; `sort` por `created_at`, `doc_number`. | Colección paginada de documentos visibles con líneas resumidas. | 401, 403, 422. |
| GET | `/api/v1/inventory-documents/{uuid}` | `inventory.view` + alcance | `uuid` público. | Documento con líneas, almacenes, estado y auditoría básica. | 401, 403, 404. |
| POST | `/api/v1/inventory-documents` | `inventory.create` + alcance | Cabecera `Idempotency-Key` requerida; `type` válido; `origin_warehouse_id` requerido y activo; `destination_warehouse_id` requerido y distinto solo en transferencias; `reason_code` obligatorio en ajustes; `lines[].product_id` existente y activo; `lines[].quantity` decimal positivo como cadena; `unit_cost` como cadena; `lines[].direction` (`entrada`|`salida`), solo en ajustes (por defecto `entrada`; se rechaza en el resto de tipos); al menos una línea. Crea en `borrador` (o confirma de inmediato si `confirm: true`, por el mismo servicio transaccional). | `201` con el documento en `borrador` (o `confirmado` si se pidió confirmación inmediata). | 401, 403 sin permiso de almacén, 422, 404 producto o almacén inexistente, 409 idempotencia. |
| PUT | `/api/v1/inventory-documents/{uuid}` | `inventory.create` + alcance | Solo desde `borrador` (RF-053): reemplaza cabecera y líneas en una sola transacción. Mismas validaciones de líneas que la creación. `uuid`, `idempotency_key` y `created_by` son inmutables; `doc_number` se regenera si cambia el tipo para que el prefijo siga siendo coherente. | Documento en `borrador` actualizado. | 401, 403 sin permiso de almacén, 404, 409 estado inválido, 422. |
| POST | `/api/v1/inventory-documents/{uuid}/confirm` | `inventory.confirm` + alcance | Cabecera `Idempotency-Key` requerida; el documento debe estar en `borrador`. Operación atómica e idempotente (ver [inventory-domain.md](./inventory-domain.md)). | Documento `confirmado` con movimientos generados. | 401, 403, 404, 409 stock insuficiente, 409 estado inválido, 409 idempotencia, 422. |
| POST | `/api/v1/inventory-documents/{uuid}/cancel` | `inventory.create` + alcance | Solo desde `borrador`; pasa a `descartado` sin efectos. | Documento `descartado`. | 401, 403, 404, 409 estado inválido. |
| POST | `/api/v1/inventory-documents/{uuid}/reverse` | `inventory.reverse` + alcance | Solo desde `confirmado`; `reason` requerido; genera movimientos compensatorios sin borrar el original. | Documento original `revertido` más referencia al compensatorio. | 401, 403, 404, 409 estado inválido o destino ya consumido, 422 motivo ausente. |

### 7.9 Kardex

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/inventory/kardex` | `inventory.view` + alcance | `product_id` y `warehouse_id` requeridos y autorizados; `from` y `to` ISO-8601 opcionales; paginación. | Movimientos ordenados con `quantity_delta`, `balance_after` y `unit_cost` como cadenas, más saldo inicial y final. | 401, 403 sin permiso de almacén, 404 producto o almacén inexistente, 422 rango inválido. |

### 7.10 Tablero

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/dashboard/summary` | Autenticado (alcance) | `warehouse_id` opcional para acotar a un almacén autorizado. | Totales de productos activos, existencias por almacén, productos bajo mínimo, movimientos recientes y valorización. | 401, 403 almacén no autorizado. |

### 7.11 Reportes y exportación

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/reports/stock` | `reports.view` + alcance | Filtros `warehouse_id`, `category_id`, `below_min` booleano; paginación. | Existencias con `quantity` y `average_cost` como cadenas. | 401, 403, 422. |
| GET | `/api/v1/reports/movements` | `reports.view` + alcance | Filtros `warehouse_id`, `product_id`, `type`, `from`, `to`; paginación. | Historial de movimientos con documento de origen. | 401, 403, 422. |
| GET | `/api/v1/reports/valuation` | `reports.view` + alcance | Filtros `warehouse_id`, `category_id`; valorización por promedio ponderado. | Valor por producto y almacén y total, montos como cadenas. | 401, 403, 422. |
| POST | `/api/v1/reports/exports` | `reports.export` + alcance | `report` en (`stock`, `movements`, `valuation`); `format` en (`pdf`, `excel`); mismos filtros del reporte; trabajo asíncrono con colas de base de datos si el volumen lo justifica. | `202` con identificador del trabajo y estado (`pending`, `done`, URL de descarga). | 401, 403, 422 formato o reporte inválido. |

### 7.12 Auditoría

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/audit-logs` | `audit.view` | Paginación; filtros `user_id`, `action`, `entity_type`, `from`, `to`; `sort` por `created_at`. Solo lectura; sin secretos (ver [security-design.md](./security-design.md)). | Colección paginada de entradas. | 401, 403, 422. |
| GET | `/api/v1/audit-logs/{id}` | `audit.view` | `id` numérico. | Entrada con `old_values` y `new_values` sin datos sensibles. | 401, 403, 404. |

### 7.13 Importación

Todos los endpoints requieren autenticación. La columna «Acceso» indica el permiso RBAC exigido; el alcance por almacén (`warehouse_user`) se valida además en cada operación de carga inicial.

| Método | Ruta | Acceso | Parámetros y validaciones | Respuesta | Errores |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/imports/catalog/template` | `catalog.create` | Parámetro `format` en (`xlsx`, `csv`). | Descarga de la plantilla de catálogo con las columnas esperadas y una fila de ejemplo. | 401, 403, 422 formato inválido. |
| POST | `/api/v1/imports/catalog/validate` | `catalog.create` | Cuerpo `multipart` con el archivo; validación en el servidor de tipo, tamaño, codificación y número de filas; dry-run con informe por fila sin modificar datos. | Informe por fila con campo y motivo de cada fallo. | 401, 403, 422 VALIDACION, 413 si se define límite de tamaño. |
| POST | `/api/v1/imports/catalog` | `catalog.create` | Cuerpo `multipart` con el archivo ya validado; aplicación atómica e idempotente por SKU en una sola transacción. | `201` con el resumen de filas creadas y actualizadas más la referencia de auditoría. | 401, 403, 422 VALIDACION, 409 IDEMPOTENCIA_REPETIDA, 413 si se define límite de tamaño. |
| GET | `/api/v1/imports/initial-stock/template` | `inventory.create` | Sin parámetros. | Descarga de la plantilla de carga inicial (SKU, almacén, cantidad y costo). | 401, 403. |
| POST | `/api/v1/imports/initial-stock/validate` | `inventory.create` + alcance | Cuerpo `multipart` con el archivo; dry-run con alcance por almacén: rechaza producto inexistente, almacén fuera de alcance, cantidad inválida y decimales no permitidos. | Informe por fila con campo y motivo de cada fallo. | 401, 403 SIN_PERMISO, 403 FUERA_DE_ALCANCE, 422 VALIDACION, 413 si se define límite de tamaño. |
| POST | `/api/v1/imports/initial-stock` | `inventory.create` + alcance | Cuerpo `multipart` con el archivo; cabecera `Idempotency-Key` requerida; genera y confirma el documento de entrada con recálculo de promedio ponderado. | `201` con el documento de entrada confirmado y su resumen de líneas. | 401, 403 SIN_PERMISO, 403 FUERA_DE_ALCANCE, 422 VALIDACION, 409 IDEMPOTENCIA_REPETIDA, 413 si se define límite de tamaño. |

Los errores usan la envoltura de §5: `422 VALIDACION` para archivo o filas inválidas (se usa `413` solo si el límite de tamaño se define como tal), `403 SIN_PERMISO` sin el permiso correspondiente, `403 FUERA_DE_ALCANCE` para almacén fuera de alcance y `409 IDEMPOTENCIA_REPETIDA` ante reenvío con clave ya usada.

[DECISIÓN] Cierre de la Unidad 3 de la Fase 2 (13/10/2026). Fuente: EP-012 y orden de construcción por unidades (la carga inicial de existencias llega en la Fase 3). Fija los números que el contrato pedía sin darlos y dos desvíos conscientes:
- Plantilla: columnas exactas en este orden - `sku, nombre, categoria, unidad, descripcion, codigo_barras, costo, precio_venta, activo` - con la fila de ejemplo `EXAMPLE-001` marcada como ilustrativa (no debe importarse). La plantilla cubre productos más la categoría y la unidad que referencian; el stock mínimo, máximo y punto de reorden son por almacén y NO forman parte de esta plantilla (los posee la carga inicial de la Fase 3).
- Límites: formatos `xlsx` y `csv`; tamaño máximo 5 MB; máximo 1000 filas; CSV en UTF-8 decodificable. Lo que exceda o llegue en otro tipo se rechaza con `422 VALIDACION` y sin efectos; nunca se inventa un `413`.
- Columnas obligatorias: `sku, nombre, categoria, unidad`; el resto son opcionales. Cada error informa fila, campo y motivo en español sin detenerse en la primera fila mala.
- Por fila: `sku` obligatorio y único dentro del archivo (el SKU existente es actualización, no error); `nombre` obligatorio; `unidad` debe ser el código de una unidad existente - el código desconocido es fila inválida y la importación NUNCA crea unidades (una errata crearía silenciosamente una unidad de medida y corrompería el catálogo, desvío consciente de RF-128 con su motivo); `categoria` se resuelve por nombre y se crea como raíz si no existe (el archivo no trae jerarquía); `codigo_barras` único cuando viene informado; `costo` y `precio_venta` como cadenas decimales de hasta 2 decimales y no negativos; `activo` acepta valores de verdad en español o inglés (`si, sí, yes, verdadero, activo` y sus negaciones, más `true/false/1/0`) y la celda vacía equivale a verdadero.
- Dry-run (`validate`): responde `200` con el informe por fila y no escribe nada (los conteos antes y después son idénticos).
- Aplicación (`store`): una sola transacción (si alguna fila falla, no se aplica ninguna y responde `422` con el informe); idempotente por SKU (reimportar actualiza con las columnas del archivo en lugar de duplicar; el SKU es la clave y jamás se modifica); responde `201` con filas creadas, actualizadas, totales y la referencia de auditoría.
- Auditoría: una entrada por importación con acción `importar_catalogo`, `entity_type` `importacion_catalogo`, `entity_id` nulo y `new_values` con el nombre del archivo y los conteos. El archivo subido NO se guarda en el servidor (el contrato no define staging): solo se lee su contenido temporal y la auditoría conserva el nombre y los conteos, nunca el contenido, las filas ni secretos.
- Validación compartida (RN-IMP-02): las reglas viven una sola vez en `App\Services\Catalog\CatalogRules` (más las guardas de `ProductService`); los Form Request de captura manual delegan en ella y cada fila del archivo se valida contra las mismas reglas, así que la importación nunca relaja el formulario.
- Dependencia mínima: `phpoffice/phpspreadsheet` para leer y generar XLSX (el CSV se maneja con funciones nativas de PHP, sin dependencia extra); la imagen del backend añade la extensión `gd` porque la librería la exige como dependencia de plataforma.

## 8. Ejemplo de confirmación de documento

Confirmación idempotente de una salida en estado `borrador`:

```http
POST /api/v1/inventory-documents/9f2c1e4a-3b5d-4c6e-8f7a-1a2b3c4d5e6f/confirm HTTP/1.1
Host: stockflow.local
Content-Type: application/json
Idempotency-Key: 7d3f9a2b-1c4e-4a5f-9b6d-2e8f0a1b3c4d
Cookie: stockflow_session=...
```

Respuesta `200`:

```json
{
  "data": {
    "uuid": "9f2c1e4a-3b5d-4c6e-8f7a-1a2b3c4d5e6f",
    "doc_number": "SAL-2026-000123",
    "type": "salida",
    "status": "confirmado",
    "origin_warehouse_id": 3,
    "destination_warehouse_id": null,
    "confirmed_at": "2026-10-10T19:30:00Z",
    "lines": [
      { "product_id": 42, "quantity": "8.000", "unit_cost": "15.250000", "line_total": "122.00" }
    ],
    "movements": [
      { "product_id": 42, "warehouse_id": 3, "direction": "salida", "quantity_delta": "-8.000", "balance_after": "2.000" }
    ]
  }
}
```

Si la existencia disponible fuese de `4.000`, la misma petición respondería `409` con código `STOCK_INSUFICIENTE` y ningún efecto parcial, según [inventory-domain.md](./inventory-domain.md).

## 9. Relación con la documentación vecina

- Las reglas de estados, transiciones, concurrencia e idempotencia que esta API expone se definen en [inventory-domain.md](./inventory-domain.md).
- Los tipos, restricciones únicas e índices que respaldan las validaciones están en [database-design.md](./database-design.md).
- Las decisiones de Sanctum, RBAC nativo, OpenAPI y colas de base de datos están en [architecture-decisions.md](./architecture-decisions.md).
- Las amenazas, controles y límites que condicionan cada endpoint están en [security-design.md](./security-design.md).
