import { HttpErrorResponse, type HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { API_BASE_URL } from '../services/api-base-url.token';

// Peticiones cuyo 401 no debe redirigir: el login (credenciales malas) y el
// arranque de sesion (no hay sesion) gestionan su propio 401.
const AUTH_ENDPOINTS = ['/auth/login', '/auth/me'];

// Peticiones de solo lectura que no necesitan cabecera CSRF.
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

/**
 * Interceptor de autenticacion. Anade las credenciales (cookie de sesion) y la
 * cabecera CSRF que exige Sanctum para las peticiones mutadoras, y ante un 401
 * redirige al login conservando la ruta deseada (frontend-architecture.md §7).
 */
export const authInterceptor: HttpInterceptorFn = (request, next) => {
  const baseUrl = inject(API_BASE_URL);
  const router = inject(Router);

  // Solo actuo sobre llamadas a la API propia: asi no adjunto credenciales a
  // terceros ni altero peticiones de plantillas o recursos.
  if (!request.url.startsWith(baseUrl)) {
    return next(request);
  }

  const csrfToken = readXsrfCookie();
  const needsCsrf = !SAFE_METHODS.has(request.method.toUpperCase());

  // `set` en lugar de `append`: sobrescribo cualquier token viejo tras una
  // renovacion CSRF, en lugar de acumular cabeceras duplicadas.
  const headers = needsCsrf && csrfToken !== null
    ? request.headers.set('X-XSRF-TOKEN', csrfToken)
    : request.headers;

  const authorizedRequest = request.clone({ withCredentials: true, headers });

  return next(authorizedRequest).pipe(
    catchError((error: unknown) => {
      // La autenticacion real vive en el backend; aqui solo mejoro la
      // navegacion enviando al login cuando la sesion ya no vale.
      if (
        error instanceof HttpErrorResponse &&
        error.status === 401 &&
        !AUTH_ENDPOINTS.some((endpoint) => request.url.includes(endpoint))
      ) {
        redirectToLogin(router);
      }

      return throwError(() => error);
    }),
  );
};

// Leo el token CSRF de la cookie XSRF-TOKEN que Laravel deja para la SPA
// (api-design.md §2). La cookie no es HttpOnly a proposito: el cliente necesita
// enviarla de vuelta en la cabecera X-XSRF-TOKEN.
function readXsrfCookie(): string | null {
  const prefix = 'XSRF-TOKEN=';
  const match = document.cookie
    .split('; ')
    .find((cookie) => cookie.startsWith(prefix));

  if (match === undefined) {
    return null;
  }

  try {
    return decodeURIComponent(match.slice(prefix.length));
  } catch {
    return null;
  }
}

function redirectToLogin(router: Router): void {
  const current = router.url;

  if (current.startsWith('/login')) {
    return;
  }

  void router.navigate(['/login'], { queryParams: { redirect: current } });
}
