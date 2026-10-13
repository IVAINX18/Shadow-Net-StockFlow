import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, FormControl, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { AppError } from '../../core/models/app-error.model';
import type { Warehouse } from '../../core/models/warehouse.model';
import type {
  Product,
  ProductWarehouseConfig,
  UpdateWarehouseConfigPayload,
} from '../../core/models/catalog.model';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { ErrorStateComponent } from '../../shared/components/error-state/error-state.component';
import { LoadingSkeletonComponent } from '../../shared/components/loading-skeleton/loading-skeleton.component';
import { StatusChipComponent } from '../../shared/components/status-chip/status-chip.component';
import { ProductsService } from './products.service';
import { WarehousesService } from '../warehouses/warehouses.service';
import { decimalStringValidator, minMaxValidator } from './catalog-validators';

/** Controles del editor de configuracion por almacen, todo como cadena. */
interface WarehouseConfigForm {
  readonly min_stock: FormControl<string>;
  readonly max_stock: FormControl<string>;
  readonly reorder_point: FormControl<string>;
}

/** Fila del editor: almacen visible con su configuracion y su formulario. */
interface WarehouseConfigRow {
  readonly warehouse: Warehouse;
  readonly config: ProductWarehouseConfig | null;
  readonly form: FormGroup<WarehouseConfigForm>;
  readonly saving: ReturnType<typeof signal<boolean>>;
  readonly error: ReturnType<typeof signal<string | null>>;
}

/**
 * Ficha de producto (RF-050, api-design.md §7.7). Muestra la ficha con
 * categoria, unidad y precios como cadena, y el editor de configuracion por
 * almacen (minimo, maximo, punto de reorden) para los almacenes visibles. Un
 * 403 FUERA_DE_ALCANCE se muestra junto a la fila afectada. No presenta
 * cantidades: el kardex llega en una fase posterior.
 */
@Component({
  selector: 'app-product-detail-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    RouterLink,
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    PageHeaderComponent,
    ErrorStateComponent,
    LoadingSkeletonComponent,
    StatusChipComponent,
  ],
  templateUrl: './product-detail-page.component.html',
  styleUrl: './product-detail-page.component.scss',
})
export class ProductDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly products = inject(ProductsService);
  private readonly warehouses = inject(WarehousesService);
  private readonly auth = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly canUpdate = this.auth.hasPermission('catalog.update');

  protected readonly product = signal<Product | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal<AppError | null>(null);
  protected readonly errorMessage = computed(() => this.error()?.message ?? null);
  protected readonly errorId = computed(() => this.error()?.errorId ?? null);

  protected readonly configRows = signal<readonly WarehouseConfigRow[]>([]);
  protected readonly configsLoading = signal(false);
  protected readonly configsError = signal<AppError | null>(null);
  protected readonly configsErrorMessage = computed(() => this.configsError()?.message ?? null);

  public constructor() {
    this.load();
  }

  protected load(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    if (!Number.isInteger(id) || id <= 0) {
      this.loading.set(false);
      this.error.set(
        new AppError({
          kind: 'business',
          code: 'NO_ENCONTRADO',
          message: 'El producto solicitado no existe.',
          status: 404,
        }),
      );
      return;
    }

    this.loading.set(true);
    this.error.set(null);

    this.products.get(id).subscribe({
      next: (product) => {
        this.product.set(product);
        this.loading.set(false);
        this.loadConfigs(product);
      },
      error: (error: unknown) => {
        this.product.set(null);
        this.error.set(error instanceof AppError ? error : null);
        this.loading.set(false);
      },
    });
  }

  protected saveRow(row: WarehouseConfigRow): void {
    const product = this.product();

    if (product === null) {
      return;
    }

    row.error.set(null);

    if (row.form.invalid) {
      row.form.markAllAsTouched();
      return;
    }

    const raw = row.form.getRawValue();
    const min = (raw.min_stock as string).trim();
    const max = (raw.max_stock as string).trim();
    const reorder = (raw.reorder_point as string).trim();

    // Los vacios no se envian: el backend conserva lo existente. Todo viaja
    // como cadena decimal, nunca como numero.
    const payload: UpdateWarehouseConfigPayload = {
      ...(min.length > 0 ? { min_stock: min } : {}),
      ...(max.length > 0 ? { max_stock: max } : {}),
      ...(reorder.length > 0 ? { reorder_point: reorder } : {}),
    };

    if (Object.keys(payload).length === 0) {
      row.error.set('Escriba al menos un valor para guardar la configuracion.');
      return;
    }

    row.saving.set(true);

    this.products.updateWarehouseConfig(product.id, row.warehouse.id, payload).subscribe({
      next: (config) => {
        row.saving.set(false);
        this.replaceRow(row, config);
      },
      error: (error: unknown) => {
        row.saving.set(false);
        // El 403 FUERA_DE_ALCANCE llega con su mensaje del sobre y se muestra
        // junto a la fila, sin tumbar el resto de la ficha.
        row.error.set(
          error instanceof AppError ? error.message : 'No se pudo guardar la configuracion.',
        );
      },
    });
  }

  // Los almacenes visibles los resuelve el backend segun el alcance; la
  // configuracion de cada uno se une por warehouse_id. Un almacen sin fila
  // muestra el formulario vacio porque el PUT tambien crea la fila.
  private loadConfigs(product: Product): void {
    this.configsLoading.set(true);
    this.configsError.set(null);

    const known = new Map<number, ProductWarehouseConfig>(
      (product.warehouses ?? []).map((config) => [config.warehouse_id, config]),
    );

    this.warehouses.list({ per_page: 100, sort: 'name' }).subscribe({
      next: (response) => {
        this.configRows.set(
          response.data.map((warehouse) => this.buildRow(warehouse, known.get(warehouse.id) ?? null)),
        );
        this.configsLoading.set(false);
      },
      error: (error: unknown) => {
        this.configRows.set([]);
        this.configsError.set(error instanceof AppError ? error : null);
        this.configsLoading.set(false);
      },
    });
  }

  private buildRow(warehouse: Warehouse, config: ProductWarehouseConfig | null): WarehouseConfigRow {
    const form = this.formBuilder.nonNullable.group(
      {
        min_stock: [config?.min_stock ?? ''],
        max_stock: [config?.max_stock ?? ''],
        reorder_point: [config?.reorder_point ?? ''],
      },
      { validators: [minMaxValidator('min_stock', 'max_stock')] },
    );

    form.controls.min_stock.addValidators([decimalStringValidator(3)]);
    form.controls.max_stock.addValidators([decimalStringValidator(3)]);
    form.controls.reorder_point.addValidators([decimalStringValidator(3)]);

    return {
      warehouse,
      config,
      form,
      saving: signal(false),
      error: signal<string | null>(null),
    };
  }

  private replaceRow(row: WarehouseConfigRow, config: ProductWarehouseConfig): void {
    this.configRows.update((rows) =>
      rows.map((candidate) =>
        candidate.warehouse.id === row.warehouse.id
          ? this.buildRow(candidate.warehouse, config)
          : candidate,
      ),
    );
  }
}
