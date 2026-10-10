# Diseño de base de datos - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento define el modelo físico de datos del MVP sobre MySQL 8.4 LTS con InnoDB: convenciones, tipos, enumeraciones, tablas, claves, índices y el orden de migraciones. Las reglas de negocio del inventario están en [inventory-domain.md](./inventory-domain.md) y el diagrama en [er-diagram.md](./er-diagram.md).

## 1. Convenciones generales

- **Motor:** InnoDB en todas las tablas (transacciones ACID y bloqueo a nivel de fila).
- **Conjunto de caracteres:** `utf8mb4` con colación `utf8mb4_0900_ai_ci`.
- **Zona horaria:** se almacena en UTC; la aplicación presenta en America/Bogota.
- **Nombres:** tablas en snake_case plural; columnas en snake_case; claves foráneas `<entidad_singular>_id`; índices `idx_<tabla>_<columnas>`; claves únicas `uq_<tabla>_<columnas>`; claves foráneas `fk_<tabla>_<referencia>`.
- **Clave primaria:** `BIGINT UNSIGNED AUTO_INCREMENT` en todas las tablas internas (clustered index de InnoDB, eficiente en joins).
- **Identificador público:** las tablas expuestas en la API añaden una columna pública estable además del id interno: `inventory_documents.uuid` (`CHAR(36)`, único). El id interno nunca se usa como identificador en URL de documentos.
- **Marcas de tiempo:** cada tabla lleva `created_at` y `updated_at`; los documentos añaden `confirmed_at` y `reversed_at`.
- **Dinero y cantidades:** siempre `DECIMAL`; nunca tipos de punto flotante.

## 2. Tipos numéricos

| Uso | Tipo | Motivo |
| --- | --- | --- |
| Cantidades (existencia, movimiento, línea, min/max) | `DECIMAL(15,3)` | Soporta unidades discretas y fraccionarias (peso, volumen) sin perder exactitud. |
| Costo unitario promedio | `DECIMAL(18,6)` | Conserva precisión en la división del promedio ponderado. |
| Costo/venta unitario del producto | `DECIMAL(18,2)` | Valores de referencia monetarios. |
| Costo unitario de línea y totales | `DECIMAL(18,4)` costo, `DECIMAL(18,2)` total | Precisión de cálculo interna y presentación a dos decimales. |
| Saldo resultante tras movimiento | `DECIMAL(15,3)` | Igual a la cantidad, para reconstruir el kardex. |
| Porcentajes o cantidades de reserva | `DECIMAL(15,3)` | Consistente con cantidades. |

El redondeo a dos decimales se aplica al calcular totales de línea y de documento; el costo unitario conserva la precisión interna.

## 3. Enumeraciones

Se implementan como columnas `VARCHAR` con valor controlado y restricción `CHECK` (defensa en profundidad), además de validación en el servicio.

| Enumeración | Valores | Columna |
| --- | --- | --- |
| Tipo de documento | `entrada`, `salida`, `ajuste`, `transferencia` | `inventory_documents.type` |
| Estado de documento | `borrador`, `confirmado`, `revertido`, `descartado` | `inventory_documents.status` |
| Dirección de movimiento | `entrada`, `salida` | `inventory_movements.direction` |
| Motivo de ajuste | `inventario_fisico`, `merma`, `robo`, `vencimiento`, `error_registro`, `donacion`, `otro` | `inventory_documents.reason_code` |
| Método de costeo | `promedio_ponderado` (MVP), `fifo` (reservado) | `products.costing_method` |

## 4. Estrategia de eliminación y desactivación

| Tabla | Estrategia | Motivo |
| --- | --- | --- |
| `users` | Desactivación (`is_active = 0`) | Nunca se borra un usuario referenciado por movimientos o auditoría. |
| `warehouses` | Desactivación (`is_active`) + borrado lógico (`deleted_at`) | Preserva el kardex de ese almacén. |
| `products` | Desactivación (`is_active`) + borrado lógico (`deleted_at`) | Preserva el historial y la valorización. |
| `categories` | Desactivación (`is_active`) | No se borra si tiene productos o subcategorías. |
| `roles`, `permissions` | Sin borrado si están asignados | Se editan, no se eliminan. |
| `inventory_documents`, `inventory_document_lines`, `inventory_movements` | Sin borrado nunca | Libro contable inmutable. |
| `audit_logs` | Sin borrado nunca | Evidencia de auditoría. |

Claves foráneas con `ON DELETE RESTRICT` para preservar historia; `ON DELETE CASCADE` solo en las tablas pivote (`role_user`, `permission_role`, `warehouse_user`).

## 5. Tablas

### 5.1 Seguridad y acceso

**`users`**

| Columna | Tipo | Nulo | Clave / restricción | Nota |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | PK | |
| name | VARCHAR(150) | No | | Nombre completo. |
| email | VARCHAR(191) | No | UNIQUE | Identificador de acceso. |
| password | VARCHAR(255) | No | | Hash (bcrypt/argon2). |
| is_active | BOOLEAN | No | default 1 | Desactivación lógica. |
| email_verified_at | TIMESTAMP | Sí | | |
| last_login_at | TIMESTAMP | Sí | | |
| created_at, updated_at | TIMESTAMP | No | | |

**`roles`** - `id` PK; `name` VARCHAR(80) no nulo; `slug` VARCHAR(80) UNIQUE; `description` VARCHAR(255) nulo; timestamps.

**`permissions`** - `id` PK; `name` VARCHAR(120) no nulo; `slug` VARCHAR(120) UNIQUE (p. ej. `inventory.documents.confirm`); `module` VARCHAR(60); timestamps.

**`role_user`** - `role_id` FK -> roles (CASCADE); `user_id` FK -> users (CASCADE); PK compuesta `(role_id, user_id)`.

**`permission_role`** - `permission_id` FK -> permissions (CASCADE); `role_id` FK -> roles (CASCADE); PK compuesta `(permission_id, role_id)`.

**`warehouses`**

| Columna | Tipo | Nulo | Clave / restricción | Nota |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | PK | |
| code | VARCHAR(30) | No | UNIQUE `uq_warehouses_code` | Código único. |
| name | VARCHAR(150) | No | | |
| address | VARCHAR(255) | Sí | | |
| city | VARCHAR(100) | Sí | | |
| manager_user_id | BIGINT UNSIGNED | Sí | FK -> users (RESTRICT) | Responsable. |
| is_active | BOOLEAN | No | default 1 | |
| created_at, updated_at | TIMESTAMP | No | | |
| deleted_at | TIMESTAMP | Sí | | Borrado lógico. |

**`warehouse_user`** - `user_id` FK -> users (CASCADE); `warehouse_id` FK -> warehouses (CASCADE); PK compuesta `(user_id, warehouse_id)`. Define el alcance por almacén.

### 5.2 Catálogo

**`categories`** - `id` PK; `parent_id` BIGINT UNSIGNED nulo FK -> categories (RESTRICT); `name` VARCHAR(150) no nulo; `slug` VARCHAR(180); `is_active` BOOLEAN default 1; timestamps. Índice `idx_categories_parent_id`.

**`units_of_measure`** - `id` PK; `code` VARCHAR(20) UNIQUE (p. ej. `UND`, `KG`, `LT`); `name` VARCHAR(80) no nulo; `allows_decimals` BOOLEAN default 0; timestamps.

**`products`**

| Columna | Tipo | Nulo | Clave / restricción | Nota |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | PK | |
| sku | VARCHAR(60) | No | UNIQUE `uq_products_sku` | Identificador único. |
| name | VARCHAR(180) | No | | |
| description | TEXT | Sí | | |
| barcode | VARCHAR(80) | Sí | UNIQUE `uq_products_barcode` (solo si no nulo) | Opcional. |
| category_id | BIGINT UNSIGNED | No | FK -> categories (RESTRICT) | |
| unit_of_measure_id | BIGINT UNSIGNED | No | FK -> units_of_measure (RESTRICT) | |
| is_active | BOOLEAN | No | default 1 | |
| cost_price | DECIMAL(18,2) | No | default 0 | Costo de referencia. |
| sale_price | DECIMAL(18,2) | No | default 0 | Precio de venta. |
| costing_method | VARCHAR(30) | No | default `promedio_ponderado`, CHECK | Método de costeo. |
| created_at, updated_at | TIMESTAMP | No | | |
| deleted_at | TIMESTAMP | Sí | | Borrado lógico. |

Índices: `idx_products_category_id`, `idx_products_is_active`.

**`product_warehouse`** - `id` PK; `product_id` FK -> products (CASCADE); `warehouse_id` FK -> warehouses (CASCADE); `min_stock` DECIMAL(15,3) default 0; `max_stock` DECIMAL(15,3) nulo; `reorder_point` DECIMAL(15,3) nulo; UNIQUE `uq_product_warehouse (product_id, warehouse_id)`. Define stock mínimo y máximo por almacén.

### 5.3 Inventario

**`inventories`** (saldo materializado, la fila que se bloquea)

| Columna | Tipo | Nulo | Clave / restricción | Nota |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | PK | |
| product_id | BIGINT UNSIGNED | No | FK -> products (RESTRICT) | |
| warehouse_id | BIGINT UNSIGNED | No | FK -> warehouses (RESTRICT) | |
| quantity | DECIMAL(15,3) | No | CHECK `quantity >= 0` | Existencia actual. |
| reserved_quantity | DECIMAL(15,3) | No | default 0 | Reservado para fases futuras. |
| average_cost | DECIMAL(18,6) | No | default 0 | Costo promedio ponderado. |
| last_movement_at | TIMESTAMP | Sí | | |
| created_at, updated_at | TIMESTAMP | No | | |

Restricción única `uq_inventories_product_warehouse (product_id, warehouse_id)`: garantiza una sola fila por par. Índice `idx_inventories_warehouse_id`.

**`inventory_documents`**

| Columna | Tipo | Nulo | Clave / restricción | Nota |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | PK | |
| uuid | CHAR(36) | No | UNIQUE `uq_inventory_documents_uuid` | Identificador público. |
| doc_number | VARCHAR(40) | No | UNIQUE `uq_inventory_documents_doc_number` | Consecutivo legible. |
| type | VARCHAR(20) | No | CHECK (`entrada`,`salida`,`ajuste`,`transferencia`) | |
| status | VARCHAR(20) | No | default `borrador`, CHECK (`borrador`,`confirmado`,`revertido`,`descartado`) | |
| origin_warehouse_id | BIGINT UNSIGNED | No | FK -> warehouses (RESTRICT) | Almacén de origen o destino único. |
| destination_warehouse_id | BIGINT UNSIGNED | Sí | FK -> warehouses (RESTRICT) | Solo transferencias. |
| idempotency_key | VARCHAR(80) | No | UNIQUE `uq_inventory_documents_idempotency_key` | Antiduplicados. |
| reference | VARCHAR(120) | Sí | | Documento externo (factura, guía). |
| notes | TEXT | Sí | | |
| reason_code | VARCHAR(30) | Sí | CHECK | Obligatorio en ajustes. |
| created_by | BIGINT UNSIGNED | No | FK -> users (RESTRICT) | |
| confirmed_by | BIGINT UNSIGNED | Sí | FK -> users (RESTRICT) | |
| confirmed_at | TIMESTAMP | Sí | | |
| reversed_by_id | BIGINT UNSIGNED | Sí | FK -> inventory_documents (RESTRICT) | Documento de reversión. |
| created_at, updated_at | TIMESTAMP | No | | |

Índices: `idx_inventory_documents_origin_warehouse (origin_warehouse_id)`, `idx_inventory_documents_destination_warehouse (destination_warehouse_id)`, `idx_inventory_documents_status (status)`, `idx_inventory_documents_type_created (type, created_at)`.

**`inventory_document_lines`**

| Columna | Tipo | Nulo | Clave / restricción | Nota |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | PK | |
| document_id | BIGINT UNSIGNED | No | FK -> inventory_documents (CASCADE, mismo agregado) | |
| product_id | BIGINT UNSIGNED | No | FK -> products (RESTRICT) | |
| quantity | DECIMAL(15,3) | No | CHECK `quantity > 0` | Siempre positiva. |
| unit_cost | DECIMAL(18,6) | No | default 0 | Costo capturado o vigente. |
| line_total | DECIMAL(18,2) | No | default 0 | `quantity * unit_cost` redondeado. |

Índices: `idx_inventory_document_lines_document (document_id)`, `idx_inventory_document_lines_product (product_id)`.

**`inventory_movements`** (libro inmutable, append-only)

| Columna | Tipo | Nulo | Clave / restricción | Nota |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | PK | |
| document_id | BIGINT UNSIGNED | No | FK -> inventory_documents (RESTRICT) | |
| document_line_id | BIGINT UNSIGNED | No | FK -> inventory_document_lines (RESTRICT) | |
| product_id | BIGINT UNSIGNED | No | FK -> products (RESTRICT) | |
| warehouse_id | BIGINT UNSIGNED | No | FK -> warehouses (RESTRICT) | |
| direction | VARCHAR(10) | No | CHECK (`entrada`,`salida`) | |
| quantity_delta | DECIMAL(15,3) | No | | Positivo o negativo. |
| unit_cost | DECIMAL(18,6) | No | | Costo aplicado. |
| balance_after | DECIMAL(15,3) | No | | Saldo resultante (kardex). |
| created_at | TIMESTAMP | No | | Sin `updated_at`: es inmutable. |
| created_by | BIGINT UNSIGNED | No | FK -> users (RESTRICT) | |

Índices: `idx_inventory_movements_kardex (product_id, warehouse_id, created_at)`, `idx_inventory_movements_document (document_id)`, `idx_inventory_movements_warehouse_date (warehouse_id, created_at)`.

### 5.4 Auditoría

**`audit_logs`** - `id` PK; `user_id` BIGINT UNSIGNED nulo FK -> users (RESTRICT; nulo para acciones del sistema); `action` VARCHAR(60) (p. ej. `confirmar_documento`); `entity_type` VARCHAR(80); `entity_id` BIGINT UNSIGNED nulo; `old_values` JSON nulo; `new_values` JSON nulo; `ip_address` VARCHAR(45) nulo; `user_agent` VARCHAR(255) nulo; `created_at`. Nunca almacena contraseñas, tokens ni secretos. Índices: `idx_audit_logs_user (user_id)`, `idx_audit_logs_entity (entity_type, entity_id)`, `idx_audit_logs_created (created_at)`.

## 6. Restricciones garantizadas por MySQL frente a reglas del servicio

| Regla | Dónde se garantiza | Mecanismo |
| --- | --- | --- |
| Unicidad de SKU, código de almacén, uuid, doc_number, idempotency_key | MySQL | Restricción `UNIQUE`. |
| Una fila de saldo por producto y almacén | MySQL | `UNIQUE (product_id, warehouse_id)`. |
| Cantidad de línea positiva | MySQL | `CHECK (quantity > 0)`. |
| Existencia no negativa | MySQL (última red) + servicio | `CHECK (quantity >= 0)`; el servicio valida antes. |
| Tipos, estados y motivos válidos | MySQL (CHECK) + servicio | `CHECK` en las columnas enumeradas. |
| Integridad referencial | MySQL | Claves foráneas. |
| Origen distinto de destino en transferencia | Servicio (+ CHECK opcional) | Validación en el caso de uso; `CHECK` puede añadirse. |
| Disponibilidad suficiente (sin sobreventa) | Servicio, dentro de la transacción | Bloqueo `SELECT ... FOR UPDATE` + comparación. |
| Atomicidad de la transferencia | Servicio | Transacción única con rollback total. |
| Coherencia kardex-saldo (invariante I-02) | Servicio | Movimientos y saldo en la misma transacción. |
| Transiciones de estado | Servicio | Máquina de estados del caso de uso. |
| Idempotencia efectiva | MySQL (unique) + servicio | Clave única + manejo del conflicto. |
| Cálculo del promedio ponderado | Servicio | Fórmula en el caso de uso. |

## 7. Orden de migraciones

1. `users` (base de Laravel), `password_reset_tokens` si se usa recuperación.
2. `roles`, `permissions`, `permission_role`, `role_user`.
3. `warehouses`, `warehouse_user`.
4. `categories`, `units_of_measure`, `products`, `product_warehouse`.
5. `inventories`.
6. `inventory_documents`, `inventory_document_lines`, `inventory_movements`.
7. `audit_logs`.

Semillas (`seeders`): roles base (Administrador, Jefe de almacén, Operador, Auditor), catálogo de permisos por módulo, unidades de medida base, categorías iniciales, y un usuario administrador de desarrollo (credenciales por variable de entorno, nunca fijas en el código).

## 8. Qué NO guarda la base de datos

- Contraseñas en texto plano (solo hash).
- Tokens, secretos o claves de API.
- Datos de tarjetas o información financiera.
- Registros eliminados del kardex o de auditoría.

## 9. Invariantes y su verificación

Las invariantes I-01 a I-07 de [inventory-domain.md](./inventory-domain.md) se comprueban con consultas de verificación en las pruebas de integración, por ejemplo:

```sql
-- I-02: el saldo materializado debe igualar la suma del kardex.
SELECT i.product_id, i.warehouse_id, i.quantity,
       COALESCE(SUM(m.quantity_delta), 0) AS kardex_total
FROM inventories i
LEFT JOIN inventory_movements m
  ON m.product_id = i.product_id AND m.warehouse_id = i.warehouse_id
GROUP BY i.product_id, i.warehouse_id, i.quantity
HAVING i.quantity <> COALESCE(SUM(m.quantity_delta), 0);
```

Si esta consulta devuelve filas, existe deriva entre el kardex y las existencias, y la prueba falla.
