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
import type { Category } from '../../core/models/catalog.model';
import { CategoriesService } from './categories.service';

/** Datos de entrada del dialogo de categoria. */
export interface CategoryDialogData {
  /** Categoria a editar; ausente significa alta. */
  readonly category?: Category;
  /** Candidatas a categoria padre (activas, sin la propia). */
  readonly parents: readonly Category[];
}

/**
 * Alta y edicion de categorias (RF-026, RF-029). Formulario reactivo tipado con
 * etiqueta sobre el campo y error bajo el campo; los 422 del backend se mapean
 * campo por campo. El slug es opcional: vacio deja que el backend lo derive.
 */
@Component({
  selector: 'app-category-dialog',
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
      {{ isEdit ? 'Editar categoria' : 'Nueva categoria' }}
    </h2>
    <mat-dialog-content>
      <form
        class="dialog__form"
        [formGroup]="form"
        (ngSubmit)="onSubmit()"
        novalidate
      >
        @if (banner(); as notice) {
          <p class="dialog__banner" role="alert">{{ notice }}</p>
        }
        <div class="dialog__field">
          <label class="dialog__label" for="category-name">Nombre</label>
          <mat-form-field appearance="outline" subscriptSizing="dynamic">
            <input
              matInput
              id="category-name"
              type="text"
              formControlName="name"
              maxlength="150"
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
        <div class="dialog__field">
          <label class="dialog__label" for="category-parent">Categoria padre</label>
          <mat-form-field appearance="outline" subscriptSizing="dynamic">
            <mat-select id="category-parent" formControlName="parent_id">
              <mat-option [value]="null">Sin padre (raiz)</mat-option>
              @for (parent of data.parents; track parent.id) {
                <mat-option [value]="parent.id">{{ parent.name }}</mat-option>
              }
            </mat-select>
            <mat-hint>Las hijas heredan la posicion jerarquica del padre.</mat-hint>
            <mat-error>
              @if (form.controls.parent_id.hasError('backend')) {
                {{ form.controls.parent_id.getError('backend') }}
              }
            </mat-error>
          </mat-form-field>
        </div>
        <div class="dialog__field">
          <label class="dialog__label" for="category-slug">Slug (opcional)</label>
          <mat-form-field appearance="outline" subscriptSizing="dynamic">
            <input
              matInput
              id="category-slug"
              type="text"
              formControlName="slug"
              class="app-mono"
            />
            <mat-hint>Vacio significa que el servidor lo deriva del nombre.</mat-hint>
            <mat-error>
              @if (form.controls.slug.hasError('backend')) {
                {{ form.controls.slug.getError('backend') }}
              }
            </mat-error>
          </mat-form-field>
        </div>
        @if (isEdit) {
          <mat-checkbox formControlName="is_active">
            Categoria activa
          </mat-checkbox>
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
        {{ saving() ? 'Guardando' : isEdit ? 'Guardar cambios' : 'Crear categoria' }}
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
export class CategoryDialogComponent {
  protected readonly data: CategoryDialogData = inject(MAT_DIALOG_DATA);
  private readonly ref: MatDialogRef<CategoryDialogComponent, Category | null> =
    inject(MatDialogRef);
  private readonly service = inject(CategoriesService);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly isEdit: boolean = this.data.category !== undefined;
  protected readonly banner = signal<string | null>(null);
  private readonly savingSignal = signal(false);
  protected readonly saving = this.savingSignal.asReadonly();

  protected readonly form = this.formBuilder.nonNullable.group({
    name: [this.data.category?.name ?? '', [Validators.required, Validators.maxLength(150)]],
    parent_id: [this.data.category?.parent_id ?? null],
    slug: [this.data.category?.slug ?? ''],
    is_active: [this.data.category?.is_active ?? true],
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

    // El slug vacio no se envia: el backend lo deriva del nombre.
    const slug = this.form.controls.slug.value.trim();
    const parentId = this.form.controls.parent_id.value;
    this.savingSignal.set(true);

    const request =
      this.data.category === undefined
        ? this.service.create({
            name: this.form.controls.name.value.trim(),
            parent_id: parentId,
            ...(slug.length > 0 ? { slug } : {}),
          })
        : this.service.update(this.data.category.id, {
            name: this.form.controls.name.value.trim(),
            parent_id: parentId,
            is_active: this.form.controls.is_active.value,
          });

    request.subscribe({
      next: (category) => this.ref.close(category),
      error: (error: unknown) => {
        this.savingSignal.set(false);
        this.handleError(error);
      },
    });
  }

  private handleError(error: unknown): void {
    if (!(error instanceof AppError)) {
      this.banner.set('No se pudo guardar la categoria. Intente de nuevo.');
      return;
    }

    if (error.kind === 'validation') {
      let matched = false;

      for (const detail of error.details) {
        if (detail.field === 'name' || detail.field === 'parent_id' || detail.field === 'slug') {
          this.form.controls[detail.field].setErrors({ backend: detail.message });
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
    for (const control of [this.form.controls.name, this.form.controls.parent_id, this.form.controls.slug]) {
      if (control.hasError('backend')) {
        control.setErrors(null);
      }
    }
  }
}
