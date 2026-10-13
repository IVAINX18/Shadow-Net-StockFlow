import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ItemResponse, ListParams, PaginatedResponse } from '../../core/models/api.model';
import type { CreateUnitPayload, UnitOfMeasure } from '../../core/models/catalog.model';

/**
 * Acceso a las unidades de medida (api-design.md §7.6). El contrato no define
 * edicion ni borrado, asi que no los ofrezco: solo listado, detalle y alta.
 */
@Injectable({ providedIn: 'root' })
export class UnitsService {
  private readonly api = inject(ApiService);

  /** Listado paginado con sort code|name. */
  public list(params?: ListParams): Observable<PaginatedResponse<UnitOfMeasure>> {
    return this.api.getPage<UnitOfMeasure>('/units', params);
  }

  /** Detalle de una unidad. */
  public get(id: number): Observable<UnitOfMeasure> {
    return this.api.unwrap<UnitOfMeasure>(`/units/${id}`);
  }

  /** Alta de unidad (catalog.create). */
  public create(payload: CreateUnitPayload): Observable<UnitOfMeasure> {
    return this.api
      .post<ItemResponse<UnitOfMeasure>>('/units', payload)
      .pipe(map((response) => response.data));
  }
}
