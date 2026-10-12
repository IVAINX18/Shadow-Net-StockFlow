import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';

/**
 * Tarjeta de indicador. Comparte un unico tratamiento de superficie y reserva
 * el acento para el icono, como corrige design-system.md §15 (sin saturacion
 * por bloques ni acentos multiples). El valor llega como cadena porque los
 * decimales del contrato viajan como texto y no deben convertirse a float.
 */
@Component({
  selector: 'app-kpi-card',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatIconModule],
  template: `
    <article class="kpi" [attr.aria-busy]="loading()">
      <div class="kpi__head">
        <span class="kpi__label">{{ label() }}</span>
        <mat-icon class="kpi__icon" aria-hidden="true">{{ icon() }}</mat-icon>
      </div>
      @if (loading()) {
        <span class="kpi__value-skeleton" aria-hidden="true"></span>
      } @else {
        <p class="kpi__value">{{ value() }}</p>
      }
      @if (hint(); as hintText) {
        <p class="kpi__hint">{{ hintText }}</p>
      }
    </article>
  `,
  styles: `
    .kpi {
      display: flex;
      flex-direction: column;
      gap: var(--app-space-2);
      padding: var(--app-space-4);
      border-radius: var(--mat-sys-corner-medium);
      background: var(--mat-sys-surface-container);
      border: 1px solid var(--mat-sys-outline-variant);
    }

    .kpi__head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: var(--app-space-2);
    }

    .kpi__label {
      font: var(--mat-sys-label-medium);
      color: var(--mat-sys-on-surface-variant);
    }

    .kpi__icon {
      color: var(--mat-sys-primary);
    }

    .kpi__value {
      margin: 0;
      font: var(--mat-sys-headline-medium);
      color: var(--mat-sys-on-surface);
      font-variant-numeric: tabular-nums;
    }

    .kpi__value-skeleton {
      display: block;
      inline-size: 4rem;
      block-size: 2rem;
      border-radius: var(--mat-sys-corner-small);
      background: var(--mat-sys-surface-container-highest);
      animation: kpi-pulse 1.4s ease-in-out infinite;
    }

    .kpi__hint {
      margin: 0;
      font: var(--mat-sys-body-small);
      color: var(--mat-sys-on-surface-variant);
    }

    @keyframes kpi-pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }

    @media (prefers-reduced-motion: reduce) {
      .kpi__value-skeleton { animation: none; }
    }
  `,
})
export class KpiCardComponent {
  /** Etiqueta del indicador. */
  public readonly label = input.required<string>();
  /** Valor ya formateado (cadenas tabulares para las cifras). */
  public readonly value = input.required<string>();
  /** Nombre del icono de Material Symbols. */
  public readonly icon = input('insights');
  /** Aclaracion opcional del indicador. */
  public readonly hint = input('');
  /** Muestra el esqueleto mientras el dato real esta en camino. */
  public readonly loading = input(false);
}
