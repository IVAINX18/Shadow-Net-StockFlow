import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ListParams, PaginatedResponse } from '../../core/models/api.model';
import type { Warehouse } from '../../core/models/warehouse.model';

/**
 * Acceso a los almacenes (api-design.md §7.4). Es la unica capa que conoce el
 * endpoint; el componente solo consume modelos tipados.
 */
@Injectable({ providedIn: 'root' })
export class WarehousesService {
  private readonly api = inject(ApiService);

  /** Listado paginado de almacenes visibles segun el alcance del usuario. */
  public list(params?: ListParams): Observable<PaginatedResponse<Warehouse>> {
    return this.api.getPage<Warehouse>('/warehouses', params);
  }

  /**
   * Cuenta de almacenes con los filtros indicados. Pido una sola fila y leo
   * `meta.total`, porque el tablero necesita el conteo, no las entidades.
   */
  public count(params?: ListParams): Observable<number> {
    return this.api
      .getPage<Warehouse>('/warehouses', { ...params, per_page: 1 })
      .pipe(map((response) => response.meta.total));
  }
}
