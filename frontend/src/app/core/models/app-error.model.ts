import type { ApiErrorDetail } from './api.model';

// Clasifico el error del backend en una de estas familias para que el
// componente decida como mostrarlo (por campo, aviso de permisos, aviso de
// negocio o error de servidor) sin volver a interpretar codigos HTTP.
export type ErrorKind =
  | 'validation'
  | 'authorization'
  | 'business'
  | 'server'
  | 'network'
  | 'unknown';

/**
 * Error normalizado de la aplicacion. El error.interceptor lo construye a
 * partir del sobre uniforme del backend (api-design.md §5) y los componentes
 * solo consumen esta forma, nunca HttpErrorResponse.
 */
export class AppError extends Error {
  public readonly kind: ErrorKind;
  /** Codigo del backend, por ejemplo VALIDACION o SIN_PERMISO. */
  public readonly code: string;
  /** Estado HTTP cuando la respuesta llego; 0 si fue un fallo de red. */
  public readonly status: number;
  /** Detalle por campo (422) o por producto/documento (409). */
  public readonly details: readonly ApiErrorDetail[];
  /** Identificador de correlacion del 500, para reportarlo. */
  public readonly errorId: string | null;

  public constructor(init: {
    kind: ErrorKind;
    code: string;
    message: string;
    status: number;
    details?: readonly ApiErrorDetail[];
    errorId?: string | null;
  }) {
    super(init.message);
    this.name = 'AppError';
    this.kind = init.kind;
    this.code = init.code;
    this.status = init.status;
    this.details = init.details ?? [];
    this.errorId = init.errorId ?? null;
  }

  /** Indica si es un error de permisos (403) que conviene mostrar sin detalle. */
  public get isAuthorization(): boolean {
    return this.kind === 'authorization';
  }

  /** Mensaje listo para mostrar del campo indicado, si el backend lo detallo. */
  public fieldMessage(field: string): string | null {
    return this.details.find((detail) => detail.field === field)?.message ?? null;
  }
}
