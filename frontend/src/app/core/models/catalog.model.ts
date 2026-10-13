// Modelos del catalogo (api-design.md §7.5, §7.6, §7.7 y §7.13). Reflejan los
// Resources del backend sin campos inventados: la ficha de producto no trae
// cantidades ni costo promedio porque el kardex llega en una fase posterior.

/** Categoria con su posicion jerarquica (CategoryResource). */
export interface Category {
  readonly id: number;
  readonly name: string;
  // El slug es nulo cuando el alta no lo envio: la migracion lo declara
  // nullable y MySQL admite varios nulos en la unica.
  readonly slug: string | null;
  readonly parent_id: number | null;
  readonly is_active: boolean;
  /** Presentes solo en el detalle, cuando el controlador los cargo. */
  readonly children?: readonly Category[];
  readonly products_count?: number;
}

/** Cuerpo de alta de categoria (StoreCategoryRequest). */
export interface CreateCategoryPayload {
  readonly name: string;
  readonly parent_id?: number | null;
  readonly slug?: string;
}

/** Cuerpo de edicion de categoria (UpdateCategoryRequest). */
export interface UpdateCategoryPayload {
  readonly name?: string;
  readonly parent_id?: number | null;
  readonly is_active?: boolean;
}

/** Unidad de medida (UnitOfMeasureResource). No tiene edicion ni borrado. */
export interface UnitOfMeasure {
  readonly id: number;
  readonly code: string;
  readonly name: string;
  readonly allows_decimals: boolean;
}

/** Cuerpo de alta de unidad (StoreUnitOfMeasureRequest). */
export interface CreateUnitPayload {
  readonly code: string;
  readonly name: string;
  readonly allows_decimals?: boolean;
}

/** Referencia minima de categoria o unidad dentro del producto. */
export interface CategoryRef {
  readonly id: number;
  readonly name: string;
  readonly slug: string | null;
  readonly parent_id: number | null;
  readonly is_active: boolean;
}

/** Producto (ProductResource). Los montos viajan como cadena, nunca float. */
export interface Product {
  readonly id: number;
  readonly sku: string;
  readonly name: string;
  readonly description: string | null;
  readonly barcode: string | null;
  readonly category_id: number;
  readonly unit_of_measure_id: number;
  readonly is_active: boolean;
  readonly cost_price: string | null;
  readonly sale_price: string | null;
  readonly costing_method: string;
  readonly category?: CategoryRef;
  readonly unit_of_measure?: UnitOfMeasure;
  /** Configuracion de los almacenes visibles para el llamante. */
  readonly warehouses?: readonly ProductWarehouseConfig[];
}

/** Cuerpo de alta de producto (CatalogRules::productRules). */
export interface CreateProductPayload {
  readonly sku: string;
  readonly name: string;
  readonly description?: string | null;
  readonly barcode?: string | null;
  readonly category_id: number;
  readonly unit_of_measure_id: number;
  readonly cost_price?: string;
  readonly sale_price?: string;
}

/** Cuerpo de edicion. El SKU no se envia: es inmutable (422 si cambia). */
export interface UpdateProductPayload {
  readonly name?: string;
  readonly description?: string | null;
  readonly barcode?: string | null;
  readonly category_id?: number;
  readonly unit_of_measure_id?: number;
  readonly is_active?: boolean;
  readonly cost_price?: string;
  readonly sale_price?: string;
}

/** Configuracion de stock por almacen (ProductWarehouseResource). */
export interface ProductWarehouseConfig {
  readonly id: number;
  readonly product_id: number;
  readonly warehouse_id: number;
  readonly min_stock: string;
  readonly max_stock: string | null;
  readonly reorder_point: string | null;
}

/** Cuerpo de configuracion por almacen. Todo como cadena decimal o nulo. */
export interface UpdateWarehouseConfigPayload {
  readonly min_stock?: string;
  readonly max_stock?: string | null;
  readonly reorder_point?: string | null;
}

/** Error de una fila del informe de importacion (campo y motivo en español). */
export interface ImportRowError {
  readonly campo: string;
  readonly motivo: string;
}

/** Fila del informe de validacion previa (dry-run). */
export interface ImportReportRow {
  readonly fila: number;
  readonly sku: string | null;
  readonly valida: boolean;
  readonly errores: readonly ImportRowError[];
}

/** Informe del dry-run: no escribe nada (AC-062). */
export interface CatalogDryRunReport {
  readonly archivo: string;
  readonly total_filas: number;
  readonly filas_validas: number;
  readonly filas_invalidas: number;
  readonly valida: boolean;
  readonly filas: readonly ImportReportRow[];
}

/** Resumen de la aplicacion atomica con su referencia de auditoria. */
export interface CatalogApplySummary {
  readonly archivo: string;
  readonly filas_creadas: number;
  readonly filas_actualizadas: number;
  readonly filas_totales: number;
  readonly auditoria_id: number;
}

/** Fila del informe atomico del 422: nada se aplico (AC-063). */
export interface ImportRowIssue {
  readonly fila: number;
  readonly campo: string;
  readonly motivo: string;
}

// Leo el informe por fila del 422 de aplicacion desde los detalles crudos del
// error: esa respuesta usa las claves fila/campo/motivo (no field/message), asi
// que el detalle filtrado por campo no la conserva y la recupero aqui con
// guardas de tipo en lugar de asumir la forma.
export function readImportRowIssues(details: readonly unknown[]): readonly ImportRowIssue[] {
  const issues: ImportRowIssue[] = [];

  for (const detail of details) {
    if (typeof detail !== 'object' || detail === null) {
      continue;
    }

    const record = detail as Record<string, unknown>;
    const fila = record['fila'];
    const campo = record['campo'];
    const motivo = record['motivo'];

    if (typeof fila === 'number' && typeof campo === 'string' && typeof motivo === 'string') {
      issues.push({ fila, campo, motivo });
    }
  }

  return issues;
}
