import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';

/**
 * Estado vacio honesto: explica que falta y como poblarlo, sin inventar datos.
 * Admite una accion proyectada cuando el usuario tiene permiso para actuar
 * (design-system.md §9).
 */
@Component({
  selector: 'app-empty-state',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatIconModule],
  template: `
    <div class="empty" role="status">
      <mat-icon class="empty__icon" aria-hidden="true">{{ icon() }}</mat-icon>
      <h2 class="empty__title app-tight">{{ title() }}</h2>
      @if (message(); as messageText) {
        <p class="empty__message">{{ messageText }}</p>
      }
      <div class="empty__action">
        <ng-content />
      </div>
    </div>
  `,
  styles: `
    .empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: var(--app-element-gap);
      padding: var(--app-space-8) var(--app-card-padding);
      color: var(--mat-sys-on-surface-variant);
    }

    /* Reservo el acento unico al icono para que el estado respire sin
    competir con la accion proyectada (design-system.md §4). */
    .empty__icon {
      font-size: 40px;
      width: 40px;
      height: 40px;
      color: var(--mat-sys-primary);
    }

    .empty__title {
      margin: 0;
      font: var(--mat-sys-title-medium);
      color: var(--mat-sys-on-surface);
    }

    .empty__message {
      margin: 0;
      max-inline-size: 46ch;
      font: var(--mat-sys-body-medium);
    }

    .empty__action {
      margin-block-start: var(--app-element-gap);
    }

    .empty__action:empty {
      display: none;
    }
  `,
})
export class EmptyStateComponent {
  /** Icono de Material Symbols. */
  public readonly icon = input('inbox');
  /** Titulo del estado vacio. */
  public readonly title = input.required<string>();
  /** Explicacion de que falta. */
  public readonly message = input('');
}
