import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ListParams, PaginatedResponse } from '../../core/models/api.model';
import type { Permission, Role } from '../../core/models/user.model';

/**
 * Acceso a roles y al catalogo de permisos (api-design.md §7.3). El catalogo de
 * permisos viaja bajo el permiso `roles.view` porque solo existe para construir
 * la gestion de roles.
 */
@Injectable({ providedIn: 'root' })
export class RolesService {
  private readonly api = inject(ApiService);

  /** Listado paginado de roles con sus permisos. */
  public list(params?: ListParams): Observable<PaginatedResponse<Role>> {
    return this.api.getPage<Role>('/roles', params);
  }

  /** Catalogo paginado de permisos. */
  public listPermissions(params?: ListParams): Observable<PaginatedResponse<Permission>> {
    return this.api.getPage<Permission>('/permissions', params);
  }

  /** Cuenta de roles, para el tablero. */
  public count(): Observable<number> {
    return this.api
      .getPage<Role>('/roles', { per_page: 1 })
      .pipe(map((response) => response.meta.total));
  }

  /** Cuenta de permisos del catalogo, para el tablero. */
  public countPermissions(): Observable<number> {
    return this.api
      .getPage<Permission>('/permissions', { per_page: 1 })
      .pipe(map((response) => response.meta.total));
  }
}
