// Modelo del almacen (WarehouseResource, api-design.md §7.4). El responsable y
// los autorizados solo llegan cuando el controlador carga la relacion, por eso
// son opcionales: el listado no los trae y el detalle si.

/** Referencia minima de una persona (responsable o autorizado). */
export interface PersonRef {
  readonly id: number;
  readonly name: string;
  readonly email: string;
}

/** Almacen visible para el usuario segun su alcance. */
export interface Warehouse {
  readonly id: number;
  readonly code: string;
  readonly name: string;
  readonly address: string | null;
  readonly city: string | null;
  readonly is_active: boolean;
  readonly manager?: PersonRef | null;
  readonly users?: readonly PersonRef[];
}
