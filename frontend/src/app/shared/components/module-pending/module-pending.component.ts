import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { PageHeaderComponent } from '../page-header/page-header.component';

/**
 * Pantalla honesta de modulo pendiente. Nombra la fase que lo entregara y los
 * endpoints que hoy no existen, para no simular datos ni aparentar una funcion
 * que aun no esta construida. Los textos llegan como datos de la ruta.
 */
@Component({
  selector: 'app-module-pending',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatIconModule, PageHeaderComponent],
  template: `
    <app-page-header [title]="pageTitle()" [subtitle]="summary()" />

    <section class="pending">
      <div class="pending__banner">
        <mat-icon class="pending__icon" aria-hidden="true">construction</mat-icon>
        <div>
          <p class="pending__phase">Disponible en {{ phase() }}.</p>
          <p class="pending__note">
            Esta seccion se construira cuando existan los endpoints del backend. Hasta
            entonces no se muestran datos: no hay API que los provea.
          </p>
        </div>
      </div>

      @if (endpoints().length > 0) {
        <div class="pending__endpoints">
          <h2 class="pending__subtitle">Endpoints que faltan</h2>
          <ul class="pending__list">
            @for (endpoint of endpoints(); track endpoint) {
              <li class="pending__item">
                <mat-icon aria-hidden="true">api</mat-icon>
                <code>{{ endpoint }}</code>
              </li>
            }
          </ul>
        </div>
      }
    </section>
  `,
  styles: `
    .pending {
      display: flex;
      flex-direction: column;
      gap: var(--app-space-5);
    }

    .pending__banner {
      display: flex;
      gap: var(--app-space-4);
      align-items: flex-start;
      padding: var(--app-space-5);
      border-radius: var(--mat-sys-corner-medium);
      background: var(--mat-sys-surface-container);
      border: 1px solid var(--mat-sys-outline-variant);
    }

    .pending__icon {
      color: var(--mat-sys-primary);
    }

    .pending__phase {
      margin: 0;
      font: var(--mat-sys-title-medium);
    }

    .pending__note {
      margin: var(--app-space-1) 0 0;
      font: var(--mat-sys-body-medium);
      color: var(--mat-sys-on-surface-variant);
    }

    .pending__subtitle {
      margin: 0 0 var(--app-space-2);
      font: var(--mat-sys-title-small);
    }

    .pending__list {
      margin: 0;
      padding: 0;
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: var(--app-space-1);
    }

    .pending__item {
      display: flex;
      align-items: center;
      gap: var(--app-space-2);
      font: var(--mat-sys-body-medium);
      color: var(--mat-sys-on-surface-variant);
    }

    .pending__item mat-icon {
      font-size: 18px;
      width: 18px;
      height: 18px;
    }

    .pending__item code {
      font-family: 'Roboto Mono', monospace;
      font-variant-numeric: tabular-nums;
    }
  `,
})
export class ModulePendingComponent {
  /** Titulo de la seccion. */
  public readonly pageTitle = input.required<string>();
  /** Resumen de una linea. */
  public readonly summary = input('');
  /** Fase que entregara la funcionalidad. */
  public readonly phase = input('una fase posterior');
  /** Endpoints que faltan por existir. */
  public readonly endpoints = input<readonly string[]>([]);
}
