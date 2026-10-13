import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { debounceTime } from 'rxjs';
import { AuthService } from '../../core/services/auth.service';
import { AppError } from '../../core/models/app-error.model';
import type { ListParams } from '../../core/models/api.model';
import type { Category, UnitOfMeasure } from '../../core/models/catalog.model';
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
import { CategoryDialogComponent } from './category-dialog.component';

/**
 * Categorias y unidades (RF-026 a RF-035, api-design.md §7.5 y §7.6). Una sola
 * pagina con dos secciones: el listado de categorias con filtros, busqueda,
 * orden y paginacion mas su alta y edicion en dialogo, y la seccion de
 * unidades con su listado y su formulario de alta. Las unidades no ofrecen
 * edicion porque el contrato no define ese endpoint.
 */
@Component({
  selector: 'app-categories-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatCheckboxModule,
    MatDialogModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatSelectModule,
    PageHeaderComponent,
    DataTableComponent,
  ],
  templateUrl: './categories-page.component.html',
  styleUrl: './categories-page.component.scss',
})
export class CategoriesPageComponent {
  private readonly categories = inject(CategoriesService);
  private readonly units = inject(UnitsService);
  private readonly auth = inject(AuthService);
  private readonly dialog = inject(MatDialog);
  private readonly formBuilder = inject(FormBuilder);

  // La autorizacion vive en el backend; aqui solo decido que se muestra.
  protected readonly canCreate = this.auth.hasPermission('catalog.create');
  protected readonly canUpdate = this.auth.hasPermission('catalog.update');

  protected readonly categoryColumns: readonly DataTableColumn<Category>[] = [
    { key: 'name', header: 'Nombre', sortable: true, cell: (row) => row.name },
    { key: 'slug', header: 'Slug', mono: true, cell: (row) => row.slug ?? 'Sin slug' },
    { key: 'parent', header: 'Padre', cell: (row) => this.parentName(row) },
    {
      key: 'is_active',
      header: 'Estado',
      cell: (row) => (row.is_active ? 'Activa' : 'Inactiva'),
      status: (row) => (row.is_active ? 'success' : 'neutral'),
    },
  ];

  protected readonly categoryActions: readonly DataTableAction<Category>[] = [
    {
      id: 'edit',
      label: 'Editar categoria',
      icon: 'edit',
      hidden: () => !this.canUpdate,
      run: (row) => this.openEdit(row),
    },
    {
      id: 'deactivate',
      label: 'Desactivar categoria',
      icon: 'block',
      hidden: (row) => !this.canUpdate || !row.is_active,
      run: (row) => this.confirmDeactivate(row),
    },
  ];

  protected readonly unitColumns: readonly DataTableColumn<UnitOfMeasure>[] = [
    { key: 'code', header: 'Codigo', sortable: true, mono: true, cell: (row) => row.code },
    { key: 'name', header: 'Nombre', sortable: true, cell: (row) => row.name },
    {
      key: 'allows_decimals',
      header: 'Decimales',
      cell: (row) => (row.allows_decimals ? 'Permite decimales' : 'Solo enteros'),
      status: (row) => (row.allows_decimals ? 'info' : 'neutral'),
    },
  ];

  // Estado del listado de categorias.
  protected readonly categoryRows = signal<readonly Category[]>([]);
  protected readonly categoryTotal = signal(0);
  protected readonly categoryPage = signal(1);
  protected readonly categoryPageSize = signal(15);
  protected readonly categorySortKey = signal<string | null>('name');
  protected readonly categorySortDirection = signal<SortDirection>('asc');
  protected readonly categoryLoading = signal(false);
  protected readonly categoryError = signal<AppError | null>(null);
  protected readonly categoryErrorMessage = computed(() => this.categoryError()?.message ?? null);
  protected readonly categoryErrorId = computed(() => this.categoryError()?.errorId ?? null);

  // Filtros del listado de categorias.
  protected readonly activeFilter = signal<boolean | null>(null);
  protected readonly parentFilter = signal<number | null>(null);

  // Directorio de categorias activas para resolver el nombre del padre y
  // poblar los selectores sin paginar la vista principal.
  protected readonly directory = signal<readonly Category[]>([]);

  // Estado del listado de unidades.
  protected readonly unitRows = signal<readonly UnitOfMeasure[]>([]);
  protected readonly unitTotal = signal(0);
  protected readonly unitPage = signal(1);
  protected readonly unitPageSize = signal(15);
  protected readonly unitSortKey = signal<string | null>('code');
  protected readonly unitSortDirection = signal<SortDirection>('asc');
  protected readonly unitLoading = signal(false);
  protected readonly unitError = signal<AppError | null>(null);
  protected readonly unitErrorMessage = computed(() => this.unitError()?.message ?? null);
  protected readonly unitErrorId = computed(() => this.unitError()?.errorId ?? null);

  // Aviso de la pagina para errores de mutacion (409 con dependientes, 403).
  // El exito se refleja recargando los datos, no con un aviso permanente.
  protected readonly notice = signal<string | null>(null);

  protected readonly searchControl = this.formBuilder.control('');
  protected readonly unitSaving = signal(false);
  protected readonly unitBanner = signal<string | null>(null);

  protected readonly unitForm = this.formBuilder.nonNullable.group({
    code: [''],
    name: [''],
    allows_decimals: [false],
  });

  public constructor() {
    // La busqueda espera a que el usuario deje de escribir antes de pedir la
    // pagina 1, para no disparar una peticion por tecla.
    this.searchControl.valueChanges.pipe(debounceTime(400), takeUntilDestroyed()).subscribe(() => {
      this.categoryPage.set(1);
      this.loadCategories();
    });

    this.loadDirectory();
    this.loadCategories();
    this.loadUnits();
  }

  protected onCategorySort(change: SortChange): void {
    this.categorySortKey.set(change.key);
    this.categorySortDirection.set(change.direction);
    this.categoryPage.set(1);
    this.loadCategories();
  }

  protected onCategoryPage(change: PageChange): void {
    this.categoryPage.set(change.page);
    this.categoryPageSize.set(change.pageSize);
    this.loadCategories();
  }

  protected onActiveFilter(value: boolean | null): void {
    this.activeFilter.set(value);
    this.categoryPage.set(1);
    this.loadCategories();
  }

  protected onParentFilter(value: number | null): void {
    this.parentFilter.set(value);
    this.categoryPage.set(1);
    this.loadCategories();
  }

  protected onClearFilters(): void {
    this.searchControl.setValue('');
    this.activeFilter.set(null);
    this.parentFilter.set(null);
    this.categoryPage.set(1);
    this.loadCategories();
  }

  protected onUnitSort(change: SortChange): void {
    this.unitSortKey.set(change.key);
    this.unitSortDirection.set(change.direction);
    this.unitPage.set(1);
    this.loadUnits();
  }

  protected onUnitPage(change: PageChange): void {
    this.unitPage.set(change.page);
    this.unitPageSize.set(change.pageSize);
    this.loadUnits();
  }

  protected openCreate(): void {
    const dialogRef = this.dialog.open<CategoryDialogComponent, object, Category | null>(
      CategoryDialogComponent,
      {
        width: '480px',
        maxWidth: 'calc(100vw - 32px)',
        data: { parents: this.directory() },
      },
    );

    dialogRef.afterClosed().subscribe((result) => {
      if (result !== null && result !== undefined) {
        this.notice.set(null);
        this.loadDirectory();
        this.loadCategories();
      }
    });
  }

  protected openEdit(category: Category): void {
    const parents = this.directory().filter((candidate) => candidate.id !== category.id);
    const dialogRef = this.dialog.open<CategoryDialogComponent, object, Category | null>(
      CategoryDialogComponent,
      {
        width: '480px',
        maxWidth: 'calc(100vw - 32px)',
        data: { category, parents },
      },
    );

    dialogRef.afterClosed().subscribe((result) => {
      if (result !== null && result !== undefined) {
        this.notice.set(null);
        this.loadDirectory();
        this.loadCategories();
      }
    });
  }

  protected confirmDeactivate(category: Category): void {
    const data: ConfirmDialogData = {
      title: 'Desactivar categoria',
      message:
        `La categoria ${category.name} dejara de estar disponible para productos nuevos. ` +
        'Si conserva productos o subcategorias activas, el servidor rechazara la operacion y mostrara el motivo.',
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
        this.deactivate(category);
      }
    });
  }

  protected createUnit(): void {
    this.unitBanner.set(null);
    this.clearUnitBackendErrors();

    const code = this.unitForm.controls.code.value.trim();
    const name = this.unitForm.controls.name.value.trim();

    if (code.length === 0 || name.length === 0 || code.length > 20) {
      this.unitForm.markAllAsTouched();
      return;
    }

    this.unitSaving.set(true);

    this.units
      .create({
        code,
        name,
        allows_decimals: this.unitForm.controls.allows_decimals.value,
      })
      .subscribe({
        next: () => {
          this.unitSaving.set(false);
          this.unitForm.reset({ code: '', name: '', allows_decimals: false });
          this.loadUnits();
        },
        error: (error: unknown) => {
          this.unitSaving.set(false);
          this.handleUnitError(error);
        },
      });
  }

  private deactivate(category: Category): void {
    this.notice.set(null);

    this.categories.deactivate(category.id).subscribe({
      next: () => {
        this.loadDirectory();
        this.loadCategories();
      },
      error: (error: unknown) => {
        // El 409 trae el motivo (productos o subcategorias activas) en el
        // mensaje del sobre; lo muestro tal cual en lugar de inventar uno.
        this.notice.set(error instanceof AppError ? error.message : 'No se pudo desactivar la categoria.');
      },
    });
  }

  protected loadCategories(): void {
    this.categoryLoading.set(true);
    this.categoryError.set(null);

    const query = this.searchControl.value?.trim() ?? '';
    const active = this.activeFilter();
    const parent = this.parentFilter();

    // ListParams es de solo lectura: compongo el objeto con propagacion en
    // lugar de asignar por clave.
    const params: ListParams = {
      page: this.categoryPage(),
      per_page: this.categoryPageSize(),
      sort: this.categorySortParam(),
      ...(query.length > 0 ? { q: query } : {}),
      ...(active !== null ? { is_active: active } : {}),
      ...(parent !== null ? { parent_id: parent } : {}),
    };

    this.categories.list(params).subscribe({
      next: (response) => {
        this.categoryRows.set(response.data);
        this.categoryTotal.set(response.meta.total);
        this.categoryLoading.set(false);
      },
      error: (error: unknown) => {
        this.categoryRows.set([]);
        this.categoryTotal.set(0);
        this.categoryError.set(error instanceof AppError ? error : null);
        this.categoryLoading.set(false);
      },
    });
  }

  protected loadUnits(): void {
    this.unitLoading.set(true);
    this.unitError.set(null);

    this.units
      .list({
        page: this.unitPage(),
        per_page: this.unitPageSize(),
        sort: this.unitSortParam(),
      })
      .subscribe({
        next: (response) => {
          this.unitRows.set(response.data);
          this.unitTotal.set(response.meta.total);
          this.unitLoading.set(false);
        },
        error: (error: unknown) => {
          this.unitRows.set([]);
          this.unitTotal.set(0);
          this.unitError.set(error instanceof AppError ? error : null);
          this.unitLoading.set(false);
        },
      });
  }

  private loadDirectory(): void {
    this.categories.list({ per_page: 100, is_active: true, sort: 'name' }).subscribe({
      next: (response) => this.directory.set(response.data),
      error: () => this.directory.set([]),
    });
  }

  private parentName(category: Category): string {
    if (category.parent_id === null) {
      return 'Raiz';
    }

    const parent = this.directory().find((candidate) => candidate.id === category.parent_id);
    return parent?.name ?? 'Sin nombre';
  }

  private handleUnitError(error: unknown): void {
    if (!(error instanceof AppError)) {
      this.unitBanner.set('No se pudo crear la unidad. Intente de nuevo.');
      return;
    }

    if (error.kind === 'validation') {
      let matched = false;

      for (const detail of error.details) {
        if (detail.field === 'code' || detail.field === 'name') {
          this.unitForm.controls[detail.field].setErrors({ backend: detail.message });
          matched = true;
        }
      }

      if (!matched) {
        this.unitBanner.set(error.message);
      }

      return;
    }

    this.unitBanner.set(error.message);
  }

  private clearUnitBackendErrors(): void {
    for (const control of [this.unitForm.controls.code, this.unitForm.controls.name]) {
      if (control.hasError('backend')) {
        control.setErrors(null);
      }
    }
  }

  private categorySortParam(): string {
    const key = this.categorySortKey() ?? 'name';
    return this.categorySortDirection() === 'desc' ? `-${key}` : key;
  }

  private unitSortParam(): string {
    const key = this.unitSortKey() ?? 'code';
    return this.unitSortDirection() === 'desc' ? `-${key}` : key;
  }
}
