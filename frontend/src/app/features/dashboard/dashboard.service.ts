import { Injectable, inject } from '@angular/core';
import { Observable, forkJoin, of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { AuthService } from '../../core/services/auth.service';
import { WarehousesService } from '../warehouses/warehouses.service';
import { UsersService } from '../users/users.service';
import { RolesService } from '../roles/roles.service';

/** Indicador con dato real de la Fase 1. `value` es null si el rol no permite consultarlo. */
export interface DashboardCounter {
  readonly key: string;
  readonly label: string;
  readonly icon: string;
  readonly value: number | null;
  readonly hint: string;
}

/** Indicador que necesita una fase posterior; se declara, no se simula. */
export interface PendingDependency {
  readonly label: string;
  readonly phase: string;
  readonly detail: string;
  readonly endpoint: string;
}

/** Conjunto de indicadores del tablero. */
export interface DashboardIndicators {
  readonly counters: readonly DashboardCounter[];
  readonly pending: readonly PendingDependency[];
}

// Indicadores que hoy NO tienen API: se listan con su fase y su endpoint futuro
// en lugar de mostrar cifras, tendencias o graficos inventados
// (design-system.md §11 y §12).
const PENDING_DEPENDENCIES: readonly PendingDependency[] = [
  {
    label: 'Productos activos',
    phase: 'Fase 2',
    detail: 'Requiere el catalogo de productos.',
    endpoint: 'GET /api/v1/products',
  },
  {
    label: 'Existencias por almacen',
    phase: 'Fase 3',
    detail: 'Requiere el kardex y las existencias por almacen.',
    endpoint: 'GET /api/v1/inventory/kardex',
  },
  {
    label: 'Movimientos recientes',
    phase: 'Fase 3',
    detail: 'Requiere los documentos de inventario.',
    endpoint: 'GET /api/v1/inventory-documents',
  },
  {
    label: 'Productos bajo minimo',
    phase: 'Fase 3',
    detail: 'Requiere el reporte de existencias y la configuracion min/max.',
    endpoint: 'GET /api/v1/reports/stock?below_min=true',
  },
  {
    label: 'Valorizacion del inventario',
    phase: 'Fase 4',
    detail: 'Requiere la valorizacion por promedio ponderado.',
    endpoint: 'GET /api/v1/reports/valuation',
  },
  {
    label: 'Resumen operativo del tablero',
    phase: 'Fase 4',
    detail: 'Resumen agregado que dependera de las existencias y los movimientos.',
    endpoint: 'GET /api/v1/dashboard/summary',
  },
];

/**
 * Reune los indicadores del tablero. Solo consulta los endpoints que existen
 * hoy (almacenes, usuarios, roles y permisos) y respeta los permisos: si el rol
 * no permite un conteo, el indicador queda sin dato en lugar de fallar entero.
 */
@Injectable({ providedIn: 'root' })
export class DashboardService {
  private readonly auth = inject(AuthService);
  private readonly warehouses = inject(WarehousesService);
  private readonly users = inject(UsersService);
  private readonly roles = inject(RolesService);

  /** Carga los indicadores disponibles y declara los pendientes. */
  public load(): Observable<DashboardIndicators> {
    const canReadWarehouses = this.auth.hasPermission('warehouses.view');
    const canReadUsers = this.auth.hasPermission('users.view');
    const canReadRoles = this.auth.hasPermission('roles.view');

    return forkJoin({
      warehouses: this.when(canReadWarehouses, () => this.warehouses.count()),
      activeWarehouses: this.when(canReadWarehouses, () => this.warehouses.count({ is_active: true })),
      users: this.when(canReadUsers, () => this.users.count()),
      activeUsers: this.when(canReadUsers, () => this.users.count({ is_active: true })),
      roles: this.when(canReadRoles, () => this.roles.count()),
      permissions: this.when(canReadRoles, () => this.roles.countPermissions()),
    }).pipe(
      map((counts) => ({
        counters: this.buildCounters(counts),
        pending: PENDING_DEPENDENCIES,
      })),
    );
  }

  private buildCounters(counts: Record<string, number | null>): readonly DashboardCounter[] {
    const warehouseTotal = counts['warehouses'];
    const userTotal = counts['users'];

    return [
      {
        key: 'active-warehouses',
        label: 'Almacenes activos',
        icon: 'warehouse',
        value: counts['activeWarehouses'],
        hint: warehouseTotal === null ? 'Sin permiso para consultar almacenes.' : `De ${warehouseTotal} en total.`,
      },
      {
        key: 'active-users',
        label: 'Usuarios activos',
        icon: 'group',
        value: counts['activeUsers'],
        hint: userTotal === null ? 'Sin permiso para consultar usuarios.' : `De ${userTotal} en total.`,
      },
      {
        key: 'roles',
        label: 'Roles',
        icon: 'badge',
        value: counts['roles'],
        hint: counts['roles'] === null ? 'Sin permiso para consultar roles.' : 'Roles definidos en el sistema.',
      },
      {
        key: 'permissions',
        label: 'Permisos',
        icon: 'key',
        value: counts['permissions'],
        hint: counts['permissions'] === null ? 'Sin permiso para consultar el catalogo.' : 'Permisos del catalogo RBAC.',
      },
    ];
  }

  // Solo disparo la consulta si el rol tiene el permiso; si no, devuelvo null
  // sin llamar al backend. Un 403 inesperado tambien se degrada a null para no
  // tumbar todo el tablero por un indicador.
  private when(allowed: boolean, factory: () => Observable<number>): Observable<number | null> {
    if (!allowed) {
      return of(null);
    }

    return factory().pipe(catchError(() => of(null)));
  }
}
