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
        <h1 class="page-header__title app-tight">{{ title() }}</h1>
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
      gap: var(--app-card-padding);
      padding-block: var(--app-space-5);
      border-bottom: 1px solid var(--mat-sys-outline-variant);
      margin-bottom: var(--app-section-gap);
    }

    .page-header__title {
      margin: 0;
      // Titulo de pagina a 32 px con tratamiento display (peso del token y
      // tracking de .app-tight): desde aqui suben todos los titulos de las
      // pantallas sin editar ninguna.
      font: var(--mat-sys-headline-large);
      font-weight: var(--app-title-weight);
      color: var(--mat-sys-on-surface);
    }

    .page-header__subtitle {
      margin: var(--app-space-1) 0 0;
      font: var(--mat-sys-body-medium);
      color: var(--mat-sys-on-surface-variant);
    }

    .page-header__actions {
      display: flex;
      align-items: center;
      gap: var(--app-element-gap);
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
