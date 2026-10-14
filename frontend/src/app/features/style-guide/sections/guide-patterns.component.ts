import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import type { DataTableColumn } from '../../../shared/components/data-table/data-table.model';
import { EmptyStateComponent } from '../../../shared/components/empty-state/empty-state.component';
import { ErrorStateComponent } from '../../../shared/components/error-state/error-state.component';
import { KpiCardComponent } from '../../../shared/components/kpi-card/kpi-card.component';
import { LoadingSkeletonComponent } from '../../../shared/components/loading-skeleton/loading-skeleton.component';
import { StatusChipComponent } from '../../../shared/components/status-chip/status-chip.component';
import type { ChipTone } from '../../../shared/components/status-chip/status-chip.component';
import type { GuideRow, ZScaleEntry } from './style-guide-models';

/**
 * Patrones de la guia: campos, chips, tarjeta de indicador, tabla, estados de
 * interfaz y escala de apilamiento. Agrupo las secciones que componen
 * componentes compartidos (design-system.md §9, §10 y §14) para repartir el
 * peso de la hoja original.
 */
@Component({
  selector: 'app-guide-patterns',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    MatFormFieldModule,
    MatInputModule,
    StatusChipComponent,
    EmptyStateComponent,
    ErrorStateComponent,
    LoadingSkeletonComponent,
    KpiCardComponent,
    DataTableComponent,
  ],
  templateUrl: './guide-patterns.component.html',
  styleUrl: './guide-patterns.component.scss',
})
export class GuidePatternsComponent {
  /** Tonos semanticos que la guia recorre para mostrar cada chip. */
  public readonly tones = input.required<readonly ChipTone[]>();
  /** Etiqueta en español de cada tono tecnico. */
  public readonly toneLabels = input.required<Readonly<Record<ChipTone, string>>>();
  /** Filas de ejemplo para revisar la tabla. */
  public readonly guideRows = input.required<readonly GuideRow[]>();
  /** Columnas de ejemplo para revisar la tabla. */
  public readonly guideColumns = input.required<readonly DataTableColumn<GuideRow>[]>();
  /** Escala unica de apilamiento del proyecto. */
  public readonly zIndexScale = input.required<readonly ZScaleEntry[]>();

  protected onNoop(): void {
    // Boton de demostracion: no ejecuta accion para no aparentar una funcion
    // real en la guia de estilo.
  }
}
