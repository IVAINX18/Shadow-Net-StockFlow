import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ListParams, PaginatedResponse } from '../../core/models/api.model';
import type { UserSummary } from '../../core/models/user.model';

/**
 * Acceso a las cuentas de usuario (api-design.md §7.2). Las cuentas son
 * globales: el permiso RBAC decide el acceso, no el alcance por almacen.
 */
@Injectable({ providedIn: 'root' })
export class UsersService {
  private readonly api = inject(ApiService);

  /** Listado paginado de usuarios (contrato ligero UserSummaryResource). */
  public list(params?: ListParams): Observable<PaginatedResponse<UserSummary>> {
    return this.api.getPage<UserSummary>('/users', params);
  }

  /** Cuenta de usuarios con los filtros indicados, para el tablero. */
  public count(params?: ListParams): Observable<number> {
    return this.api
      .getPage<UserSummary>('/users', { ...params, per_page: 1 })
      .pipe(map((response) => response.meta.total));
  }
}
