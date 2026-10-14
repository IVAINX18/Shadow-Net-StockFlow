import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import type { SpacingRow } from './style-guide-models';

/**
 * Espacio y movimiento de la guia: elevacion, ritmo de composicion y estados de
 * interaccion. Agrupo lo que el contrato trata como comportamiento fisico de la
 * interfaz (design-system.md §7 y §8) para repartir el peso de la hoja original.
 */
@Component({
  selector: 'app-guide-space-motion',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatButtonModule, MatIconModule],
  templateUrl: './guide-space-motion.component.html',
  styleUrl: './guide-space-motion.component.scss',
})
export class GuideSpaceMotionComponent {
  /** Ritmo de composicion del contrato (§8). */
  public readonly rhythmTokens = input.required<readonly SpacingRow[]>();
  /** Escala general de 4 px a 32 px tal como la emite :root en styles.scss. */
  public readonly spaceScale = input.required<readonly SpacingRow[]>();

  protected onNoop(): void {
    // Boton de demostracion: no ejecuta accion para no aparentar una funcion
    // real en la guia de estilo.
  }
}
