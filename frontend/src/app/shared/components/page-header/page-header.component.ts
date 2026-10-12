import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/**
 * Cabecera de pagina reutilizable: titulo, subtitulo opcional y una zona de
 * acciones proyectada. Mantiene el mismo ritmo tipografico y de espaciado en
 * todas las pantallas (design-system.md §6 y §7).
 */
@Component({
  selector: 'app-page-header',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="page-header">
      <div class="page-header__titles">
        <h1 class="page-header__title">{{ title() }}</h1>
        @if (subtitle(); as sub) {
          <p class="page-header__subtitle">{{ sub }}</p>
        }
      </div>
      <div class="page-header__actions">
        <ng-content />
      </div>
    </header>
  `,
  styles: `
    .page-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: var(--app-space-4);
      padding-block: var(--app-space-5);
      border-bottom: 1px solid var(--mat-sys-outline-variant);
      margin-bottom: var(--app-space-5);
    }

    .page-header__title {
      margin: 0;
      font: var(--mat-sys-headline-small);
      color: var(--mat-sys-on-surface);
    }

    .page-header__subtitle {
      margin: 4px 0 0;
      font: var(--mat-sys-body-medium);
      color: var(--mat-sys-on-surface-variant);
    }

    .page-header__actions {
      display: flex;
      align-items: center;
      gap: var(--app-space-2);
      flex-wrap: wrap;
    }
  `,
})
export class PageHeaderComponent {
  /** Titulo de la pagina. */
  public readonly title = input.required<string>();
  /** Texto de apoyo bajo el titulo. */
  public readonly subtitle = input('');
}
