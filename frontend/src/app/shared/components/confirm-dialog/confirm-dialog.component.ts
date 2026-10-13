import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import {
  MAT_DIALOG_DATA,
  MatDialogModule,
  MatDialogRef,
} from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';

/** Datos del dialogo de confirmacion. */
export interface ConfirmDialogData {
  /** Titulo del dialogo. */
  readonly title: string;
  /** Explicacion de la consecuencia, en español. */
  readonly message: string;
  /** Etiqueta del boton de confirmacion. */
  readonly confirmLabel: string;
  /** Tono del boton de confirmacion. */
  readonly confirmColor?: 'primary' | 'warn';
}

/**
 * Dialogo de confirmacion para acciones destructivas o irreversibles
 * (design-system.md §9). Nunca se usa window.confirm: toda confirmacion pasa
 * por este dialogo de Material con la consecuencia explicada.
 */
@Component({
  selector: 'app-confirm-dialog',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatDialogModule, MatButtonModule, MatIconModule],
  template: `
    <h2 mat-dialog-title class="confirm__title app-tight">{{ data.title }}</h2>
    <mat-dialog-content>
      <p class="confirm__message">{{ data.message }}</p>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button mat-button type="button" (click)="onCancel()">Cancelar</button>
      <button
        mat-flat-button
        type="button"
        [color]="data.confirmColor ?? 'warn'"
        (click)="onConfirm()"
      >
        <mat-icon aria-hidden="true">warning</mat-icon>
        {{ data.confirmLabel }}
      </button>
    </mat-dialog-actions>
  `,
  styles: `
    .confirm__title {
      font: var(--mat-sys-title-large);
    }

    .confirm__message {
      margin: 0;
      font: var(--mat-sys-body-medium);
      color: var(--mat-sys-on-surface-variant);
    }
  `,
})
export class ConfirmDialogComponent {
  protected readonly data: ConfirmDialogData = inject(MAT_DIALOG_DATA);
  private readonly ref: MatDialogRef<ConfirmDialogComponent, boolean> = inject(MatDialogRef);

  protected onCancel(): void {
    this.ref.close(false);
  }

  protected onConfirm(): void {
    this.ref.close(true);
  }
}
