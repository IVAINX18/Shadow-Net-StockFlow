import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { KpiCardComponent } from '../../shared/components/kpi-card/kpi-card.component';
import { ErrorStateComponent } from '../../shared/components/error-state/error-state.component';
import { LoadingSkeletonComponent } from '../../shared/components/loading-skeleton/loading-skeleton.component';
import { DashboardService } from './dashboard.service';
import type { DashboardCounter, PendingDependency } from './dashboard.service';
import { AppError } from '../../core/models/app-error.model';

/**
 * Tablero. Muestra solo los indicadores que las API reales de la Fase 1
 * soportan (almacenes, usuarios, roles y permisos) y, aparte, tarjetas de
 * dependencia que declaran que indicadores necesitan las fases 2 a 5. No hay
 * cifras inventadas, ni tendencias, ni graficos de relleno.
 */
@Component({
  selector: 'app-dashboard-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    MatIconModule,
    PageHeaderComponent,
    KpiCardComponent,
    ErrorStateComponent,
    LoadingSkeletonComponent,
  ],
  templateUrl: './dashboard-page.component.html',
  styleUrl: './dashboard-page.component.scss',
})
export class DashboardPageComponent {
  private readonly dashboard = inject(DashboardService);

  protected readonly counters = signal<readonly DashboardCounter[]>([]);
  protected readonly pending = signal<readonly PendingDependency[]>([]);
  protected readonly loading = signal(false);
  protected readonly error = signal<AppError | null>(null);

  protected readonly errorMessage = computed(() => this.error()?.message ?? null);
  protected readonly errorId = computed(() => this.error()?.errorId ?? null);

  public constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    this.dashboard.load().subscribe({
      next: (indicators) => {
        this.counters.set(indicators.counters);
        this.pending.set(indicators.pending);
        this.loading.set(false);
      },
      error: (error: unknown) => {
        this.error.set(error instanceof AppError ? error : null);
        this.loading.set(false);
      },
    });
  }

  // Los indicadores sin dato se muestran como "n/d" con su explicacion, en
  // lugar de un cero que sugeriria un conteo real de cero.
  protected displayValue(counter: DashboardCounter): string {
    return counter.value === null ? 'n/d' : String(counter.value);
  }
}
