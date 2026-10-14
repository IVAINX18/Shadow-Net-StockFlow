import type { ChipTone } from '../../../shared/components/status-chip/status-chip.component';

/** Muestra un token de color con su nombre para revision visual. */
export interface TokenSwatch {
  readonly token: string;
  readonly label: string;
}

/** Fila de ritmo o escala: el valor declarado es el que emite el token. */
export interface SpacingRow {
  readonly token: string;
  readonly value: string;
  readonly use: string;
}

/** Muestra tipografica: el token compone la muestra y tight cierra el tracking. */
export interface TypeSample {
  readonly token: string;
  readonly label: string;
  readonly sample: string;
  readonly tight: boolean;
}

/** Nivel de la escala de apilamiento del contrato. */
export interface ZScaleEntry {
  readonly name: string;
  readonly value: string;
}

// Fixture de la guia: dos filas genericas que solo existen para revisar la
// tabla (encabezado, chip de estado, alineacion). No representan datos de
// negocio; las cifras reales siempre vienen del backend (design-system.md §11).
export interface GuideRow {
  readonly referencia: string;
  readonly estado: ChipTone;
  readonly etiqueta: string;
}
