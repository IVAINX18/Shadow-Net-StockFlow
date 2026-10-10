# Arquitectura del backend - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento describe la arquitectura del backend Laravel 13 del ERP Shadow-Net StockFlow: su estructura por capas, responsabilidades, versionado de la API, manejo de errores y flujo transaccional del inventario. La visión del sistema está en [system-architecture.md](./system-architecture.md), las decisiones de versión y patrones en [architecture-decisions.md](./architecture-decisions.md), las reglas del dominio en [inventory-domain.md](./inventory-domain.md) y el modelo físico en [database-design.md](./database-design.md). El cliente que consume esta API se describe en [frontend-architecture.md](./frontend-architecture.md) y su operación en [deployment-architecture.md](./deployment-architecture.md).

## 1. Principios

- Controladores finos: traducen HTTP a llamadas de caso de uso; no contienen reglas de negocio ni abren transacciones. [DECISIÓN]
- Lógica transaccional en casos de uso y servicios: toda confirmación o reversión de documentos vive en un servicio que no conoce HTTP ni JSON, reutilizable desde API, consola o cola.
- Autorización siempre en el servidor con Policies y Gates sobre el RBAC nativo; el frontend nunca otorga derechos.
- Respuestas versionadas mediante API Resources; errores uniformes mapeados desde excepciones de dominio.
- Tipos numéricos DECIMAL según [database-design.md](./database-design.md); nunca flotantes para dinero ni cantidades.

Versiones aplicables (ver [architecture-decisions.md](./architecture-decisions.md)): Laravel 13.35.x sobre PHP 8.4.x, Sanctum 4.3.x, Pest 5.3.x para pruebas, Pint 1.32.x para estilo, `barryvdh/laravel-dompdf` 3.1.x y `maatwebsite/excel` 4.0.x para exportación.

## 2. Estructura por capas dentro de `backend/app`

| Capa | Contenido | Responsabilidad |
| --- | --- | --- |
| `Http/Controllers` | Controladores finos por recurso (`AuthController`, `WarehouseController`, `ProductController`, `InventoryDocumentController`, `ReportController`) | Reciben el Form Request validado, verifican autorización (`authorize` / `can`), invocan un caso de uso y devuelven un Resource. |
| `Http/Requests` | Form Requests (`StoreDocumentRequest`, `ConfirmDocumentRequest`, `RevertDocumentRequest`) | Validación sintáctica (tipos, requeridos, formatos, existencia de identificadores) y preparación de datos. No validan disponibilidad de stock. |
| `Http/Resources` | API Resources (`DocumentResource`, `MovementResource`, `InventoryResource`) | Forma pública y estable del JSON v1; ocultan columnas internas y exponen `uuid` en documentos. |
| `Http/Middleware` | Autenticación Sanctum, verificación de cuenta activa, registro de auditoría HTTP | Sesión por cookie para la SPA, bloqueo de usuarios desactivados, contexto de auditoría (IP, agente). |
| `UseCases` / `Services` | Casos de uso transaccionales (`ConfirmInventoryDocument`, `RevertInventoryDocument`, `CreateInventoryDocument`) | Unidad atómica: bloqueos, validaciones de negocio, deltas, movimientos, promedio ponderado, cambio de estado y auditoría, todo en una transacción. [DECISIÓN] |
| `Domain` | Entidades, enumeraciones y objetos de valor según necesidad (`DocumentType`, `DocumentStatus`, `MovementDirection`, `Money`, `Quantity`) | Vocabulario del dominio independiente del framework; máquina de estados y fórmula de valorización. |
| `Models` | Modelos Eloquent (`User`, `Warehouse`, `Product`, `Inventory`, `InventoryDocument`, `InventoryDocumentLine`, `InventoryMovement`, `AuditLog`) | Acceso a datos del MVP; relaciones, conversiones de tipo (`casts`) y ámbitos de consulta. Si las consultas de kardex se complican, se introduce una capa de repositorios sin cambiar los casos de uso. [DECISIÓN] |
| `Policies` / `Gates` | `WarehousePolicy`, `InventoryDocumentPolicy`, `ProductPolicy`, definiciones de Gates por permiso | RBAC nativo más alcance por almacén (`warehouse_user`): quién puede crear, confirmar o leer cada almacén. |
| `Events` / `Listeners` | `DocumentConfirmed`, `DocumentReverted` y sus oyentes | Efectos secundarios fuera de la transacción crítica (auditoría diferida, notificaciones, recálculo de indicadores). |
| `Jobs` | Exportaciones grandes, indicador de valorización pesado | Trabajos en cola de base de datos (ADR-013); sin Redis en el MVP. |
| `Notifications` | Avisos de stock mínimo, confirmación de transferencias | Correos o notificaciones internas disparadas por eventos. |
| `Exceptions` | Excepciones de dominio (`InsufficientStockException`, `InvalidDocumentTransitionException`, `DuplicateIdempotencyKeyException`) | Representan fallos de negocio; el manejador las mapea a respuestas HTTP. |

## 3. Árbol de carpetas

```text
backend/
  routes/
    api/
      v1/                        Versionado de la API (ApiVersioning).
        auth.php
        warehouses.php
        products.php
        inventory-documents.php
        reports.php
  app/
    Http/
      Controllers/
        AuthController.php
        WarehouseController.php
        ProductController.php
        InventoryDocumentController.php
        ReportController.php
      Requests/
        StoreDocumentRequest.php
        ConfirmDocumentRequest.php
        RevertDocumentRequest.php
      Resources/
        DocumentResource.php
        MovementResource.php
        InventoryResource.php
      Middleware/
        EnsureAccountIsActive.php
    UseCases/
      CreateInventoryDocument.php
      ConfirmInventoryDocument.php
      RevertInventoryDocument.php
    Services/
      WeightedAverageCost.php
      IdempotencyGuard.php
    Domain/
      Enums/
        DocumentType.php
        DocumentStatus.php
        MovementDirection.php
      ValueObjects/
        Quantity.php
    Models/
      User.php
      Warehouse.php
      Product.php
      Inventory.php
      InventoryDocument.php
      InventoryDocumentLine.php
      InventoryMovement.php
      AuditLog.php
    Policies/
      WarehousePolicy.php
      InventoryDocumentPolicy.php
      ProductPolicy.php
    Events/
      DocumentConfirmed.php
      DocumentReverted.php
    Listeners/
      WriteAuditEntry.php
      NotifyLowStock.php
    Jobs/
      ExportInventoryReport.php
    Notifications/
      LowStockNotification.php
    Exceptions/
      InsufficientStockException.php
      InvalidDocumentTransitionException.php
  database/
    migrations/                  Orden según database-design.md (§7).
    seeders/                     Roles, permisos, unidades, categorías, admin de desarrollo.
  tests/
    Unit/
    Feature/                     Integración, autorización y concurrencia (MySQL real).
```

## 4. Versionado de la API

[DECISIÓN] La base pública es `/api/v1` y cada grupo de rutas vive en `routes/api/v1/*.php`. Las reglas son:

- La v1 es estable; cualquier cambio incompatible crea una v2 sin modificar la v1.
- Los Resources pertenecen a la versión (p. ej. `Http/Resources/V1/...` cuando aparezca la v2).
- El identificador público de documentos en URL es el `uuid`; el id interno nunca aparece en rutas de documentos.
- La especificación OpenAPI versionada es el contrato (ADR-012); el diseño de endpoints se detalla en su documento propio.

Esqueleto ilustrativo del registro de rutas:

```php
// routes/api/v1/inventory-documents.php
Route::middleware(['auth:sanctum', 'active.account'])->group(function () {
    Route::post('/inventory-documents', [InventoryDocumentController::class, 'store']);
    Route::post('/inventory-documents/{document:uuid}/confirm', [InventoryDocumentController::class, 'confirm']);
    Route::post('/inventory-documents/{document:uuid}/revert', [InventoryDocumentController::class, 'revert']);
});
```

## 5. Flujo transaccional con `SELECT ... FOR UPDATE`

La confirmación ejecuta la unidad atómica de [inventory-domain.md](./inventory-domain.md) (§5 y §6). Orden dentro de la transacción:

1. Revalidar el estado del documento en modo bloqueante (`lockForUpdate`): debe seguir en `borrador`; esto evita la doble confirmación concurrente.
2. Resolver los pares `(warehouse_id, product_id)` afectados (uno o dos almacenes en transferencias) y ordenarlos ascendentemente; adquirir los bloqueos en ese orden global para evitar interbloqueos.
3. Si la fila de `inventories` no existe (primera entrada), crearla; ante colisión por la restricción única `(product_id, warehouse_id)`, reintentar leyendo y bloqueando la fila ya creada.
4. Validar las reglas de negocio contra el saldo bloqueado: cantidades positivas, disponibilidad suficiente, almacenes distintos en transferencias, entidades activas, motivo en ajustes, autorización por almacén.
5. Aplicar los deltas a `inventories`, recalcular el promedio ponderado en entradas y en la pata de destino de transferencias, insertar los movimientos con el saldo resultante y cambiar el documento a `confirmado` con responsable y fecha.
6. Insertar la entrada de auditoría y confirmar (`COMMIT`); ante cualquier excepción o regla fallida, revertir (`ROLLBACK`) sin efectos parciales.
7. Ejecutar la transacción con reintento acotado ante interbloqueo; un interbloqueo repite la transacción completa, nunca aplica deltas parciales.

Esqueleto ilustrativo del caso de uso (sin código completo):

```php
// app/UseCases/ConfirmInventoryDocument.php (esqueleto)
Db::transaction(function () use ($document, $user) {
    $doc = InventoryDocument::where('uuid', $document->uuid)->lockForUpdate()->firstOrFail();
    $this->guardState($doc);                 // debe seguir en borrador
    $pairs = $this->orderedPairs($doc);      // orden (warehouse_id, product_id)
    $stocks = $this->lockStocks($pairs);     // SELECT ... FOR UPDATE ordenado
    $this->guardBusinessRules($doc, $stocks);// disponibilidad, almacenes, motivo
    $this->applyDeltas($doc, $stocks);       // saldos + promedio ponderado
    $this->writeMovements($doc, $stocks);    // kardex con balance_after
    $this->markConfirmed($doc, $user);       // estado, responsable, fecha
    $this->writeAudit($doc, $user);          // auditoría en la misma transacción
}, attempts: 3);
```

## 6. Manejo de errores

[DECISIÓN] Las reglas de negocio se expresan como excepciones de dominio y el manejador de excepciones las mapea a HTTP de forma uniforme:

| Excepción | Significado | Respuesta HTTP |
| --- | --- | --- |
| `InsufficientStockException` | Salida o pata de origen sin disponibilidad | 409 con el detalle del producto y el saldo disponible |
| `InvalidDocumentTransitionException` | Confirmar, revertir o descartar desde un estado no permitido | 422 con el estado actual y la transición intentada |
| `DuplicateIdempotencyKeyException` | Clave de idempotencia ya usada con efecto aplicado | 200 con el recurso existente o 409 idempotente según el contrato |
| Validación del Form Request | Sintaxis inválida | 422 con errores por campo |
| Fallo de Policy o Gate | Sin permiso sobre el almacén o la acción | 403 sin revelar datos del almacén |
| Error no previsto | Excepción inesperada | 500 genérico con identificador de correlación; el detalle queda en los logs |

Los errores de negocio nunca exponen trazas ni secretos; los de validación devuelven campos y mensajes accionables para el formulario.

## 7. Convenciones

- Código y nombres técnicos en inglés; mensajes de error y documentación en español (acta del proyecto, SD-06).
- Tablas en snake_case plural, foráneas `<entidad>_id`, índices `idx_*`, únicas `uq_*` y foráneas `fk_*` según [database-design.md](./database-design.md).
- Zona horaria de almacenamiento UTC; presentación en America/Bogota.
- Estilo de código con Pint 1.32.x; pruebas con Pest 5.3.x (unitarias, integración, autorización y concurrencia contra MySQL real, nunca SQLite para concurrencia).
- Semillas con roles base (Administrador, Jefe de almacén, Operador, Auditor), catálogo de permisos, unidades y categorías; el administrador de desarrollo se crea con credenciales por variable de entorno, nunca fijas en el código.

## 8. Inyección de servicios

[DECISIÓN] Los casos de uso y servicios se resuelven por el contenedor de Laravel mediante inyección por constructor con tipos explícitos; los controladores reciben el caso de uso como dependencia y no lo instancian manualmente. Esto permite sustituir implementaciones (por ejemplo, introducir repositorios o dobles de prueba) sin tocar los controladores, y facilita las pruebas de integración del dominio.

Esqueleto ilustrativo:

```php
// app/Http/Controllers/InventoryDocumentController.php (esqueleto)
public function __construct(
    private ConfirmInventoryDocument $confirmDocument,
) {}

public function confirm(ConfirmDocumentRequest $request, InventoryDocument $document)
{
    $this->authorize('confirm', $document);
    $result = $this->confirmDocument->handle($document, $request->user());
    return new DocumentResource($result);
}
```

## 9. Referencias

- Sistema: [system-architecture.md](./system-architecture.md).
- Decisiones: [architecture-decisions.md](./architecture-decisions.md).
- Dominio: [inventory-domain.md](./inventory-domain.md).
- Datos: [database-design.md](./database-design.md) y [er-diagram.md](./er-diagram.md).
- Frontend: [frontend-architecture.md](./frontend-architecture.md).
- Despliegue: [deployment-architecture.md](./deployment-architecture.md).
