import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import type { Signal } from '@angular/core';
import type { Observable } from 'rxjs';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../shared/components/data-table/data-table.component';
import type {
  DataTableColumn,
  PageChange,
  SortChange,
  SortDirection,
} from '../../shared/components/data-table/data-table.model';
import { UsersService } from '../users/users.service';
import { RolesService } from '../roles/roles.service';
import type { Role, UserSummary } from '../../core/models/user.model';
import type { ListParams, PaginatedResponse } from '../../core/models/api.model';
import { AppError } from '../../core/models/app-error.model';

/** Estado de listado reutilizable por las dos tablas de esta pantalla. */
interface ListState<T> {
  readonly rows: Signal<readonly T[]>;
  readonly total: Signal<number>;
  readonly page: Signal<number>;
  readonly pageSize: Signal<number>;
  readonly sortKey: Signal<string | null>;
  readonly sortDirection: Signal<SortDirection>;
  readonly loading: Signal<boolean>;
  readonly error: Signal<AppError | null>;
  load(): void;
  sort(change: SortChange): void;
  goToPage(change: PageChange): void;
}

// Concentro la logica de carga, orden y paginacion en una sola funcion para que
// las dos tablas de la pantalla compartan comportamiento sin duplicarlo.
function createListState<T>(
  fetch: (params: ListParams) => Observable<PaginatedResponse<T>>,
): ListState<T> {
  const rows = signal<readonly T[]>([]);
  const total = signal(0);
  const page = signal(1);
  const pageSize = signal(15);
  const sortKey = signal<string | null>('name');
  const sortDirection = signal<SortDirection>('asc');
  const loading = signal(false);
  const error = signal<AppError | null>(null);

  const sortParam = (): string => {
    const key = sortKey() ?? 'name';
    return sortDirection() === 'desc' ? `-${key}` : key;
  };

  const load = (): void => {
    loading.set(true);
    error.set(null);

    fetch({ page: page(), per_page: pageSize(), sort: sortParam() }).subscribe({
      next: (response) => {
        rows.set(response.data);
        total.set(response.meta.total);
        loading.set(false);
      },
      error: (cause: unknown) => {
        rows.set([]);
        total.set(0);
        error.set(cause instanceof AppError ? cause : null);
        loading.set(false);
      },
    });
  };

  return {
    rows,
    total,
    page,
    pageSize,
    sortKey,
    sortDirection,
    loading,
    error,
    load,
    sort: (change: SortChange): void => {
      sortKey.set(change.key);
      sortDirection.set(change.direction);
      page.set(1);
      load();
    },
    goToPage: (change: PageChange): void => {
      page.set(change.page);
      pageSize.set(change.pageSize);
      load();
    },
  };
}

/**
 * Seccion de Usuarios y roles (API real de la Fase 1, §7.2 y §7.3). Presenta
 * dos tablas: cuentas y roles con su numero de permisos. Ambas implementan los
 * cuatro estados frente al backend real.
 */
@Component({
  selector: 'app-users-roles-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [PageHeaderComponent, DataTableComponent],
  templateUrl: './users-roles-page.component.html',
  styleUrl: './users-roles-page.component.scss',
})
export class UsersRolesPageComponent {
  private readonly users = inject(UsersService);
  private readonly roles = inject(RolesService);

  protected readonly userColumns: readonly DataTableColumn<UserSummary>[] = [
    { key: 'name', header: 'Nombre', sortable: true, cell: (row) => row.name },
    { key: 'email', header: 'Correo', sortable: true, cell: (row) => row.email },
    {
      key: 'roles',
      header: 'Roles',
      cell: (row) => row.roles.map((role) => role.name).join(', ') || 'Sin rol',
    },
    {
      key: 'is_active',
      header: 'Estado',
      cell: (row) => (row.is_active ? 'Activo' : 'Inactivo'),
      status: (row) => (row.is_active ? 'success' : 'neutral'),
    },
  ];

  protected readonly roleColumns: readonly DataTableColumn<Role>[] = [
    { key: 'name', header: 'Rol', sortable: true, cell: (row) => row.name },
    { key: 'slug', header: 'Slug', cell: (row) => row.slug },
    { key: 'description', header: 'Descripcion', cell: (row) => row.description ?? 'Sin descripcion' },
    {
      key: 'permissions',
      header: 'Permisos',
      numeric: true,
      cell: (row) => String(row.permissions?.length ?? 0),
    },
  ];

  private readonly usersState = createListState<UserSummary>((params) => this.users.list(params));
  private readonly rolesState = createListState<Role>((params) => this.roles.list(params));

  protected readonly userRows = this.usersState.rows;
  protected readonly userTotal = this.usersState.total;
  protected readonly userPage = this.usersState.page;
  protected readonly userPageSize = this.usersState.pageSize;
  protected readonly userSortKey = this.usersState.sortKey;
  protected readonly userSortDirection = this.usersState.sortDirection;
  protected readonly userLoading = this.usersState.loading;
  protected readonly userErrorMessage = computed(() => this.usersState.error()?.message ?? null);
  protected readonly userErrorId = computed(() => this.usersState.error()?.errorId ?? null);

  protected readonly roleRows = this.rolesState.rows;
  protected readonly roleTotal = this.rolesState.total;
  protected readonly rolePage = this.rolesState.page;
  protected readonly rolePageSize = this.rolesState.pageSize;
  protected readonly roleSortKey = this.rolesState.sortKey;
  protected readonly roleSortDirection = this.rolesState.sortDirection;
  protected readonly roleLoading = this.rolesState.loading;
  protected readonly roleErrorMessage = computed(() => this.rolesState.error()?.message ?? null);
  protected readonly roleErrorId = computed(() => this.rolesState.error()?.errorId ?? null);

  public constructor() {
    this.usersState.load();
    this.rolesState.load();
  }

  protected onUserSort(change: SortChange): void {
    this.usersState.sort(change);
  }

  protected onUserPage(change: PageChange): void {
    this.usersState.goToPage(change);
  }

  protected onRoleSort(change: SortChange): void {
    this.rolesState.sort(change);
  }

  protected onRolePage(change: PageChange): void {
    this.rolesState.goToPage(change);
  }
}
