import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

/**
 * Estado de error en espanol junto al contenido afectado (design-system.md §9).
 * Distingue el mensaje segun la familia del error y ofrece reintentar cuando la
 * operacion es repetible.
 */
@Component({
  selector: 'app-error-state',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatButtonModule, MatIconModule],
  template: `
    <div class="error" role="alert">
      <mat-icon class="error__icon" aria-hidden="true">error</mat-icon>
      <div class="error__body">
        <h2 class="error__title app-tight">{{ title() }}</h2>
        <p class="error__message">{{ message() }}</p>
        @if (errorId(); as id) {
          <p class="error__ref app-mono app-tabular">Referencia: {{ id }}</p>
        }
      </div>
      <button mat-stroked-button type="button" class="error__retry" (click)="retry.emit()">
        <mat-icon aria-hidden="true">refresh</mat-icon>
        Reintentar
      </button>
    </div>
  `,
  styles: `
    .error {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: var(--app-space-3);
      padding: var(--app-card-padding);
      border: 1px solid var(--mat-sys-outline-variant);
      border-radius: var(--mat-sys-corner-medium);
      background: var(--mat-sys-error-container);
      color: var(--mat-sys-on-error-container);
    }

    /* Mantengo el icono en el rol semantico de error: el color aqui
    comunica estado, no decora (design-system.md §4). */
    .error__icon {
      color: var(--mat-sys-error);
    }

    .error__body {
      flex: 1;
    }

    .error__title {
      margin: 0;
      font: var(--mat-sys-title-small);
    }

    .error__message {
      margin: var(--app-space-1) 0 0;
      font: var(--mat-sys-body-medium);
    }

    .error__ref {
      margin: var(--app-space-1) 0 0;
      font: var(--mat-sys-body-small);
    }

    /* Doy respuesta fisica al reintento, como en toda accion del
    proyecto (design-system.md §7). */
    .error__retry {
      transition: transform 140ms ease;
    }

    .error__retry:active {
      transform: scale(0.98);
    }
  `,
})
export class ErrorStateComponent {
  /** Titulo corto del error. */
  public readonly title = input('No se pudieron cargar los datos');
  /** Mensaje en espanol, ya normalizado por el backend o el ErrorService. */
  public readonly message = input.required<string>();
  /** Identificador de correlacion del 500, si existe. */
  public readonly errorId = input<string | null>(null);
  /** Solicita reintentar la operacion. */
  public readonly retry = output<void>();
}
