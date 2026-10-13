import type { ChipTone } from '../status-chip/status-chip.component';

/**
 * Definicion de una columna de la tabla de datos. `cell` produce la
 * representacion textual (los decimales llegan como cadena y se muestran tal
 * cual); `status` opcional hace que la celda se pinte con un chip de estado.
 */
export interface DataTableColumn<T> {
  /** Identificador de la columna (y clave de orden por defecto). */
  readonly key: string;
  /** Encabezado visible. */
  readonly header: string;
  /** Texto de la celda para la fila. */
  readonly cell: (row: T) => string;
  /** Tono del chip si la columna representa un estado. */
  readonly status?: (row: T) => ChipTone | null;
  /** Alinea a la derecha y activa cifras tabulares. */
  readonly numeric?: boolean;
  /** Presenta la celda en monoespaciada (codigos, slugs, claves tecnicas). */
  readonly mono?: boolean;
  /** Permite ordenar por esta columna. */
  readonly sortable?: boolean;
  /** Campo de orden que espera la API, si difiere de `key`. */
  readonly sortKey?: string;
}

/** Direccion de ordenamiento. */
export type SortDirection = 'asc' | 'desc';

/**
 * Accion por fila de la tabla. La pagina la define con sus permisos ya
 * evaluados: hidden oculta y disabled desactiva con su motivo accesible.
 */
export interface DataTableAction<T> {
  /** Identificador estable para el seguimiento de la lista. */
  readonly id: string;
  /** Etiqueta visible en la ayuda emergente y el nombre accesible. */
  readonly label: string;
  /** Icono de Material Symbols. */
  readonly icon: string;
  /** Ejecuta la accion sobre la fila. */
  readonly run: (row: T) => void;
  /** Oculta la accion para la fila indicada. */
  readonly hidden?: (row: T) => boolean;
  /** Desactiva la accion para la fila indicada. */
  readonly disabled?: (row: T) => boolean;
}

/** Cambio de orden solicitado por el usuario. */
export interface SortChange {
  readonly key: string;
  readonly direction: SortDirection;
}

/** Cambio de pagina solicitado por el usuario. */
export interface PageChange {
  readonly page: number;
  readonly pageSize: number;
}
