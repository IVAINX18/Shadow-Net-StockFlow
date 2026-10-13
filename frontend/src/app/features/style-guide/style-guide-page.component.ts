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

/** Fila de ritmo o escala: el valor declarado es el que emite el token. */
interface SpacingRow {
  readonly token: string;
  readonly value: string;
  readonly use: string;
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

  protected readonly typography: readonly { token: string; label: string; sample: string; tight: boolean }[] = [
    { token: 'var(--mat-sys-display-small)', label: 'Display small', sample: 'StockFlow', tight: true },
    { token: 'var(--mat-sys-headline-small)', label: 'Headline small', sample: 'Resumen de almacen', tight: true },
    { token: 'var(--mat-sys-title-large)', label: 'Title large', sample: 'Movimientos del dia', tight: true },
    { token: 'var(--mat-sys-title-medium)', label: 'Title medium', sample: 'Kardex por producto', tight: true },
    { token: 'var(--mat-sys-body-large)', label: 'Body large', sample: 'Texto general de la interfaz.', tight: false },
    { token: 'var(--mat-sys-body-medium)', label: 'Body medium', sample: 'Texto de apoyo y descripciones.', tight: false },
    { token: 'var(--mat-sys-label-large)', label: 'Label large', sample: 'ETIQUETA DE CAMPO', tight: false },
    { token: 'var(--mat-sys-label-small)', label: 'Label small', sample: 'ESTADO', tight: false },
  ];

  // Ritmo de composicion del contrato (§8): los valores son los que declaran
  // los tokens, asi que la pagina los muestra sin inventar ninguno.
  protected readonly rhythmTokens: readonly SpacingRow[] = [
    { token: '--app-page-gutter', value: '24 px', use: 'Margen interior del area de contenido' },
    { token: '--app-section-gap', value: '24 px', use: 'Separacion entre bloques de una pantalla' },
    { token: '--app-card-padding', value: '16 px', use: 'Relleno interior de tarjetas y contenedores' },
    { token: '--app-element-gap', value: '8 px', use: 'Separacion entre elementos de un mismo bloque' },
  ];

  // Escala general de 4 px a 32 px tal como la emite :root en styles.scss.
  protected readonly spaceScale: readonly SpacingRow[] = [
    { token: '--app-space-1', value: '4 px', use: 'Separacion minima' },
    { token: '--app-space-2', value: '8 px', use: 'Elementos de un bloque' },
    { token: '--app-space-3', value: '12 px', use: 'Relleno compacto' },
    { token: '--app-space-4', value: '16 px', use: 'Relleno de tarjeta' },
    { token: '--app-space-5', value: '20 px', use: 'Paso intermedio' },
    { token: '--app-space-6', value: '24 px', use: 'Separacion entre bloques' },
    { token: '--app-space-8', value: '32 px', use: 'Separacion amplia' },
  ];

  protected readonly tones: readonly ChipTone[] = ['neutral', 'info', 'success', 'warning', 'error'];

  // Traduzco la clave tecnica del tono a copy de interfaz en espanol para que
  // la guia se lea como producto y no como codigo (design-system.md §12).
  // Mantengo el bucle de la plantilla y solo mapeo la etiqueta.
  protected readonly toneLabels: Readonly<Record<ChipTone, string>> = {
    neutral: 'Neutral',
    info: 'Informativo',
    success: 'Exito',
    warning: 'Advertencia',
    error: 'Error',
  };

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
