import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/** Forma del esqueleto de carga. */
export type SkeletonVariant = 'table' | 'cards' | 'text';

/**
 * Esqueleto con la forma del contenido final (filas de tabla o tarjetas), no un
 * indicador circular generico (design-system.md §9). Respeta la preferencia de
 * movimiento reducido.
 */
@Component({
  selector: 'app-loading-skeleton',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @switch (variant()) {
      @case ('cards') {
        <div class="skeleton-cards" aria-hidden="true">
          @for (item of items(); track item) {
            <span class="skeleton skeleton--card"></span>
          }
        </div>
      }
      @case ('text') {
        <div class="skeleton-text" aria-hidden="true">
          @for (item of items(); track item) {
            <span class="skeleton skeleton--line"></span>
          }
        </div>
      }
      @default {
        <div class="skeleton-table" aria-hidden="true">
          @for (item of items(); track item) {
            <span class="skeleton skeleton--row"></span>
          }
        </div>
      }
    }
    <span class="sr-only" role="status">Cargando contenido</span>
  `,
  styles: `
    .skeleton {
      display: block;
      border-radius: var(--mat-sys-corner-extra-small);
      background: linear-gradient(
        90deg,
        var(--mat-sys-surface-container-high) 25%,
        var(--mat-sys-surface-container-highest) 37%,
        var(--mat-sys-surface-container-high) 63%
      );
      background-size: 400% 100%;
      animation: skeleton-shimmer 1.4s ease infinite;
    }

    .skeleton-table,
    .skeleton-text {
      display: flex;
      flex-direction: column;
      gap: var(--app-element-gap);
    }

    .skeleton--row { block-size: 40px; }
    .skeleton--line { block-size: 16px; }

    .skeleton-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: var(--app-space-4);
    }

    .skeleton--card {
      block-size: 96px;
      border-radius: var(--mat-sys-corner-medium);
    }

    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    @keyframes skeleton-shimmer {
      0% { background-position: 100% 50%; }
      100% { background-position: 0 50%; }
    }

    @media (prefers-reduced-motion: reduce) {
      .skeleton { animation: none; }
    }
  `,
})
export class LoadingSkeletonComponent {
  /** Forma del esqueleto. */
  public readonly variant = input<SkeletonVariant>('table');
  /** Numero de elementos a dibujar. */
  public readonly rows = input(6);

  // Genero una lista estable para el bucle de la plantilla; no dependo del
  // indice de `@for` para no repetir marcas cuando el conteo cambia.
  protected readonly items = computed(() =>
    Array.from({ length: Math.max(1, Math.trunc(this.rows())) }, (_value, index) => index),
  );
}
