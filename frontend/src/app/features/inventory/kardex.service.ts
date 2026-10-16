import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ListParams, PaginatedResponse } from '../../core/models/api.model';
import type { KardexBalances, KardexMovement } from '../../core/models/inventory.model';

/** Respuesta del kardex: paginador de Laravel mas los saldos del rango. */
export interface KardexResponse extends PaginatedResponse<KardexMovement> {
  readonly balances: KardexBalances;
}

/** Filtros del kardex. Producto y almacen son obligatorios en el backend. */
export interface KardexParams extends ListParams {
  readonly product_id: number;
  readonly warehouse_id: number;
  readonly from?: string;
  readonly to?: string;
}

/**
 * Acceso al kardex por producto y almacen. Uso GET tipado en lugar de
 * getPage porque la respuesta trae `balances` junto al paginador y ese objeto
 * alimenta las tarjetas de saldo inicial y final del rango.
 */
@Injectable({ providedIn: 'root' })
export class KardexService {
  private readonly api = inject(ApiService);

  /** Movimientos del producto en el almacen, con saldos del rango. */
  public list(params: KardexParams): Observable<KardexResponse> {
    return this.api.get<KardexResponse>('/inventory/kardex', params);
  }
}
