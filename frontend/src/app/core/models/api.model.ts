// Modelos del sobre uniforme de la API (api-design.md §5) y de la paginacion
// (§4). Los declaro en core/models para que todo servicio y componente use la
// misma forma tipada y ningun dato de la API viaje como any.

/** Elemento de `error.details`, uno por campo en los 422. */
export interface ApiErrorDetail {
  readonly field?: string;
  readonly code: string;
  readonly message: string;
  /** En el 500 el detalle trae solo `error_id` para rastrear en el log. */
  readonly error_id?: string;
}

/** Sobre de error: `{ error: { code, message, details } }`. */
export interface ApiErrorEnvelope {
  readonly error: {
    readonly code: string;
    readonly message: string;
    /** En el 500 el detalle trae solo `error_id` para rastrear en el log. */
    readonly details: readonly Partial<ApiErrorDetail>[];
  };
}

/** Enlaces de navegacion del paginador de Laravel. */
export interface PaginationLinks {
  readonly first: string | null;
  readonly last: string | null;
  readonly prev: string | null;
  readonly next: string | null;
}

/** Metadatos de paginacion: `page`/`per_page` con `meta` (api-design.md §4). */
export interface PaginationMeta {
  readonly current_page: number;
  readonly per_page: number;
  readonly total: number;
  readonly last_page: number;
}

/** Respuesta de coleccion paginada. */
export interface PaginatedResponse<T> {
  readonly data: readonly T[];
  readonly links: PaginationLinks;
  readonly meta: PaginationMeta;
}

/** Respuesta de recurso unico. */
export interface ItemResponse<T> {
  readonly data: T;
}

/** Parametros de consulta de un listado (page, per_page, sort, filtros, q). */
export interface ListParams {
  readonly page?: number;
  readonly per_page?: number;
  readonly sort?: string;
  readonly q?: string;
  readonly [filter: string]: string | number | boolean | undefined;
}
