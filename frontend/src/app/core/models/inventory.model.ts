// Modelos del inventario (Fase 3). Reflejan los Resources medidos en vivo
// contra la API: el listado de documentos ya trae `lines[]` en cada fila, el
// detalle puede traer `movements[]` y `reversal` segun su estado, el kardex
// suma el objeto `balances` fuera del paginador y el stock expone `totals`.
// Los decimales viajan como cadena (DECIMAL 3/6/2) y se muestran tal cual:
// nunca los convierto a numero para no perder digitos.

/** Tipo canonico de documento de inventario. */
export type InventoryDocumentType = 'entrada' | 'salida' | 'ajuste' | 'transferencia';

/** Estado canonico del ciclo de vida del documento. */
export type InventoryDocumentStatus = 'borrador' | 'confirmado' | 'revertido' | 'descartado';

/** Linea de un documento: cantidad y costos como cadena decimal. */
export interface InventoryDocumentLine {
  readonly id: number;
  readonly product_id: number;
  readonly quantity: string;
  readonly direction: string | null;
  readonly unit_cost: string;
  readonly line_total: string;
}

/** Movimiento generado al confirmar (libro append-only). */
export interface InventoryMovement {
  readonly id: number;
  readonly document_id: number;
  readonly product_id: number;
  readonly warehouse_id: number;
  readonly direction: string;
  readonly quantity_delta: string;
  readonly unit_cost: string;
  readonly balance_after: string;
  readonly created_at: string;
}

/**
 * Reversion que corrige un documento confirmado sin borrarlo. El Resource no
 * devuelve el motivo en el detalle del original: solo el resumen del documento
 * de reversion (uuid, numero, tipo y estado), asi que muestro eso y no invento
 * un motivo que la API no entrega.
 */
export interface InventoryReversal {
  readonly uuid: string;
  readonly doc_number: string;
  readonly type: InventoryDocumentType;
  readonly status: InventoryDocumentStatus;
}

/** Documento de inventario con sus lineas y, segun su estado, movimientos y reversion. */
export interface InventoryDocument {
  readonly uuid: string;
  readonly doc_number: string;
  readonly type: InventoryDocumentType;
  readonly status: InventoryDocumentStatus;
  readonly origin_warehouse_id: number | null;
  readonly destination_warehouse_id: number | null;
  readonly reference: string | null;
  readonly notes: string | null;
  readonly reason_code: string | null;
  readonly created_by: number;
  readonly confirmed_by: number | null;
  readonly confirmed_at: string | null;
  readonly created_at: string;
  readonly updated_at: string;
  readonly lines: readonly InventoryDocumentLine[];
  readonly movements?: readonly InventoryMovement[];
  readonly reversal?: InventoryReversal | null;
}

/** Movimiento del kardex por producto y almacen, con su saldo resultante. */
export interface KardexMovement {
  readonly id: number;
  readonly document_id: number;
  readonly product_id: number;
  readonly warehouse_id: number;
  readonly direction: string;
  readonly quantity_delta: string;
  readonly unit_cost: string;
  readonly balance_after: string;
  readonly created_at: string;
}

/** Saldos del rango consultado en el kardex (fuera del paginador). */
export interface KardexBalances {
  readonly initial_balance: string;
  readonly final_balance: string;
}

/** Fila de existencias: cantidades y valores como cadena, nunca float. */
export interface StockRow {
  readonly product_id: number;
  readonly sku: string;
  readonly product_name: string;
  readonly category_id: number | null;
  readonly warehouse_id: number;
  readonly warehouse_code: string;
  readonly quantity: string;
  readonly average_cost: string;
  readonly stock_value: string;
  readonly min_stock: string | null;
  readonly max_stock: string | null;
  readonly reorder_point: string | null;
  readonly below_min: boolean;
  readonly last_movement_at: string | null;
}

/** Totales del listado de existencias (fuera del paginador). */
export interface StockTotals {
  readonly total_quantity: string;
  readonly total_value: string;
}

/** Cuerpo de la reversion: el motivo es obligatorio en el backend. */
export interface ReverseDocumentPayload {
  readonly reason: string;
}
