import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import type { TokenSwatch, TypeSample } from './style-guide-models';

/**
 * Fundamentos visuales de la guia: color, tipografia y forma. Agrupo las tres
 * secciones que documentan tokens (design-system.md §3 a §7) para que cada hoja
 * quede por debajo del presupuesto de 4 kB sin recortar ni un estilo.
 */
@Component({
  selector: 'app-guide-foundation',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './guide-foundation.component.html',
  styleUrl: './guide-foundation.component.scss',
})
export class GuideFoundationComponent {
  /** Roles de superficie del tema activo. */
  public readonly surfaceTokens = input.required<readonly TokenSwatch[]>();
  /** Roles de acento y semantica del tema activo. */
  public readonly accentTokens = input.required<readonly TokenSwatch[]>();
  /** Escala tipografica con su muestra. */
  public readonly typography = input.required<readonly TypeSample[]>();
  /** Cifra de ejemplo presentada desde la cadena del contrato, sin float. */
  public readonly decimalSample = input.required<string>();
}
