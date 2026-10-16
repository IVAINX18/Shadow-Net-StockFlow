import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { AppError } from '../../core/models/app-error.model';
import type { ListParams } from '../../core/models/api.model';
import type { StockRow } from '../../core/models/inventory.model';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../shared/components/data-table/data-table.component';
import type {
  DataTableColumn,
  PageChange,
} from '../../shared/components/data-table/data-table.model';
import { KpiCardComponent } from '../../shared/components/kpi-card/kpi-card.component';
import { StockService } from './stock.service';

/**
 * Existencias por producto y almacen. Lee GET /reports/stock con el permiso
 * reports.view: si el usuario no lo tiene, el guardia de la ruta lo desvia a
 * /sin-acceso y un 403 tardio se muestra como error de permiso, nunca como
 * tabla vacia. Las cifras (quantity, costs, totals) se muestran tal cual.
 */
@Component({
  selector: 'app-stock-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatSelectModule,
    MatSlideToggleModule,
    PageHeaderComponent,
    DataTableComponent,
    KpiCardComponent,
  ],
  templateUrl: './stock-page.component.html',
  styleUrl: './stock-page.component.scss',
})
export class StockPageComponent {
  private readonly stock = inject(StockService);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly columns: readonly DataTableColumn<StockRow>[] = [
    { key: 'sku', header: 'SKU', mono: true, cell: (row) => row.sku },
    { key: 'product_name', header: 'Producto', cell: (row) => row.product_name },
    { key: 'warehouse_code', header: 'Almacen', mono: true, cell: (row) => row.warehouse_code },
    {
      key: 'quantity',
      header: 'Existencia',
      numeric: true,
      cell: (row) => row.quantity,
      status: (row) => (row.below_min ? 'warning' : null),
    },
    {
      key: 'average_cost',
      header: 'Costo prom.',
      numeric: true,
      cell: (row) => row.average_cost,
    },
    {
      key: 'stock_value',
      header: 'Valorizado',
      numeric: true,
      cell: (row) => row.stock_value,
    },
    {
      key: 'min_stock',
      header: 'Minimo',
      numeric: true,
      cell: (row) => row.min_stock ?? '—',
    },
  ];

  protected readonly rows = signal<readonly StockRow[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly pageSize = signal(15);
  protected readonly loading = signal(false);
  protected readonly error = signal<AppError | null>(null);
  protected readonly errorMessage = computed(() => this.error()?.message ?? null);
  protected readonly errorId = computed(() => this.error()?.errorId ?? null);
  protected readonly totalQuantity = signal<string | null>(null);
  protected readonly totalValue = signal<string | null>(null);

  protected readonly belowMinFilter = signal<boolean | null>(null);
  protected readonly filters = this.formBuilder.group({
    warehouseId: [''],
    categoryId: [''],
  });

  public constructor() {
    this.filters.valueChanges.pipe(takeUntilDestroyed()).subscribe(() => {
      this.page.set(1);
      this.load();
    });
    this.load();
  }

  protected onPage(change: PageChange): void {
    this.page.set(change.page);
    this.pageSize.set(change.pageSize);
    this.load();
  }

  protected onBelowMin(value: boolean | null): void {
    this.belowMinFilter.set(value);
    this.page.set(1);
    this.load();
  }

  protected onClearFilters(): void {
    this.belowMinFilter.set(null);
    this.filters.reset({ warehouseId: '', categoryId: '' });
    this.page.set(1);
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    const raw = this.filters.value;
    const belowMin = this.belowMinFilter();
    const params: ListParams = {
      page: this.page(),
      per_page: this.pageSize(),
      ...this.numericFilter('warehouse_id', raw.warehouseId),
      ...this.numericFilter('category_id', raw.categoryId),
    };

    this.stock
      .list(belowMin === null ? params : { ...params, below_min: belowMin })
      .subscribe({
        next: (response) => {
          this.rows.set(response.data);
          this.total.set(response.meta.total);
          this.totalQuantity.set(response.totals.total_quantity);
          this.totalValue.set(response.totals.total_value);
          this.loading.set(false);
        },
        error: (error: unknown) => {
          this.rows.set([]);
          this.total.set(0);
          this.totalQuantity.set(null);
          this.totalValue.set(null);
          this.error.set(error instanceof AppError ? error : null);
          this.loading.set(false);
        },
      });
  }

  private numericFilter(key: string, value: string | null | undefined): ListParams {
    const trimmed = (value ?? '').trim();
    return trimmed === '' || !/^\d+$/.test(trimmed) ? {} : { [key]: Number(trimmed) };
  }
}
