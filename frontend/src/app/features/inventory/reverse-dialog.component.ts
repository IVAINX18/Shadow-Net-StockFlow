import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';

/**
 * Dialogo de reversion: pide el motivo obligatorio que exige el backend para
 * revertir un confirmado. Devuelve el motivo o null si se cancela.
 */
@Component({
  selector: 'app-reverse-dialog',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, MatDialogModule, MatButtonModule, MatFormFieldModule, MatInputModule],
  template: `
    <h2 mat-dialog-title class="reverse__title app-tight">Revertir {{ docNumber }}</h2>
    <mat-dialog-content>
      <p class="reverse__message">
        La reversion genera el movimiento contrario sin borrar el original. Explique el motivo:
      </p>
      <mat-form-field appearance="outline" class="reverse__field">
        <mat-label>Motivo de la reversion</mat-label>
        <textarea matInput rows="3" maxlength="500" [formControl]="reasonControl"></textarea>
        @if (reasonControl.invalid && reasonControl.touched) {
          <mat-error>El motivo es obligatorio.</mat-error>
        }
      </mat-form-field>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button mat-button type="button" (click)="onCancel()">Cancelar</button>
      <button mat-flat-button color="warn" type="button" [disabled]="sending()" (click)="onConfirm()">
        Revertir
      </button>
    </mat-dialog-actions>
  `,
  styles: `
    .reverse__title {
      font: var(--mat-sys-title-large);
    }

    .reverse__message {
      margin: 0 0 var(--app-element-gap);
      font: var(--mat-sys-body-medium);
      color: var(--mat-sys-on-surface-variant);
    }

    .reverse__field {
      inline-size: 100%;
    }
  `,
})
export class ReverseDialogComponent {
  protected readonly docNumber: string = inject<string>(MAT_DIALOG_DATA);
  private readonly ref: MatDialogRef<ReverseDialogComponent, string | null> = inject(MatDialogRef);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly reasonControl = this.formBuilder.control('', {
    nonNullable: true,
    validators: [Validators.required, Validators.maxLength(500)],
  });
  protected readonly sending = signal(false);

  protected onCancel(): void {
    this.ref.close(null);
  }

  protected onConfirm(): void {
    this.reasonControl.markAsTouched();

    if (this.reasonControl.invalid) {
      return;
    }

    this.sending.set(true);
    this.ref.close(this.reasonControl.value.trim());
  }
}
