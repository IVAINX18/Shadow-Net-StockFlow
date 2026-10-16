import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ItemResponse, ListParams, PaginatedResponse } from '../../core/models/api.model';
import type {
  InventoryDocument,
  ReverseDocumentPayload,
} from '../../core/models/inventory.model';

/**
 * Acceso a los documentos de inventario. El listado es paginado en el
 * servidor con filtros type, status, almacenes y fechas; el backend resuelve
 * el alcance por almacen, asi que no filtro nada en memoria. La confirmacion
 * exige cabecera Idempotency-Key porque el endpoint es transaccional y un
 * reintento sin clave duplicaria movimientos.
 */
@Injectable({ providedIn: 'root' })
export class InventoryDocumentsService {
  private readonly api = inject(ApiService);

  /** Listado paginado con los filtros reales de la API. */
  public list(params?: ListParams): Observable<PaginatedResponse<InventoryDocument>> {
    return this.api.getPage<InventoryDocument>('/inventory-documents', params);
  }

  /** Detalle con lineas y, segun su estado, movimientos y reversion. */
  public get(uuid: string): Observable<InventoryDocument> {
    return this.api.unwrap<InventoryDocument>(`/inventory-documents/${uuid}`);
  }

  /**
   * Confirma un borrador y genera sus movimientos. Cada confirmacion lleva
   * una clave de idempotencia propia: si el usuario reintenta tras un fallo
   * de red, el backend responde sin duplicar el asiento.
   */
  public confirm(uuid: string): Observable<InventoryDocument> {
    return this.api
      .post<ItemResponse<InventoryDocument>>(
        `/inventory-documents/${uuid}/confirm`,
        {},
        { 'Idempotency-Key': crypto.randomUUID() },
      )
      .pipe(map((response) => response.data));
  }

  /** Descarta un borrador. No toca existencias porque nada se confirmo. */
  public cancel(uuid: string): Observable<InventoryDocument> {
    return this.api
      .post<ItemResponse<InventoryDocument>>(`/inventory-documents/${uuid}/cancel`, {})
      .pipe(map((response) => response.data));
  }

  /** Revierte un confirmado con motivo obligatorio; el original queda revertido. */
  public reverse(uuid: string, payload: ReverseDocumentPayload): Observable<InventoryDocument> {
    return this.api
      .post<ItemResponse<InventoryDocument>>(`/inventory-documents/${uuid}/reverse`, payload)
      .pipe(map((response) => response.data));
  }
}
