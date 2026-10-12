import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { FormsModule } from '@angular/forms';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { StatusChipComponent } from '../../shared/components/status-chip/status-chip.component';
import type { ChipTone } from '../../shared/components/status-chip/status-chip.component';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state.component';
import { ErrorStateComponent } from '../../shared/components/error-state/error-state.component';
import { LoadingSkeletonComponent } from '../../shared/components/loading-skeleton/loading-skeleton.component';
import { KpiCardComponent } from '../../shared/components/kpi-card/kpi-card.component';
import { DataTableComponent } from '../../shared/components/data-table/data-table.component';
import type { DataTableColumn } from '../../shared/components/data-table/data-table.model';
import { ThemeService } from '../../core/services/theme.service';

/** Muestra un token de color con su nombre para revision visual. */
interface TokenSwatch {
  readonly token: string;
  readonly label: string;
}

// Fixture de la guia: dos filas genericas que solo existen para revisar la
// tabla (encabezado, chip de estado, alineacion). No representan datos de
// negocio; las cifras reales siempre vienen del backend (design-system.md §11).
interface GuideRow {
  readonly referencia: string;
  readonly estado: ChipTone;
  readonly etiqueta: string;
}

/**
 * Guia de estilo interna. Renderiza tokens, escala tipografica, botones,
 * campos, chips y los estados de interfaz para revisar el sistema de diseno en
 * el navegador, en ambos temas. No es una pantalla de producto: es la
 * verificacion visual del contrato de diseno.
 */
@Component({
  selector: 'app-style-guide-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    FormsModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatIconModule,
    PageHeaderComponent,
    StatusChipComponent,
    EmptyStateComponent,
    ErrorStateComponent,
    LoadingSkeletonComponent,
    KpiCardComponent,
    DataTableComponent,
  ],
  templateUrl: './style-guide-page.component.html',
  styleUrl: './style-guide-page.component.scss',
})
export class StyleGuidePageComponent {
  private readonly themeService = inject(ThemeService);

  /** Indica si el tema activo es oscuro, para el conmutador. */
  protected readonly isDark = this.themeService.isDark;

  protected readonly surfaceTokens: readonly TokenSwatch[] = [
    { token: '--mat-sys-surface', label: 'Surface' },
    { token: '--mat-sys-surface-container-low', label: 'Container low' },
    { token: '--mat-sys-surface-container', label: 'Container' },
    { token: '--mat-sys-surface-container-high', label: 'Container high' },
    { token: '--mat-sys-outline-variant', label: 'Outline variant' },
  ];

  protected readonly accentTokens: readonly TokenSwatch[] = [
    { token: '--mat-sys-primary', label: 'Primary' },
    { token: '--mat-sys-primary-container', label: 'Primary container' },
    { token: '--mat-sys-secondary-container', label: 'Secondary container' },
    { token: '--mat-sys-tertiary-container', label: 'Tertiary container' },
    { token: '--mat-sys-error', label: 'Error' },
    { token: '--mat-sys-error-container', label: 'Error container' },
  ];

  protected readonly typography: readonly { token: string; label: string; sample: string }[] = [
    { token: 'var(--mat-sys-display-small)', label: 'Display small', sample: 'StockFlow' },
    { token: 'var(--mat-sys-headline-small)', label: 'Headline small', sample: 'Resumen de almacen' },
    { token: 'var(--mat-sys-title-large)', label: 'Title large', sample: 'Movimientos del dia' },
    { token: 'var(--mat-sys-title-medium)', label: 'Title medium', sample: 'Kardex por producto' },
    { token: 'var(--mat-sys-body-large)', label: 'Body large', sample: 'Texto general de la interfaz.' },
    { token: 'var(--mat-sys-body-medium)', label: 'Body medium', sample: 'Texto de apoyo y descripciones.' },
    { token: 'var(--mat-sys-label-large)', label: 'Label large', sample: 'ETIQUETA DE CAMPO' },
    { token: 'var(--mat-sys-label-small)', label: 'Label small', sample: 'ESTADO' },
  ];

  protected readonly tones: readonly ChipTone[] = ['neutral', 'info', 'success', 'warning', 'error'];

  protected readonly zIndexScale: readonly { name: string; value: string }[] = [
    { name: '--app-z-base', value: '0' },
    { name: '--app-z-sticky', value: '100' },
    { name: '--app-z-drawer', value: '200' },
    { name: '--app-z-appbar', value: '300' },
    { name: '--app-z-overlay', value: '400' },
    { name: '--app-z-toast', value: '500' },
  ];

  // Cifra tabular de ejemplo: los decimales se presentan desde la cadena del
  // contrato y nunca se convierten a float.
  protected readonly decimalSample = '12.500';

  protected readonly guideRows: readonly GuideRow[] = [
    { referencia: 'EJEMPLO-1', estado: 'success', etiqueta: 'Activo' },
    { referencia: 'EJEMPLO-2', estado: 'neutral', etiqueta: 'Inactivo' },
  ];

  protected readonly guideColumns: readonly DataTableColumn<GuideRow>[] = [
    {
      key: 'referencia',
      header: 'Referencia',
      cell: (row) => row.referencia,
      sortable: true,
    },
    {
      key: 'estado',
      header: 'Estado',
      cell: (row) => row.etiqueta,
      status: (row) => row.estado,
      sortable: true,
    },
  ];

  protected toggleTheme(): void {
    this.themeService.toggle();
  }

  protected onNoop(): void {
    // Boton de demostracion: no ejecuta accion para no aparentar una funcion
    // real en la guia de estilo.
  }
}
