import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { AppError } from '../../core/models/app-error.model';
import type { KardexMovement } from '../../core/models/inventory.model';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../shared/components/data-table/data-table.component';
import type {
  DataTableColumn,
  PageChange,
} from '../../shared/components/data-table/data-table.model';
import { KpiCardComponent } from '../../shared/components/kpi-card/kpi-card.component';
import { KardexService } from './kardex.service';

/**
 * Kardex por producto y almacen. El backend exige product_id y warehouse_id
 * (422 VALIDACION sin ellos), asi que no consulto hasta tener ambos ids
 * validos: la primera pantalla es un vacio compuesto que explica como
 * poblarla, no un error. El saldo de cada fila (balance_after) se muestra
 * tal cual llega, como cadena.
 */
@Component({
  selector: 'app-kardex-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    PageHeaderComponent,
    DataTableComponent,
    KpiCardComponent,
  ],
  templateUrl: './kardex-page.component.html',
  styleUrl: './kardex-page.component.scss',
})
export class KardexPageComponent {
  private readonly kardex = inject(KardexService);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly queryForm = this.formBuilder.group({
    productId: ['', [Validators.required, Validators.pattern(/^\d+$/)]],
    warehouseId: ['', [Validators.required, Validators.pattern(/^\d+$/)]],
    from: [''],
    to: [''],
  });

  protected readonly columns: readonly DataTableColumn<KardexMovement>[] = [
    { key: 'id', header: 'Movimiento', mono: true, cell: (row) => `#${row.id}` },
    { key: 'direction', header: 'Direccion', cell: (row) => row.direction },
    {
      key: 'quantity_delta',
      header: 'Cantidad',
      numeric: true,
      cell: (row) => row.quantity_delta,
    },
    {
      key: 'unit_cost',
      header: 'Costo unitario',
      numeric: true,
      cell: (row) => row.unit_cost,
    },
    {
      key: 'balance_after',
      header: 'Saldo resultante',
      numeric: true,
      cell: (row) => row.balance_after,
    },
    { key: 'created_at', header: 'Fecha', cell: (row) => row.created_at },
  ];

  protected readonly rows = signal<readonly KardexMovement[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly pageSize = signal(15);
  protected readonly loading = signal(false);
  protected readonly queried = signal(false);
  protected readonly error = signal<AppError | null>(null);
  protected readonly errorMessage = computed(() => this.error()?.message ?? null);
  protected readonly errorId = computed(() => this.error()?.errorId ?? null);
  protected readonly initialBalance = signal<string | null>(null);
  protected readonly finalBalance = signal<string | null>(null);

  protected onQuery(): void {
    this.queryForm.markAllAsTouched();

    if (this.queryForm.invalid) {
      return;
    }

    this.page.set(1);
    this.load();
  }

  protected onClear(): void {
    this.queryForm.reset({ productId: '', warehouseId: '', from: '', to: '' });
    this.queried.set(false);
    this.rows.set([]);
    this.total.set(0);
    this.error.set(null);
    this.initialBalance.set(null);
    this.finalBalance.set(null);
  }

  protected onPage(change: PageChange): void {
    this.page.set(change.page);
    this.pageSize.set(change.pageSize);
    this.load();
  }

  protected load(): void {
    const raw = this.queryForm.value;
    const productId = Number((raw.productId ?? '').trim());
    const warehouseId = Number((raw.warehouseId ?? '').trim());

    if (!Number.isInteger(productId) || productId <= 0 || !Number.isInteger(warehouseId) || warehouseId <= 0) {
      return;
    }

    this.loading.set(true);
    this.error.set(null);
    this.queried.set(true);

    const from = (raw.from ?? '').trim();
    const to = (raw.to ?? '').trim();

    this.kardex
      .list({
        product_id: productId,
        warehouse_id: warehouseId,
        page: this.page(),
        per_page: this.pageSize(),
        ...(from === '' ? {} : { from }),
        ...(to === '' ? {} : { to }),
      })
      .subscribe({
        next: (response) => {
          this.rows.set(response.data);
          this.total.set(response.meta.total);
          this.initialBalance.set(response.balances.initial_balance);
          this.finalBalance.set(response.balances.final_balance);
          this.loading.set(false);
        },
        error: (error: unknown) => {
          this.rows.set([]);
          this.total.set(0);
          this.initialBalance.set(null);
          this.finalBalance.set(null);
          this.error.set(error instanceof AppError ? error : null);
          this.loading.set(false);
        },
      });
  }
}
