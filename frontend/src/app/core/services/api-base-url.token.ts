import { InjectionToken } from '@angular/core';

// Token de inyeccion de la URL base de la API, como pide
// docs/architecture/frontend-architecture.md §6 y §9. Los servicios la reciben
// por este token y nunca la leen del entorno directamente, para que la
// configuracion del entorno activo sea lo unico que cambie entre despliegues.
export const API_BASE_URL = new InjectionToken<string>('API_BASE_URL', {
  providedIn: 'root',
  factory: () => {
    throw new Error(
      'API_BASE_URL no esta provisto. app.config.ts debe registrarlo desde environment.apiBaseUrl.',
    );
  },
});
