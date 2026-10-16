import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { AppError } from '../../core/models/app-error.model';
import type { ListParams } from '../../core/models/api.model';
import type {
  InventoryDocument,
  InventoryDocumentStatus,
  InventoryDocumentType,
} from '../../core/models/inventory.model';
import type { ChipTone } from '../../shared/components/status-chip/status-chip.component';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../shared/components/data-table/data-table.component';
import type {
  DataTableAction,
  DataTableColumn,
  PageChange,
  SortChange,
  SortDirection,
} from '../../shared/components/data-table/data-table.model';
import {
  ConfirmDialogComponent,
  type ConfirmDialogData,
} from '../../shared/components/confirm-dialog/confirm-dialog.component';
import { InventoryDocumentsService } from './inventory-documents.service';

const TYPE_LABELS: Readonly<Record<InventoryDocumentType, string>> = {
  entrada: 'Entrada',
  salida: 'Salida',
  ajuste: 'Ajuste',
  transferencia: 'Transferencia',
};

const STATUS_TONES: Readonly<Record<InventoryDocumentStatus, ChipTone>> = {
  borrador: 'neutral',
  confirmado: 'success',
  revertido: 'warning',
  descartado: 'error',
};

const STATUS_LABELS: Readonly<Record<InventoryDocumentStatus, string>> = {
  borrador: 'Borrador',
  confirmado: 'Confirmado',
  revertido: 'Revertido',
  descartado: 'Descartado',
};

const SORTABLE_COLUMNS = new Set(['doc_number', 'created_at']);

/**
 * Documentos de inventario. La ruta /inventario los lista todos y la ruta
 * /transferencias reutiliza esta misma pagina con type=transferencia fijo y
 * sin acciones de ciclo de vida: es solo lectura. Todo filtro y orden viaja
 * al servidor; el unico sort valido medido es doc_number y created_at.
 */
@Component({
  selector: 'app-documents-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatSelectModule,
    PageHeaderComponent,
    DataTableComponent,
  ],
  templateUrl: './documents-page.component.html',
  styleUrl: './documents-page.component.scss',
})
export class DocumentsPageComponent {
  private readonly documents = inject(InventoryDocumentsService);
  private readonly auth = inject(AuthService);
  private readonly dialog = inject(MatDialog);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly formBuilder = inject(FormBuilder);

  // La ruta de transferencias fija el tipo y esconde las acciones: el alta,
  // la edicion y la carga inicial no son parte de esta unidad y no las finjo
  // con botones que no hacen nada.
  protected readonly fixedType = signal<InventoryDocumentType | null>(
    (this.route.snapshot.data['fixedType'] as InventoryDocumentType | undefined) ?? null,
  );
  protected readonly pageTitle = signal<string>(
    (this.route.snapshot.data['pageTitle'] as string | undefined) ?? 'Inventario',
  );
  protected readonly pageSubtitle = signal<string>(
    (this.route.snapshot.data['pageSubtitle'] as string | undefined) ??
      'Documentos de inventario y su ciclo de vida.',
  );

  protected readonly canConfirm = this.auth.hasPermission('inventory.confirm');
  protected readonly canCancel = this.auth.hasPermission('inventory.create');
  protected readonly canReverse = this.auth.hasPermission('inventory.reverse');

  protected readonly columns: readonly DataTableColumn<InventoryDocument>[] = [
    { key: 'doc_number', header: 'Documento', sortable: true, mono: true, cell: (row) => row.doc_number },
    {
      key: 'type',
      header: 'Tipo',
      cell: (row) => TYPE_LABELS[row.type] ?? row.type,
    },
    {
      key: 'status',
      header: 'Estado',
      cell: (row) => STATUS_LABELS[row.status] ?? row.status,
      status: (row) => STATUS_TONES[row.status] ?? null,
    },
    {
      key: 'origin',
      header: 'Origen',
      cell: (row) => (row.origin_warehouse_id === null ? '—' : `#${row.origin_warehouse_id}`),
    },
    {
      key: 'destination',
      header: 'Destino',
      cell: (row) => (row.destination_warehouse_id === null ? '—' : `#${row.destination_warehouse_id}`),
    },
    { key: 'created_at', header: 'Creado', sortable: true, cell: (row) => row.created_at },
  ];

  protected readonly rows = signal<readonly InventoryDocument[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly pageSize = signal(15);
  protected readonly sortKey = signal<string>('created_at');
  protected readonly sortDirection = signal<SortDirection>('desc');
  protected readonly loading = signal(false);
  protected readonly error = signal<AppError | null>(null);
  protected readonly errorMessage = computed(() => this.error()?.message ?? null);
  protected readonly errorId = computed(() => this.error()?.errorId ?? null);

  protected readonly typeFilter = signal<InventoryDocumentType | null>(this.fixedType());
  protected readonly statusFilter = signal<InventoryDocumentStatus | null>(null);
  protected readonly notice = signal<string | null>(null);
  protected readonly filters = this.formBuilder.group({
    originWarehouse: [''],
    destinationWarehouse: [''],
    from: [''],
    to: [''],
  });

  public constructor() {
    this.filters.valueChanges.pipe(takeUntilDestroyed()).subscribe(() => {
      this.page.set(1);
      this.load();
    });
    this.load();
  }

  protected actions(): readonly DataTableAction<InventoryDocument>[] {
    if (this.fixedType() !== null) {
      return [this.viewAction];
    }

    return [this.viewAction, this.confirmAction, this.cancelAction, this.reverseAction];
  }

  private readonly viewAction: DataTableAction<InventoryDocument> = {
    id: 'view',
    label: 'Ver detalle del documento',
    icon: 'visibility',
    run: (row) => this.openDetail(row),
  };

  private readonly confirmAction: DataTableAction<InventoryDocument> = {
    id: 'confirm',
    label: 'Confirmar documento',
    icon: 'check_circle',
    hidden: (row) => !this.canConfirm || row.status !== 'borrador',
    run: (row) => this.confirmDocument(row),
  };

  private readonly cancelAction: DataTableAction<InventoryDocument> = {
    id: 'cancel',
    label: 'Descartar borrador',
    icon: 'cancel',
    hidden: (row) => !this.canCancel || row.status !== 'borrador',
    run: (row) => this.cancelDocument(row),
  };

  private readonly reverseAction: DataTableAction<InventoryDocument> = {
    id: 'reverse',
    label: 'Revertir documento confirmado',
    icon: 'history',
    hidden: (row) => !this.canReverse || row.status !== 'confirmado',
    run: (row) => this.openDetail(row),
  };

  protected onSort(change: SortChange): void {
    // Solo doc_number y created_at existen en el sort del backend: cualquier
    // otra clave devolveria 422, asi que la ignoro en lugar de pedirla.
    if (!SORTABLE_COLUMNS.has(change.key)) {
      return;
    }

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

  protected onTypeFilter(value: InventoryDocumentType | null): void {
    this.typeFilter.set(value);
    this.page.set(1);
    this.load();
  }

  protected onStatusFilter(value: InventoryDocumentStatus | null): void {
    this.statusFilter.set(value);
    this.page.set(1);
    this.load();
  }

  protected onClearFilters(): void {
    this.typeFilter.set(this.fixedType());
    this.statusFilter.set(null);
    this.filters.reset({ originWarehouse: '', destinationWarehouse: '', from: '', to: '' });
    this.page.set(1);
    this.load();
  }

  protected openDetail(document: InventoryDocument): void {
    void this.router.navigate(['/inventario', document.uuid]);
  }

  protected confirmDocument(document: InventoryDocument): void {
    const data: ConfirmDialogData = {
      title: 'Confirmar documento',
      message:
        `El documento ${document.doc_number} generara sus movimientos de forma atomica. ` +
        'Si el stock no alcanza, la confirmacion se rechaza sin escribir nada.',
      confirmLabel: 'Confirmar',
      confirmColor: 'primary',
    };
    const dialogRef = this.dialog.open<ConfirmDialogComponent, ConfirmDialogData, boolean>(
      ConfirmDialogComponent,
      { width: '440px', maxWidth: 'calc(100vw - 32px)', data },
    );

    dialogRef.afterClosed().subscribe((confirmed) => {
      if (confirmed === true) {
        // Cada confirmacion lleva su propia clave de idempotencia (la genera
        // el servicio): un reintento tras un fallo de red no duplica el asiento.
        this.documents.confirm(document.uuid).subscribe({
          next: () => {
            this.notice.set(null);
            this.load();
          },
          error: (error: unknown) => {
            this.notice.set(this.businessMessage(error));
          },
        });
      }
    });
  }

  protected cancelDocument(document: InventoryDocument): void {
    const data: ConfirmDialogData = {
      title: 'Descartar borrador',
      message:
        `El documento ${document.doc_number} quedara descartado. ` +
        'No mueve existencias porque nunca se confirmo.',
      confirmLabel: 'Descartar',
    };
    const dialogRef = this.dialog.open<ConfirmDialogComponent, ConfirmDialogData, boolean>(
      ConfirmDialogComponent,
      { width: '440px', maxWidth: 'calc(100vw - 32px)', data },
    );

    dialogRef.afterClosed().subscribe((confirmed) => {
      if (confirmed === true) {
        this.documents.cancel(document.uuid).subscribe({
          next: () => {
            this.notice.set(null);
            this.load();
          },
          error: (error: unknown) => {
            this.notice.set(this.businessMessage(error));
          },
        });
      }
    });
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    const raw = this.filters.value;
    const params: ListParams = {
      page: this.page(),
      per_page: this.pageSize(),
      sort: this.sortParam(),
      ...(this.typeFilter() === null ? {} : { type: this.typeFilter() as string }),
      ...(this.statusFilter() === null ? {} : { status: this.statusFilter() as string }),
      ...this.numericFilter('origin_warehouse_id', raw.originWarehouse),
      ...this.numericFilter('destination_warehouse_id', raw.destinationWarehouse),
      ...this.textFilter('from', raw.from),
      ...this.textFilter('to', raw.to),
    };

    this.documents.list(params).subscribe({
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

  // Los 409 de negocio (STOCK_INSUFICIENTE, ESTADO_INVALIDO) ya traen su
  // mensaje en español en el sobre: los muestro tal cual en lugar de
  // inventar una redaccion.
  private businessMessage(error: unknown): string {
    if (error instanceof AppError) {
      return error.message;
    }

    return 'La accion no pudo completarse. Intente de nuevo.';
  }

  private sortParam(): string {
    const key = SORTABLE_COLUMNS.has(this.sortKey()) ? this.sortKey() : 'created_at';
    return this.sortDirection() === 'desc' ? `-${key}` : key;
  }

  // Los ids de almacen viajan como numero solo cuando el campo trae digitos:
  // un vacio no se envia para no pedir un filtro inexistente.
  private numericFilter(key: string, value: string | null | undefined): ListParams {
    const trimmed = (value ?? '').trim();
    return trimmed === '' || !/^\d+$/.test(trimmed) ? {} : { [key]: Number(trimmed) };
  }

  private textFilter(key: string, value: string | null | undefined): ListParams {
    const trimmed = (value ?? '').trim();
    return trimmed === '' ? {} : { [key]: trimmed };
  }
}
