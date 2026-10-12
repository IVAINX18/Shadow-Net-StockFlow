import type { Warehouse } from './warehouse.model';

// Modelos del RBAC que devuelven /auth/me, /users y /roles (api-design.md §7.1
// a §7.3). Reflejan exactamente los Resources del backend, sin campos internos.

/** Permiso del catalogo, en su forma minima (PermissionResource). */
export interface Permission {
  readonly id: number;
  readonly name: string;
  readonly slug: string;
  readonly module: string;
}

/** Rol con sus permisos (RoleResource los expone solo si vienen cargados). */
export interface Role {
  readonly id: number;
  readonly name: string;
  readonly slug: string;
  readonly description: string | null;
  /** Presente en /auth/me y /roles; ausente en el listado de usuarios. */
  readonly permissions?: readonly Permission[];
}

/** Usuario autenticado con roles, permisos y almacenes (UserResource). */
export interface AuthenticatedUser {
  readonly id: number;
  readonly name: string;
  readonly email: string;
  readonly is_active: boolean;
  readonly last_login_at: string | null;
  readonly roles: readonly Role[];
  /** Slugs planos de permisos, ya aplanados por el backend. */
  readonly permissions: readonly string[];
  readonly warehouses: readonly Warehouse[];
}

/** Usuario de listado, sin la carga pesada de permisos (UserSummaryResource). */
export interface UserSummary {
  readonly id: number;
  readonly name: string;
  readonly email: string;
  readonly is_active: boolean;
  readonly roles: readonly Role[];
}

/** Respuesta de /auth/login y /auth/me. */
export interface AuthResponse {
  readonly user: AuthenticatedUser;
}

/** Credenciales del formulario de acceso. */
export interface LoginCredentials {
  readonly email: string;
  readonly password: string;
}

/**
 * Contraseña y capacidades de la sesion. No declaro constantes de permiso aqui
 * para que la lista siga viviendo solo en el backend (PermissionSlug.php); en el
 * frontend los slugs se consumen como texto.
 */
