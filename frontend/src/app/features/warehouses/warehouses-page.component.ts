import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import {
  DataTableComponent,
} from '../../shared/components/data-table/data-table.component';
import type {
  DataTableColumn,
  PageChange,
  SortChange,
  SortDirection,
} from '../../shared/components/data-table/data-table.model';
import { WarehousesService } from './warehouses.service';
import type { Warehouse } from '../../core/models/warehouse.model';
import { AppError } from '../../core/models/app-error.model';

/**
 * Listado de almacenes (API real de la Fase 1). Implementa los cuatro estados
 * (carga, vacio, error, datos), ordenamiento y paginacion contra el contrato
 * del §7.4 sin inventar datos.
 */
@Component({
  selector: 'app-warehouses-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [PageHeaderComponent, DataTableComponent],
  template: `
    <app-page-header
      title="Almacenes"
      subtitle="Almacenes visibles segun el alcance de su cuenta."
    />

    <app-data-table
      [columns]="columns"
      [rows]="rows()"
      [total]="total()"
      [page]="page()"
      [pageSize]="pageSize()"
      [loading]="loading()"
      [error]="errorMessage()"
      [errorId]="errorId()"
      [sortKey]="sortKey()"
      [sortDirection]="sortDirection()"
      emptyIcon="warehouse"
      emptyTitle="Sin almacenes visibles"
      emptyMessage="Su cuenta no tiene almacenes asignados o no existe ninguno registrado todavia."
      (sortChange)="onSort($event)"
      (pageChange)="onPage($event)"
      (retry)="load()"
    />
  `,
})
export class WarehousesPageComponent {
  private readonly service = inject(WarehousesService);

  protected readonly columns: readonly DataTableColumn<Warehouse>[] = [
    { key: 'code', header: 'Codigo', sortable: true, cell: (row) => row.code },
    { key: 'name', header: 'Nombre', sortable: true, cell: (row) => row.name },
    { key: 'city', header: 'Ciudad', cell: (row) => row.city ?? 'Sin ciudad' },
    {
      key: 'is_active',
      header: 'Estado',
      cell: (row) => (row.is_active ? 'Activo' : 'Inactivo'),
      status: (row) => (row.is_active ? 'success' : 'neutral'),
    },
  ];

  protected readonly rows = signal<readonly Warehouse[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly pageSize = signal(15);
  protected readonly sortKey = signal<string | null>('created_at');
  protected readonly sortDirection = signal<SortDirection>('asc');
  protected readonly loading = signal(false);
  protected readonly error = signal<AppError | null>(null);

  protected readonly errorMessage = computed(() => this.error()?.message ?? null);
  protected readonly errorId = computed(() => this.error()?.errorId ?? null);

  public constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    this.service
      .list({
        page: this.page(),
        per_page: this.pageSize(),
        sort: this.sortParam(),
      })
      .subscribe({
        next: (response) => {
          this.rows.set(response.data);
          this.total.set(response.meta.total);
          this.loading.set(false);
        },
        error: (error: unknown) => {
          this.rows.set([]);
          this.total.set(0);
          this.error.set(error instanceof AppError ? error : null);
          this.loading.set(false);
        },
      });
  }

  protected onSort(change: SortChange): void {
    this.sortKey.set(change.key);
    this.sortDirection.set(change.direction);
    this.page.set(1);
    this.load();
  }

  protected onPage(change: PageChange): void {
    this.page.set(change.page);
    this.pageSize.set(change.pageSize);
    this.load();
  }

  // La API ordena con `-` para descendente, como fija api-design.md §4.
  private sortParam(): string {
    const key = this.sortKey();

    if (key === null) {
      return 'created_at';
    }

    return this.sortDirection() === 'desc' ? `-${key}` : key;
  }
}
