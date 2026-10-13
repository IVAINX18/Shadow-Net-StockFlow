import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import {
  MAT_DIALOG_DATA,
  MatDialogModule,
  MatDialogRef,
} from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { AppError } from '../../core/models/app-error.model';
import type {
  Category,
  Product,
  UnitOfMeasure,
} from '../../core/models/catalog.model';
import { ProductsService } from './products.service';
import { decimalStringValidator } from './catalog-validators';

/** Datos de entrada del dialogo de producto. */
export interface ProductDialogData {
  /** Producto a editar; ausente significa alta. */
  readonly product?: Product;
  /** Categorias activas para el selector. */
  readonly categories: readonly Category[];
  /** Unidades para el selector. */
  readonly units: readonly UnitOfMeasure[];
}

/**
 * Alta y edicion de productos (RF-036, RF-047). El SKU solo existe en el alta:
 * en edicion se muestra deshabilitado con la explicacion de su inmutabilidad,
 * porque el backend responde 422 ante cualquier intento de cambio. Los montos
 * viajan como cadena decimal y nunca se convierten a numero.
 */
@Component({
  selector: 'app-product-dialog',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatCheckboxModule,
    MatButtonModule,
  ],
  template: `
    <h2 mat-dialog-title class="dialog__title app-tight">
      {{ isEdit ? 'Editar producto' : 'Nuevo producto' }}
    </h2>
    <mat-dialog-content>
      <form class="dialog__form" [formGroup]="form" (ngSubmit)="onSubmit()" novalidate>
        @if (banner(); as notice) {
          <p class="dialog__banner" role="alert">{{ notice }}</p>
        }
        @if (isEdit) {
          <div class="dialog__field">
            <label class="dialog__label" for="product-sku-view">SKU</label>
            <mat-form-field appearance="outline" subscriptSizing="dynamic">
              <input
                matInput
                id="product-sku-view"
                type="text"
                class="app-mono"
                [value]="data.product?.sku ?? ''"
                disabled
              />
              <mat-hint>El SKU es inmutable: identifica al producto y no se puede cambiar.</mat-hint>
            </mat-form-field>
          </div>
        } @else {
          <div class="dialog__field">
            <label class="dialog__label" for="product-sku">SKU</label>
            <mat-form-field appearance="outline" subscriptSizing="dynamic">
              <input
                matInput
                id="product-sku"
                type="text"
                formControlName="sku"
                maxlength="60"
                class="app-mono"
                required
              />
              <mat-hint>Unico en el catalogo, maximo 60 caracteres.</mat-hint>
              <mat-error>
                @if (form.controls.sku.hasError('required')) {
                  El SKU es obligatorio.
                } @else if (form.controls.sku.hasError('backend')) {
                  {{ form.controls.sku.getError('backend') }}
                }
              </mat-error>
            </mat-form-field>
          </div>
        }
        <div class="dialog__field">
          <label class="dialog__label" for="product-name">Nombre</label>
          <mat-form-field appearance="outline" subscriptSizing="dynamic">
            <input
              matInput
              id="product-name"
              type="text"
              formControlName="name"
              maxlength="180"
              required
            />
            <mat-error>
              @if (form.controls.name.hasError('required')) {
                El nombre es obligatorio.
              } @else if (form.controls.name.hasError('backend')) {
                {{ form.controls.name.getError('backend') }}
              }
            </mat-error>
          </mat-form-field>
        </div>
        <div class="dialog__row">
          <div class="dialog__field">
            <label class="dialog__label" for="product-category">Categoria</label>
            <mat-form-field appearance="outline" subscriptSizing="dynamic">
              <mat-select id="product-category" formControlName="category_id" required>
                @for (category of data.categories; track category.id) {
                  <mat-option [value]="category.id">{{ category.name }}</mat-option>
                }
              </mat-select>
              <mat-error>
                @if (form.controls.category_id.hasError('required')) {
                  La categoria es obligatoria.
                } @else if (form.controls.category_id.hasError('backend')) {
                  {{ form.controls.category_id.getError('backend') }}
                }
              </mat-error>
            </mat-form-field>
          </div>
          <div class="dialog__field">
            <label class="dialog__label" for="product-unit">Unidad de medida</label>
            <mat-form-field appearance="outline" subscriptSizing="dynamic">
              <mat-select id="product-unit" formControlName="unit_of_measure_id" required>
                @for (unit of data.units; track unit.id) {
                  <mat-option [value]="unit.id">{{ unit.code }} ({{ unit.name }})</mat-option>
                }
              </mat-select>
              <mat-error>
                @if (form.controls.unit_of_measure_id.hasError('required')) {
                  La unidad es obligatoria.
                } @else if (form.controls.unit_of_measure_id.hasError('backend')) {
                  {{ form.controls.unit_of_measure_id.getError('backend') }}
                }
              </mat-error>
            </mat-form-field>
          </div>
        </div>
        <div class="dialog__field">
          <label class="dialog__label" for="product-barcode">Codigo de barras (opcional)</label>
          <mat-form-field appearance="outline" subscriptSizing="dynamic">
            <input
              matInput
              id="product-barcode"
              type="text"
              formControlName="barcode"
              maxlength="80"
              class="app-mono"
            />
            <mat-hint>Unico cuando se informa; vacio significa sin codigo.</mat-hint>
            <mat-error>
              @if (form.controls.barcode.hasError('backend')) {
                {{ form.controls.barcode.getError('backend') }}
              }
            </mat-error>
          </mat-form-field>
        </div>
        <div class="dialog__field">
          <label class="dialog__label" for="product-description">Descripcion (opcional)</label>
          <mat-form-field appearance="outline" subscriptSizing="dynamic">
            <textarea matInput id="product-description" formControlName="description" rows="2"></textarea>
            <mat-error>
              @if (form.controls.description.hasError('backend')) {
                {{ form.controls.description.getError('backend') }}
              }
            </mat-error>
          </mat-form-field>
        </div>
        <div class="dialog__row">
          <div class="dialog__field">
            <label class="dialog__label" for="product-cost">Costo de referencia (opcional)</label>
            <mat-form-field appearance="outline" subscriptSizing="dynamic">
              <input
                matInput
                id="product-cost"
                type="text"
                inputmode="decimal"
                formControlName="cost_price"
                class="app-mono app-tabular"
              />
              <mat-hint>Cadena decimal con maximo 2 decimales, sin negativos.</mat-hint>
              <mat-error>
                @if (form.controls.cost_price.hasError('decimal')) {
                  Use una cadena decimal valida con maximo 2 decimales.
                } @else if (form.controls.cost_price.hasError('backend')) {
                  {{ form.controls.cost_price.getError('backend') }}
                }
              </mat-error>
            </mat-form-field>
          </div>
          <div class="dialog__field">
            <label class="dialog__label" for="product-price">Precio de venta (opcional)</label>
            <mat-form-field appearance="outline" subscriptSizing="dynamic">
              <input
                matInput
                id="product-price"
                type="text"
                inputmode="decimal"
                formControlName="sale_price"
                class="app-mono app-tabular"
              />
              <mat-hint>Cadena decimal con maximo 2 decimales, sin negativos.</mat-hint>
              <mat-error>
                @if (form.controls.sale_price.hasError('decimal')) {
                  Use una cadena decimal valida con maximo 2 decimales.
                } @else if (form.controls.sale_price.hasError('backend')) {
                  {{ form.controls.sale_price.getError('backend') }}
                }
              </mat-error>
            </mat-form-field>
          </div>
        </div>
        @if (isEdit) {
          <mat-checkbox formControlName="is_active">Producto activo</mat-checkbox>
        }
      </form>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button mat-button type="button" (click)="onCancel()">Cancelar</button>
      <button
        mat-flat-button
        color="primary"
        type="button"
        [disabled]="saving()"
        (click)="onSubmit()"
      >
        {{ saving() ? 'Guardando' : isEdit ? 'Guardar cambios' : 'Crear producto' }}
      </button>
    </mat-dialog-actions>
  `,
  styles: `
    .dialog__title {
      font: var(--mat-sys-title-large);
    }

    .dialog__form {
      display: flex;
      flex-direction: column;
      gap: var(--app-space-4);
      padding-top: var(--app-space-2);
    }

    .dialog__banner {
      margin: 0;
      padding: var(--app-space-3) var(--app-card-padding);
      border: 1px solid var(--mat-sys-outline-variant);
      border-radius: var(--mat-sys-corner-medium);
      background: var(--mat-sys-error-container);
      color: var(--mat-sys-on-error-container);
      font: var(--mat-sys-body-medium);
    }

    .dialog__row {
      display: flex;
      flex-wrap: wrap;
      gap: var(--app-space-4);
    }

    .dialog__row .dialog__field {
      flex: 1 1 200px;
    }

    .dialog__field {
      display: flex;
      flex-direction: column;
      gap: var(--app-space-1);
    }

    .dialog__label {
      font: var(--mat-sys-label-medium);
      color: var(--mat-sys-on-surface);
    }
  `,
})
export class ProductDialogComponent {
  protected readonly data: ProductDialogData = inject(MAT_DIALOG_DATA);
  private readonly ref: MatDialogRef<ProductDialogComponent, Product | null> = inject(MatDialogRef);
  private readonly service = inject(ProductsService);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly isEdit: boolean = this.data.product !== undefined;
  protected readonly banner = signal<string | null>(null);
  private readonly savingSignal = signal(false);
  protected readonly saving = this.savingSignal.asReadonly();

  protected readonly form = this.formBuilder.nonNullable.group({
    sku: [this.data.product?.sku ?? '', [Validators.required, Validators.maxLength(60)]],
    name: [this.data.product?.name ?? '', [Validators.required, Validators.maxLength(180)]],
    category_id: [this.data.product?.category_id ?? null, [Validators.required]],
    unit_of_measure_id: [this.data.product?.unit_of_measure_id ?? null, [Validators.required]],
    barcode: [this.data.product?.barcode ?? ''],
    description: [this.data.product?.description ?? ''],
    cost_price: [this.data.product?.cost_price ?? '', [decimalStringValidator(2)]],
    sale_price: [this.data.product?.sale_price ?? '', [decimalStringValidator(2)]],
    is_active: [this.data.product?.is_active ?? true],
  });

  protected onCancel(): void {
    this.ref.close(null);
  }

  protected onSubmit(): void {
    this.banner.set(null);
    this.clearBackendErrors();

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.savingSignal.set(true);
    const values = this.form.getRawValue();

    // Los opcionales vacios no se envian; los montos viajan como cadena tal
    // cual los escribio el usuario, sin convertirlos a numero.
    const nullIfBlank = (value: string): string | null =>
      value.trim().length === 0 ? null : value.trim();

    if (!this.isEdit) {
      this.service
        .create({
          sku: values.sku.trim(),
          name: values.name.trim(),
          category_id: Number(values.category_id),
          unit_of_measure_id: Number(values.unit_of_measure_id),
          description: nullIfBlank(values.description),
          barcode: nullIfBlank(values.barcode),
          ...(nullIfBlank(values.cost_price) !== null
            ? { cost_price: (nullIfBlank(values.cost_price) as string) }
            : {}),
          ...(nullIfBlank(values.sale_price) !== null
            ? { sale_price: (nullIfBlank(values.sale_price) as string) }
            : {}),
        })
        .subscribe({ next: (product) => this.ref.close(product), error: (error: unknown) => this.fail(error) });
      return;
    }

    const product = this.data.product;

    if (product === undefined) {
      this.savingSignal.set(false);
      return;
    }

    this.service
      .update(product.id, {
        name: values.name.trim(),
        category_id: Number(values.category_id),
        unit_of_measure_id: Number(values.unit_of_measure_id),
        description: nullIfBlank(values.description),
        barcode: nullIfBlank(values.barcode),
        is_active: values.is_active,
        ...(nullIfBlank(values.cost_price) !== null
          ? { cost_price: (nullIfBlank(values.cost_price) as string) }
          : {}),
        ...(nullIfBlank(values.sale_price) !== null
          ? { sale_price: (nullIfBlank(values.sale_price) as string) }
          : {}),
      })
      .subscribe({ next: (updated) => this.ref.close(updated), error: (error: unknown) => this.fail(error) });
  }

  private fail(error: unknown): void {
    this.savingSignal.set(false);

    if (!(error instanceof AppError)) {
      this.banner.set('No se pudo guardar el producto. Intente de nuevo.');
      return;
    }

    if (error.kind === 'validation') {
      let matched = false;

      for (const detail of error.details) {
        const field = detail.field;

        if (
          field === 'sku' ||
          field === 'name' ||
          field === 'category_id' ||
          field === 'unit_of_measure_id' ||
          field === 'barcode' ||
          field === 'description' ||
          field === 'cost_price' ||
          field === 'sale_price'
        ) {
          this.form.controls[field].setErrors({ backend: detail.message });
          matched = true;
        }
      }

      if (!matched) {
        this.banner.set(error.message);
      }

      return;
    }

    this.banner.set(error.message);
  }

  private clearBackendErrors(): void {
    for (const control of Object.values(this.form.controls)) {
      if (control.hasError('backend')) {
        const current = { ...control.errors };
        delete current['backend'];
        control.setErrors(Object.keys(current).length > 0 ? current : null);
      }
    }
  }
}
