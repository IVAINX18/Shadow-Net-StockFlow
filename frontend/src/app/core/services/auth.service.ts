import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, finalize, of, switchMap, tap } from 'rxjs';
import { ApiService } from './api.service';
import type {
  AuthenticatedUser,
  AuthResponse,
  LoginCredentials,
  Role,
} from '../models/user.model';
import type { Warehouse } from '../models/warehouse.model';

// Tipado del resultado del arranque: distingo si habia sesion de si el arranque
// fallo por red, para no redirigir al login cuando el problema fue la conexion.
export type BootstrapResult = 'authenticated' | 'anonymous' | 'unreachable';

/**
 * Estado de sesion del usuario. Los componentes leen Signals de solo lectura y
 * derivan con computed(); AuthService es el unico que muta el estado. Nunca
 * decide autorizacion: solo refleja lo que el backend ya autorizo.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);

  private readonly session = signal<AuthenticatedUser | null>(null);
  private readonly loading = signal(false);

  /** Usuario autenticado, o null si no hay sesion. */
  public readonly user = this.session.asReadonly();
  /** Indica que el arranque de sesion esta en curso. */
  public readonly isLoading = this.loading.asReadonly();
  /** Hay sesion activa. */
  public readonly isAuthenticated = computed(() => this.session() !== null);
  /** Almacenes a los que el usuario tiene acceso, segun el backend. */
  public readonly warehouses = computed<readonly Warehouse[]>(() => this.session()?.warehouses ?? []);
  /** Roles del usuario. */
  public readonly roles = computed<readonly Role[]>(() => this.session()?.roles ?? []);
  /**
   * Indice de permisos para consultas O(1) en la navegacion. Es un Set derivado
   * del arreglo de slugs que devuelve /auth/me; no sustituye la autorizacion del
   * backend, solo filtra lo que se muestra.
   */
  private readonly permissionSet = computed<ReadonlySet<string>>(
    () => new Set(this.session()?.permissions ?? []),
  );

  /** Indica si el usuario tiene el permiso (slug) indicado. Navegacion, no autorizacion. */
  public hasPermission(slug: string): boolean {
    return this.permissionSet().has(slug);
  }

  /** Indica si el usuario tiene al menos uno de los permisos indicados. */
  public hasAnyPermission(slugs: readonly string[]): boolean {
    const set = this.permissionSet();
    return slugs.some((slug) => set.has(slug));
  }

  /**
   * Restaura la sesion al arrancar la aplicacion consultando GET /auth/me. No
   * lanza: un 401 significa "sin sesion" y un fallo de red se distingue para no
   * mandar al login a un usuario cuya API esta caida.
   */
  public bootstrap(): Observable<BootstrapResult> {
    this.loading.set(true);

    return this.api.get<AuthResponse>('/auth/me').pipe(
      tap((response) => this.session.set(response.user)),
      switchMap(() => of<BootstrapResult>('authenticated')),
      catchError((error: unknown) => {
        // Un 401/419 limpia la sesion; cualquier otro fallo deja el estado como
        // "sin conexion" y conserva al usuario en la pantalla actual.
        if (isUnauthorized(error)) {
          this.session.set(null);
          return of<BootstrapResult>('anonymous');
        }

        return of<BootstrapResult>('unreachable');
      }),
      finalize(() => this.loading.set(false)),
    );
  }

  /**
   * Inicia sesion. Primero solicita la cookie CSRF de Sanctum (C-06) y despues
   * envia las credenciales; solo entonces abre la sesion en el estado.
   */
  public login(credentials: LoginCredentials): Observable<AuthenticatedUser> {
    this.loading.set(true);

    return this.api.bootstrapCsrf().pipe(
      switchMap(() => this.api.post<AuthResponse>('/auth/login', credentials)),
      tap((response) => this.session.set(response.user)),
      switchMap((response) => of(response.user)),
      finalize(() => this.loading.set(false)),
    );
  }

  /** Cierra la sesion en el servidor y limpia el estado local. */
  public logout(): Observable<void> {
    this.loading.set(true);

    return this.api.post<void>('/auth/logout', null).pipe(
      catchError(() => of(undefined)),
      tap(() => {
        this.session.set(null);
        void this.router.navigate(['/login']);
      }),
      finalize(() => this.loading.set(false)),
      switchMap(() => of(undefined)),
    );
  }
}

// Un 401 o 419 durante el arranque significan que no hay sesion valida; el
// resto (red, 500) no debe interpretarse como "cerrar sesion".
function isUnauthorized(error: unknown): boolean {
  const status = (error as { status?: unknown }).status;
  return status === 401 || status === 419;
}
