import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ListParams, PaginatedResponse } from '../../core/models/api.model';
import type { StockRow, StockTotals } from '../../core/models/inventory.model';

/** Respuesta de existencias: paginador de Laravel mas los totales. */
export interface StockResponse extends PaginatedResponse<StockRow> {
  readonly totals: StockTotals;
}

/**
 * Acceso al listado de existencias. Vive bajo /reports y exige el permiso
 * reports.view, no inventory.view: la matriz de permisos los separa a
 * proposito, asi que la ruta se protege con ese slug y un 403 se muestra
 * como falta de permiso, no como tabla vacia.
 */
@Injectable({ providedIn: 'root' })
export class StockService {
  private readonly api = inject(ApiService);

  /**
   * Existencias con filtros de almacen, categoria y bajo minimo. Mapeo
   * below_min a las cadenas 'true'/'false' que anuncia el tablero en lugar
   * de delegar el booleano: asi `false` viaja explicito y ningun casteo a
   * ciegas lo invierte ni lo omite.
   */
  public list(params?: ListParams & { readonly below_min?: boolean }): Observable<StockResponse> {
    const { below_min, ...rest } = params ?? {};
    return this.api.get<StockResponse>('/reports/stock', {
      ...rest,
      ...(below_min === undefined ? {} : { below_min: below_min ? 'true' : 'false' }),
    });
  }
}
