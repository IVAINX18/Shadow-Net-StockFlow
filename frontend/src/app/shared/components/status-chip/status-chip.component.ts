import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';

/** Tono semantico del chip. El color nunca es el unico portador de informacion. */
export type ChipTone = 'neutral' | 'info' | 'success' | 'warning' | 'error';

// Cada tono usa una familia tonal de Material 3 y un icono, de modo que el
// estado se distingue por texto e icono ademas del color (design-system.md §13).
const TONE_ICONS: Readonly<Record<ChipTone, string>> = {
  neutral: 'circle',
  info: 'info',
  success: 'check_circle',
  warning: 'warning',
  error: 'error',
};

/**
 * Chip de estado. Presenta el estado de un registro (activo, inactivo, etc.)
 * con color, icono y texto, sin depender solo del color.
 */
@Component({
  selector: 'app-status-chip',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatIconModule],
  template: `
    <span class="chip" [class]="'chip--' + tone()">
      <mat-icon class="chip__icon" aria-hidden="true">{{ icon() }}</mat-icon>
      <span class="chip__label">{{ label() }}</span>
    </span>
  `,
  styles: `
    .chip {
      display: inline-flex;
      align-items: center;
      gap: var(--app-space-1);
      padding: 2px var(--app-space-2);
      border-radius: var(--mat-sys-corner-full);
      font: var(--mat-sys-label-small);
      white-space: nowrap;
    }

    .chip__icon {
      font-size: 16px;
      width: 16px;
      height: 16px;
    }

    .chip--neutral {
      background: var(--mat-sys-surface-container-highest);
      color: var(--mat-sys-on-surface-variant);
    }

    .chip--info {
      background: var(--mat-sys-secondary-container);
      color: var(--mat-sys-on-secondary-container);
    }

    .chip--success {
      background: var(--mat-sys-tertiary-container);
      color: var(--mat-sys-on-tertiary-container);
    }

    .chip--warning {
      background: var(--mat-sys-secondary-container);
      color: var(--mat-sys-on-secondary-container);
    }

    .chip--error {
      background: var(--mat-sys-error-container);
      color: var(--mat-sys-on-error-container);
    }
  `,
})
export class StatusChipComponent {
  /** Texto del estado. */
  public readonly label = input.required<string>();
  /** Tono semantico. */
  public readonly tone = input<ChipTone>('neutral');

  protected readonly icon = computed(() => TONE_ICONS[this.tone()]);
}
