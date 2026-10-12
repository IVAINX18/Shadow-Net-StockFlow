import {
  HttpContextToken,
  HttpErrorResponse,
  type HttpInterceptorFn,
} from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, from, switchMap, throwError } from 'rxjs';
import { ApiService } from '../services/api.service';
import { ErrorService } from '../services/error.service';

// Marca la peticion ya reintentada: evita un bucle si el 419 se repite tras
// renovar la cookie.
const CSRF_RETRIED = new HttpContextToken<boolean>(() => false);

/**
 * Interceptor de errores. Normaliza todo fallo a AppError (validacion por
 * campo, autorizacion, negocio o servidor) y resuelve el 419 del CSRF renovando
 * la cookie y reintentando una sola vez (api-design.md §5).
 */
export const errorInterceptor: HttpInterceptorFn = (request, next) => {
  const errorService = inject(ErrorService);
  const api = inject(ApiService);

  return next(request).pipe(
    catchError((error: unknown) => {
      const isCsrfExpired = error instanceof HttpErrorResponse && error.status === 419;

      if (isCsrfExpired && !request.context.get(CSRF_RETRIED)) {
        // Renuevo la cookie CSRF y reintento la misma peticion una vez; el
        // interceptor de autenticacion volvera a leer el token ya renovado.
        return from(api.bootstrapCsrf()).pipe(
          switchMap(() =>
            next(request.clone({ context: request.context.set(CSRF_RETRIED, true) })),
          ),
          catchError((retryError: unknown) => throwError(() => errorService.toAppError(retryError))),
        );
      }

      return throwError(() => errorService.toAppError(error));
    }),
  );
};
