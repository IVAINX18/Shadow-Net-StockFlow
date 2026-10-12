import { inject } from '@angular/core';
import { type CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Guardia de navegacion (no de autorizacion). Si no hay sesion, redirige al
 * login conservando la ruta deseada. Recuerda: la autorizacion real vive en el
 * backend (security-design.md §1); esta guardia solo mejora la navegacion.
 */
export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (auth.isAuthenticated()) {
    return true;
  }

  return router.createUrlTree(['/login'], {
    queryParams: { redirect: state.url },
  });
};
