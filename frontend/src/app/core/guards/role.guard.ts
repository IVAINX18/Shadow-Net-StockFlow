import { inject } from '@angular/core';
import { type CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Guardia de navegacion por permiso (documentada como role.guard en
 * frontend-architecture.md §2 y §8). Comprueba los slugs de permiso que el
 * backend asigna a los roles, porque es el permiso, no el nombre del rol, lo
 * que determina que se muestra. Nunca sustituye a la autorizacion del backend:
 * aunque el usuario manipule la URL, la Policy del servidor responde 403.
 */
export const roleGuard = (requiredSlugs: readonly string[]): CanActivateFn => {
  return () => {
    const auth = inject(AuthService);
    const router = inject(Router);

    if (auth.hasAnyPermission(requiredSlugs)) {
      return true;
    }

    // Reenvio a la pantalla de acceso denegado, que explica el motivo en lugar
    // de mostrar una tabla vacia o un error de red.
    return router.createUrlTree(['/sin-acceso']);
  };
};
