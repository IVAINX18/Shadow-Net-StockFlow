import {
  ApplicationConfig,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners,
  provideZonelessChangeDetection,
} from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { MAT_ICON_DEFAULT_OPTIONS } from '@angular/material/icon';
import { provideRouter, withComponentInputBinding, withInMemoryScrolling } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { inject } from '@angular/core';
import { routes } from './app.routes';
import { environment } from '../environments/environment';
import { API_BASE_URL } from './core/services/api-base-url.token';
import { AuthService } from './core/services/auth.service';
import { authInterceptor } from './core/interceptors/auth.interceptor';
import { errorInterceptor } from './core/interceptors/error.interceptor';

// Configuracion de la aplicacion. El orden de los interceptores importa: el de
// errores va por fuera y el de autenticacion por dentro, para que sea este quien
// vea el 401 crudo del backend antes de normalizarlo (frontend-architecture.md §7).
export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    // Zoneless explicito: el estado se propaga por Signals y no por zona
    // (frontend-architecture.md §3).
    provideZonelessChangeDetection(),
    provideRouter(
      routes,
      withComponentInputBinding(),
      withInMemoryScrolling({ scrollPositionRestoration: 'top', anchorScrolling: 'enabled' }),
    ),
    provideHttpClient(withInterceptors([errorInterceptor, authInterceptor])),
    { provide: API_BASE_URL, useValue: environment.apiBaseUrl },
    // Uso Material Symbols como unica fuente de iconos; no se dibujan SVG a mano.
    { provide: MAT_ICON_DEFAULT_OPTIONS, useValue: { fontSet: 'material-symbols-outlined' } },
    // Restauro la sesion antes de la primera navegacion para que las guardias
    // vean el estado real y no manden al login a una sesion existente.
    provideAppInitializer(() => firstValueFrom(inject(AuthService).bootstrap())),
  ],
};
