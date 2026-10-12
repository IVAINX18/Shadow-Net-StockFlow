import { Injectable } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { AppError, type ErrorKind } from '../models/app-error.model';
import type { ApiErrorEnvelope, ApiErrorDetail } from '../models/api.model';

// Codigos del backend (api-design.md §5) agrupados por familia para no repetir
// listas en cada componente.
const AUTHORIZATION_CODES = new Set(['NO_AUTENTICADO', 'SIN_PERMISO', 'FUERA_DE_ALCANCE', 'CUENTA_INACTIVA']);
const BUSINESS_CODES = new Set(['STOCK_INSUFICIENTE', 'ESTADO_INVALIDO', 'IDEMPOTENCIA_REPETIDA', 'NO_ENCONTRADO']);

/**
 * Normaliza cualquier fallo (respuesta HTTP, red o error desconocido) a un
 * AppError tipado. Vive en core/services porque es transversal: el
 * error.interceptor lo usa y los componentes solo consumen AppError.
 */
@Injectable({ providedIn: 'root' })
export class ErrorService {
  /** Convierte un error arbitrario en AppError, sin escapar nunca a HttpErrorResponse. */
  public toAppError(error: unknown): AppError {
    if (error instanceof AppError) {
      return error;
    }

    if (error instanceof HttpErrorResponse) {
      // El estado 0 no trae respuesta HTTP: es un fallo de red o CORS, y el
      // usuario necesita una frase distinta a la de un error del servidor.
      if (error.status === 0) {
        return new AppError({
          kind: 'network',
          code: 'SIN_CONEXION',
          message: 'No se pudo contactar con el servidor. Verifique su conexion e intente de nuevo.',
          status: 0,
        });
      }

      const envelope = this.readEnvelope(error.error);

      if (envelope !== null) {
        return new AppError({
          kind: this.kindFor(error.status, envelope.error.code),
          code: envelope.error.code,
          message: envelope.error.message,
          status: error.status,
          details: this.readDetails(envelope),
          errorId: this.readErrorId(error.error),
        });
      }

      return new AppError({
        kind: this.kindFor(error.status, ''),
        code: this.codeForStatus(error.status),
        message: this.messageForStatus(error.status),
        status: error.status,
      });
    }

    return new AppError({
      kind: 'unknown',
      code: 'DESCONOCIDO',
      message: 'Ocurrio un error inesperado. Intente de nuevo.',
      status: 0,
    });
  }

  // Leo el sobre con guardas de tipo en lugar de confiar en la forma, porque el
  // body llega como unknown y el modo estricto prohibe asumir `any`.
  private readEnvelope(body: unknown): ApiErrorEnvelope | null {
    if (typeof body !== 'object' || body === null) {
      return null;
    }

    const maybeError = (body as Record<string, unknown>)['error'];

    if (typeof maybeError !== 'object' || maybeError === null) {
      return null;
    }

    const record = maybeError as Record<string, unknown>;
    const code = record['code'];
    const message = record['message'];

    if (typeof code !== 'string' || typeof message !== 'string') {
      return null;
    }

    const rawDetails = Array.isArray(record['details']) ? record['details'] : [];
    const details = rawDetails
      .filter((item): item is Record<string, unknown> => typeof item === 'object' && item !== null)
      .map((item) => ({
        ...(typeof item['field'] === 'string' ? { field: item['field'] } : {}),
        ...(typeof item['code'] === 'string' ? { code: item['code'] } : {}),
        ...(typeof item['message'] === 'string' ? { message: item['message'] } : {}),
        ...(typeof item['error_id'] === 'string' ? { error_id: item['error_id'] } : {}),
      }));

    return { error: { code, message, details } };
  }

  // Conservo solo los detalles que traen campo y mensaje: en el 500 el detalle
  // es un error_id, que extraigo aparte y no debe aparecer como si fuera un campo.
  private readDetails(envelope: ApiErrorEnvelope): readonly ApiErrorDetail[] {
    return envelope.error.details.flatMap((detail) =>
      typeof detail.field === 'string' && typeof detail.message === 'string'
        ? [{ field: detail.field, code: detail.code ?? 'VALIDACION', message: detail.message }]
        : [],
    );
  }

  // Leo el error_id del cuerpo crudo, porque en el 500 el detalle trae solo ese
  // identificador y no un campo de formulario; lo extraigo antes de filtrar los
  // detalles para no perderlo.
  private readErrorId(body: unknown): string | null {
    if (typeof body !== 'object' || body === null) {
      return null;
    }

    const error = (body as Record<string, unknown>)['error'];

    if (typeof error !== 'object' || error === null) {
      return null;
    }

    const details = (error as Record<string, unknown>)['details'];

    if (!Array.isArray(details)) {
      return null;
    }

    for (const detail of details) {
      if (typeof detail === 'object' && detail !== null) {
        const candidate = (detail as Record<string, unknown>)['error_id'];

        if (typeof candidate === 'string') {
          return candidate;
        }
      }
    }

    return null;
  }

  private kindFor(status: number, code: string): ErrorKind {
    if (code === 'VALIDACION') {
      return 'validation';
    }

    if (AUTHORIZATION_CODES.has(code)) {
      return 'authorization';
    }

    if (BUSINESS_CODES.has(code)) {
      return 'business';
    }

    if (status >= 500) {
      return 'server';
    }

    if (status === 422) {
      return 'validation';
    }

    if (status === 401 || status === 403) {
      return 'authorization';
    }

    if (status === 409 || status === 404) {
      return 'business';
    }

    return 'unknown';
  }

  private codeForStatus(status: number): string {
    switch (status) {
      case 401:
      case 403:
        return 'SIN_PERMISO';
      case 404:
        return 'NO_ENCONTRADO';
      case 422:
        return 'VALIDACION';
      case 429:
        return 'LIMITE_EXCEDIDO';
      default:
        return status >= 500 ? 'ERROR_INTERNO' : 'SOLICITUD_INVALIDA';
    }
  }

  private messageForStatus(status: number): string {
    switch (status) {
      case 401:
        return 'Su sesion expiro. Inicie sesion de nuevo.';
      case 403:
        return 'No tiene permiso para realizar esta accion.';
      case 404:
        return 'El recurso solicitado no existe.';
      case 419:
        return 'La sesion de seguridad expiro. Vuelva a intentarlo.';
      case 422:
        return 'Los datos enviados no son validos.';
      case 429:
        return 'Demasiadas solicitudes. Intente de nuevo mas tarde.';
      default:
        return status >= 500
          ? 'Ocurrio un error inesperado. Intente de nuevo mas tarde.'
          : 'La solicitud no pudo procesarse.';
    }
  }
}
