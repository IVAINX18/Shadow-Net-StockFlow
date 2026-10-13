import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { Router, RouterLink } from '@angular/router';
import { debounceTime } from 'rxjs';
import { AuthService } from '../../core/services/auth.service';
import { AppError } from '../../core/models/app-error.model';
import type { ListParams } from '../../core/models/api.model';
import type { Category, Product, UnitOfMeasure } from '../../core/models/catalog.model';
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
import { CategoriesService } from './categories.service';
import { UnitsService } from './units.service';
import { ProductsService } from './products.service';
import { ProductDialogComponent } from './product-dialog.component';

/**
 * Productos (RF-036 a RF-050, api-design.md §7.7). Listado con filtros por
 * estado y categoria, busqueda por SKU, nombre y codigo de barras, orden y
 * paginacion; alta y edicion en dialogo con selectores de categoria y unidad;
 * desactivacion con confirmacion. El detalle vive en su propia ruta.
 */
@Component({
  selector: 'app-products-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    RouterLink,
    MatButtonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatSelectModule,
    PageHeaderComponent,
    DataTableComponent,
  ],
  templateUrl: './products-page.component.html',
  styleUrl: './products-page.component.scss',
})
export class ProductsPageComponent {
  private readonly products = inject(ProductsService);
  private readonly categories = inject(CategoriesService);
  private readonly units = inject(UnitsService);
  private readonly auth = inject(AuthService);
  private readonly dialog = inject(MatDialog);
  private readonly router = inject(Router);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly canCreate = this.auth.hasPermission('catalog.create');
  protected readonly canUpdate = this.auth.hasPermission('catalog.update');

  protected readonly columns: readonly DataTableColumn<Product>[] = [
    { key: 'sku', header: 'SKU', sortable: true, mono: true, cell: (row) => row.sku },
    { key: 'name', header: 'Nombre', sortable: true, cell: (row) => row.name },
    { key: 'category', header: 'Categoria', cell: (row) => row.category?.name ?? 'Sin categoria' },
    { key: 'unit', header: 'Unidad', cell: (row) => row.unit_of_measure?.code ?? 'Sin unidad' },
    {
      key: 'is_active',
      header: 'Estado',
      cell: (row) => (row.is_active ? 'Activo' : 'Inactivo'),
      status: (row) => (row.is_active ? 'success' : 'neutral'),
    },
  ];

  protected readonly actions: readonly DataTableAction<Product>[] = [
    {
      id: 'view',
      label: 'Ver ficha del producto',
      icon: 'visibility',
      run: (row) => this.openDetail(row),
    },
    {
      id: 'edit',
      label: 'Editar producto',
      icon: 'edit',
      hidden: () => !this.canUpdate,
      run: (row) => this.openEdit(row),
    },
    {
      id: 'deactivate',
      label: 'Desactivar producto',
      icon: 'block',
      hidden: (row) => !this.canUpdate || !row.is_active,
      run: (row) => this.confirmDeactivate(row),
    },
  ];

  protected readonly rows = signal<readonly Product[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly pageSize = signal(15);
  protected readonly sortKey = signal<string | null>('name');
  protected readonly sortDirection = signal<SortDirection>('asc');
  protected readonly loading = signal(false);
  protected readonly error = signal<AppError | null>(null);
  protected readonly errorMessage = computed(() => this.error()?.message ?? null);
  protected readonly errorId = computed(() => this.error()?.errorId ?? null);

  protected readonly activeFilter = signal<boolean | null>(null);
  protected readonly categoryFilter = signal<number | null>(null);
  protected readonly categoryDirectory = signal<readonly Category[]>([]);
  protected readonly unitDirectory = signal<readonly UnitOfMeasure[]>([]);

  protected readonly notice = signal<string | null>(null);
  protected readonly searchControl = this.formBuilder.control('');

  public constructor() {
    this.searchControl.valueChanges.pipe(debounceTime(400), takeUntilDestroyed()).subscribe(() => {
      this.page.set(1);
      this.load();
    });

    this.loadDirectories();
    this.load();
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

  protected onActiveFilter(value: boolean | null): void {
    this.activeFilter.set(value);
    this.page.set(1);
    this.load();
  }

  protected onCategoryFilter(value: number | null): void {
    this.categoryFilter.set(value);
    this.page.set(1);
    this.load();
  }

  protected onClearFilters(): void {
    this.searchControl.setValue('');
    this.activeFilter.set(null);
    this.categoryFilter.set(null);
    this.page.set(1);
    this.load();
  }

  protected openCreate(): void {
    const dialogRef = this.dialog.open<ProductDialogComponent, object, Product | null>(
      ProductDialogComponent,
      {
        width: '560px',
        maxWidth: 'calc(100vw - 32px)',
        data: { categories: this.categoryDirectory(), units: this.unitDirectory() },
      },
    );

    dialogRef.afterClosed().subscribe((result) => {
      if (result !== null && result !== undefined) {
        this.notice.set(null);
        this.load();
      }
    });
  }

  protected openEdit(product: Product): void {
    const dialogRef = this.dialog.open<ProductDialogComponent, object, Product | null>(
      ProductDialogComponent,
      {
        width: '560px',
        maxWidth: 'calc(100vw - 32px)',
        data: { product, categories: this.categoryDirectory(), units: this.unitDirectory() },
      },
    );

    dialogRef.afterClosed().subscribe((result) => {
      if (result !== null && result !== undefined) {
        this.notice.set(null);
        this.load();
      }
    });
  }

  protected openDetail(product: Product): void {
    void this.router.navigate(['/productos', product.id]);
  }

  protected confirmDeactivate(product: Product): void {
    const data: ConfirmDialogData = {
      title: 'Desactivar producto',
      message:
        `El producto ${product.sku} dejara de estar disponible para documentos nuevos. ` +
        'Su historial se conserva sin cambios.',
      confirmLabel: 'Desactivar',
    };
    const dialogRef = this.dialog.open<ConfirmDialogComponent, ConfirmDialogData, boolean>(
      ConfirmDialogComponent,
      {
        width: '440px',
        maxWidth: 'calc(100vw - 32px)',
        data,
      },
    );

    dialogRef.afterClosed().subscribe((confirmed) => {
      if (confirmed === true) {
        this.products.deactivate(product.id).subscribe({
          next: () => {
            this.notice.set(null);
            this.load();
          },
          error: (error: unknown) => {
            this.notice.set(
              error instanceof AppError ? error.message : 'No se pudo desactivar el producto.',
            );
          },
        });
      }
    });
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    const query = this.searchControl.value?.trim() ?? '';
    const active = this.activeFilter();
    const category = this.categoryFilter();

    // ListParams es de solo lectura: compongo el objeto con propagacion en
    // lugar de asignar por clave.
    const params: ListParams = {
      page: this.page(),
      per_page: this.pageSize(),
      sort: this.sortParam(),
      ...(query.length > 0 ? { q: query } : {}),
      ...(active !== null ? { is_active: active } : {}),
      ...(category !== null ? { category_id: category } : {}),
    };

    this.products.list(params).subscribe({
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

  // Los selectores del dialogo necesitan el catalogo completo, no la pagina
  // visible: pido hasta 100 registros activos ordenados por nombre.
  private loadDirectories(): void {
    this.categories.list({ per_page: 100, is_active: true, sort: 'name' }).subscribe({
      next: (response) => this.categoryDirectory.set(response.data),
      error: () => this.categoryDirectory.set([]),
    });
    this.units.list({ per_page: 100, sort: 'name' }).subscribe({
      next: (response) => this.unitDirectory.set(response.data),
      error: () => this.unitDirectory.set([]),
    });
  }

  private sortParam(): string {
    const key = this.sortKey() ?? 'name';
    return this.sortDirection() === 'desc' ? `-${key}` : key;
  }
}
