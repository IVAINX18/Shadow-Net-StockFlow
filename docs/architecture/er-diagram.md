# Diagrama entidad-relación - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento presenta el modelo entidad-relación del MVP en Mermaid, con sus cardinalidades y una explicación breve de cada relación. El detalle de columnas, tipos, restricciones e índices vive en [database-design.md](./database-design.md). Las reglas del núcleo de inventario están en [inventory-domain.md](./inventory-domain.md).

## 1. Diagrama entidad-relación

```mermaid
erDiagram
    USERS ||--o{ ROLE_USER : "posee"
    ROLES ||--o{ ROLE_USER : "asignado a"
    ROLES ||--o{ PERMISSION_ROLE : "concede"
    PERMISSIONS ||--o{ PERMISSION_ROLE : "incluido en"
    USERS ||--o{ WAREHOUSE_USER : "accede a"
    WAREHOUSES ||--o{ WAREHOUSE_USER : "autoriza a"
    USERS ||--o{ AUDIT_LOGS : "genera"

    CATEGORIES ||--o{ CATEGORIES : "padre de"
    CATEGORIES ||--o{ PRODUCTS : "clasifica"
    UNITS_OF_MEASURE ||--o{ PRODUCTS : "mide"
    PRODUCTS ||--o{ PRODUCT_WAREHOUSE : "se configura en"
    WAREHOUSES ||--o{ PRODUCT_WAREHOUSE : "configura"
    PRODUCTS ||--o{ INVENTORIES : "tiene saldo en"
    WAREHOUSES ||--o{ INVENTORIES : "almacena"

    WAREHOUSES ||--o{ INVENTORY_DOCUMENTS : "es origen de"
    USERS ||--o{ INVENTORY_DOCUMENTS : "crea"
    INVENTORY_DOCUMENTS ||--o{ INVENTORY_DOCUMENT_LINES : "contiene"
    PRODUCTS ||--o{ INVENTORY_DOCUMENT_LINES : "referencia"
    INVENTORY_DOCUMENTS ||--o{ INVENTORY_MOVEMENTS : "genera"
    INVENTORY_DOCUMENT_LINES ||--o{ INVENTORY_MOVEMENTS : "origina"
    PRODUCTS ||--o{ INVENTORY_MOVEMENTS : "afecta"
    WAREHOUSES ||--o{ INVENTORY_MOVEMENTS : "registra en"

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        boolean is_active
        timestamp email_verified_at
        timestamp last_login_at
        timestamps created_at_updated_at
    }

    ROLES {
        bigint id PK
        string name
        string slug UK
        string description
    }

    PERMISSIONS {
        bigint id PK
        string name
        string slug UK
        string module
    }

    ROLE_USER {
        bigint role_id FK
        bigint user_id FK
    }

    PERMISSION_ROLE {
        bigint permission_id FK
        bigint role_id FK
    }

    WAREHOUSE_USER {
        bigint user_id FK
        bigint warehouse_id FK
    }

    WAREHOUSES {
        bigint id PK
        string code UK
        string name
        string address
        string city
        bigint manager_user_id FK
        boolean is_active
        timestamps created_at_updated_at
        timestamp deleted_at
    }

    CATEGORIES {
        bigint id PK
        bigint parent_id FK
        string name
        string slug
        boolean is_active
    }

    UNITS_OF_MEASURE {
        bigint id PK
        string code UK
        string name
        boolean allows_decimals
    }

    PRODUCTS {
        bigint id PK
        string sku UK
        string name
        text description
        string barcode UK
        bigint category_id FK
        bigint unit_of_measure_id FK
        boolean is_active
        decimal cost_price
        decimal sale_price
        string costing_method
        timestamps created_at_updated_at
        timestamp deleted_at
    }

    PRODUCT_WAREHOUSE {
        bigint id PK
        bigint product_id FK
        bigint warehouse_id FK
        decimal min_stock
        decimal max_stock
        decimal reorder_point
    }

    INVENTORIES {
        bigint id PK
        bigint product_id FK
        bigint warehouse_id FK
        decimal quantity
        decimal reserved_quantity
        decimal average_cost
        timestamp last_movement_at
    }

    INVENTORY_DOCUMENTS {
        bigint id PK
        string uuid UK
        string doc_number UK
        string type
        string status
        bigint origin_warehouse_id FK
        bigint destination_warehouse_id FK
        string idempotency_key UK
        string reference
        text notes
        string reason_code
        bigint created_by FK
        bigint confirmed_by FK
        timestamp confirmed_at
        bigint reversed_by_id FK
        timestamps created_at_updated_at
    }

    INVENTORY_DOCUMENT_LINES {
        bigint id PK
        bigint document_id FK
        bigint product_id FK
        decimal quantity
        decimal unit_cost
        decimal line_total
    }

    INVENTORY_MOVEMENTS {
        bigint id PK
        bigint document_id FK
        bigint document_line_id FK
        bigint product_id FK
        bigint warehouse_id FK
        string direction
        decimal quantity_delta
        decimal unit_cost
        decimal balance_after
        timestamp created_at
        bigint created_by FK
    }

    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK
        string action
        string entity_type
        bigint entity_id
        json old_values
        json new_values
        string ip_address
        string user_agent
        timestamp created_at
    }
```

## 2. Cardinalidades

| Relación | Cardinalidad | Descripción |
| --- | --- | --- |
| users - role_user - roles | N:M | Un usuario puede tener varios roles; un rol agrupa varios usuarios. |
| roles - permission_role - permissions | N:M | Un rol concede varios permisos; un permiso pertenece a varios roles. |
| users - warehouse_user - warehouses | N:M | Un usuario accede a varios almacenes; un almacén autoriza a varios usuarios. |
| categories - categories | 1:N (autorreferente) | Una categoría padre tiene varias hijas; jerarquía de profundidad acotada. |
| categories - products | 1:N | Una categoría agrupa varios productos. |
| units_of_measure - products | 1:N | Una unidad de medida se usa en varios productos. |
| products - product_warehouse - warehouses | N:M | Configuración de stock mínimo/máximo por producto y almacén. |
| products - inventories - warehouses | N:M (tabla puente con saldo) | Un producto tiene un saldo por cada almacén. Restricción única `(product_id, warehouse_id)`. |
| warehouses - inventory_documents (origen) | 1:N | Un almacén es origen de varios documentos. |
| warehouses - inventory_documents (destino) | 1:N | Un almacén es destino de varias transferencias. |
| inventory_documents - inventory_document_lines | 1:N | Un documento contiene una o varias líneas. |
| inventory_documents - inventory_movements | 1:N | Un documento confirmado genera uno o varios movimientos. |
| inventory_document_lines - inventory_movements | 1:N | Una línea origina al menos un movimiento (dos en transferencias). |
| products - inventory_movements | 1:N | Un producto aparece en muchos movimientos (kardex). |
| warehouses - inventory_movements | 1:N | Un almacén registra muchos movimientos. |
| users - audit_logs | 1:N | Un usuario genera muchas entradas de auditoría. |

## 3. Notas de modelado

- **Inventario como libro + saldo.** `inventory_movements` es el libro inmutable (append-only); `inventories` es el saldo materializado por rendimiento. Su coherencia es la invariante I-02 de [inventory-domain.md](./inventory-domain.md).
- **Documento, línea y movimiento.** La intención de negocio (`inventory_documents` + `inventory_document_lines`) se separa del efecto (`inventory_movements`), lo que permite estados `borrador`/`confirmado` y reversiones trazables.
- **Transferencias.** Un documento de tipo `transferencia` usa `origin_warehouse_id` y `destination_warehouse_id` y genera dos movimientos por línea (uno de salida en origen y uno de entrada en destino).
- **Unicidad.** Cada tabla con nombre lógico único lo garantiza con restricción `UNIQUE`: `warehouses.code`, `products.sku`, `products.barcode` (cuando no es nulo), `inventory_documents.uuid`, `inventory_documents.doc_number`, `inventory_documents.idempotency_key` y `inventories(product_id, warehouse_id)`.
- **Borrado lógico.** `warehouses` y `products` usan desactivación (`is_active`) y borrado lógico (`deleted_at`); nunca se borran físicamente si tienen movimientos, para preservar el kardex.
- **Sin secretos.** `users.password` se guarda con hash; `audit_logs` nunca almacena contraseñas, tokens ni secretos.
- **Evolución multi-organización.** El diagrama no incluye `organization_id` en el MVP; se añadirá como columna discriminante en V2 sin rehacer relaciones.
