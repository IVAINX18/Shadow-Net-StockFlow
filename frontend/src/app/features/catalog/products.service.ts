import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ItemResponse, ListParams, PaginatedResponse } from '../../core/models/api.model';
import type {
  CreateProductPayload,
  Product,
  ProductWarehouseConfig,
  UpdateProductPayload,
  UpdateWarehouseConfigPayload,
} from '../../core/models/catalog.model';

/**
 * Acceso a los productos y a su configuracion por almacen (api-design.md
 * §7.7). La ficha expone categoria, unidad y configuracion min/max de los
 * almacenes visibles, sin cantidades: el kardex llega en una fase posterior y
 * no invento ceros. El SKU es inmutable y no se envia en la edicion.
 */
@Injectable({ providedIn: 'root' })
export class ProductsService {
  private readonly api = inject(ApiService);

  /** Listado paginado con filtros is_active, category_id, warehouse_id, q y sort. */
  public list(params?: ListParams): Observable<PaginatedResponse<Product>> {
    return this.api.getPage<Product>('/products', params);
  }

  /** Ficha con categoria, unidad y configuracion de almacenes autorizados. */
  public get(id: number): Observable<Product> {
    return this.api.unwrap<Product>(`/products/${id}`);
  }

  /** Alta de producto (catalog.create). Los montos viajan como cadena. */
  public create(payload: CreateProductPayload): Observable<Product> {
    return this.api
      .post<ItemResponse<Product>>('/products', payload)
      .pipe(map((response) => response.data));
  }

  /** Edicion de producto (catalog.update). Nunca incluye el SKU. */
  public update(id: number, payload: UpdateProductPayload): Observable<Product> {
    return this.api
      .put<ItemResponse<Product>>(`/products/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  /** Desactivacion. Preserva el historial sin borrarlo. */
  public deactivate(id: number): Observable<Product> {
    return this.api
      .post<ItemResponse<Product>>(`/products/${id}/deactivate`, {})
      .pipe(map((response) => response.data));
  }

  /** Configuracion min/max por almacen dentro del alcance del llamante. */
  public warehouseConfigs(productId: number): Observable<readonly ProductWarehouseConfig[]> {
    return this.api.unwrap<readonly ProductWarehouseConfig[]>(`/products/${productId}/warehouses`);
  }

  /**
   * Alta o edicion de la configuracion por almacen (catalog.update mas alcance
   * por almacen; 403 FUERA_DE_ALCANCE fuera de alcance). Todo como cadena.
   */
  public updateWarehouseConfig(
    productId: number,
    warehouseId: number,
    payload: UpdateWarehouseConfigPayload,
  ): Observable<ProductWarehouseConfig> {
    return this.api
      .put<ItemResponse<ProductWarehouseConfig>>(
        `/products/${productId}/warehouses/${warehouseId}`,
        payload,
      )
      .pipe(map((response) => response.data));
  }
}
