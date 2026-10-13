import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ItemResponse, ListParams, PaginatedResponse } from '../../core/models/api.model';
import type {
  Category,
  CreateCategoryPayload,
  UpdateCategoryPayload,
} from '../../core/models/catalog.model';

/**
 * Acceso a las categorias (api-design.md §7.5). Las lecturas piden solo sesion
 * y las escrituras el permiso catalog.create o catalog.update, que el backend
 * verifica; aqui solo se consumen modelos tipados.
 */
@Injectable({ providedIn: 'root' })
export class CategoriesService {
  private readonly api = inject(ApiService);

  /** Listado paginado con filtros is_active, parent_id, q y sort name|created_at. */
  public list(params?: ListParams): Observable<PaginatedResponse<Category>> {
    return this.api.getPage<Category>('/categories', params);
  }

  /** Detalle con hijas y conteo de productos. */
  public get(id: number): Observable<Category> {
    return this.api.unwrap<Category>(`/categories/${id}`);
  }

  /** Alta de categoria (catalog.create). */
  public create(payload: CreateCategoryPayload): Observable<Category> {
    return this.api
      .post<ItemResponse<Category>>('/categories', payload)
      .pipe(map((response) => response.data));
  }

  /** Edicion de categoria (catalog.update). */
  public update(id: number, payload: UpdateCategoryPayload): Observable<Category> {
    return this.api
      .put<ItemResponse<Category>>(`/categories/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  /** Desactivacion. Rechaza con 409 si hay productos o subcategorias activas. */
  public deactivate(id: number): Observable<Category> {
    return this.api
      .post<ItemResponse<Category>>(`/categories/${id}/deactivate`, {})
      .pipe(map((response) => response.data));
  }
}
