import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { MatPaginatorModule, type PageEvent } from '@angular/material/paginator';
import { MatTableModule } from '@angular/material/table';
import { EmptyStateComponent } from '../empty-state/empty-state.component';
import { ErrorStateComponent } from '../error-state/error-state.component';
import { LoadingSkeletonComponent } from '../loading-skeleton/loading-skeleton.component';
import { StatusChipComponent } from '../status-chip/status-chip.component';
import type {
  DataTableColumn,
  PageChange,
  SortChange,
  SortDirection,
} from './data-table.model';

/**
 * Envoltorio de tabla de datos con los cuatro estados (carga, vacio, error y
 * datos), ordenamiento por encabezado y paginacion. Es generico sobre el tipo
 * de fila: las columnas reciben la fila real tipada y devuelven su texto, de
 * modo que cada pagina no reimplementa la tabla (design-system.md §9 y §15).
 */
@Component({
  selector: 'app-data-table',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    MatTableModule,
    MatPaginatorModule,
    MatIconModule,
    EmptyStateComponent,
    ErrorStateComponent,
    LoadingSkeletonComponent,
    StatusChipComponent,
  ],
  templateUrl: './data-table.component.html',
  styleUrl: './data-table.component.scss',
})
export class DataTableComponent<T> {
  /** Columnas visibles. */
  public readonly columns = input.required<readonly DataTableColumn<T>[]>();
  /** Filas de la pagina actual. */
  public readonly rows = input.required<readonly T[]>();
  /** Total de registros del backend (para el paginador). */
  public readonly total = input(0);
  /** Pagina actual, 1-based como la API. */
  public readonly page = input(1);
  /** Tamano de pagina. */
  public readonly pageSize = input(15);
  /** Estado de carga. */
  public readonly loading = input(false);
  /** Mensaje de error ya normalizado, o null. */
  public readonly error = input<string | null>(null);
  /** Identificador de correlacion del error, si lo hay. */
  public readonly errorId = input<string | null>(null);
  /** Titulo del estado vacio. */
  public readonly emptyTitle = input('Sin registros');
  /** Explicacion del estado vacio. */
  public readonly emptyMessage = input('');
  /** Icono del estado vacio. */
  public readonly emptyIcon = input('inbox');
  /** Clave de orden activa. */
  public readonly sortKey = input<string | null>(null);
  /** Direccion de orden activa. */
  public readonly sortDirection = input<SortDirection>('asc');

  /** Solicitud de orden. */
  public readonly sortChange = output<SortChange>();
  /** Solicitud de cambio de pagina. */
  public readonly pageChange = output<PageChange>();
  /** Solicitud de reintento tras un error. */
  public readonly retry = output<void>();

  protected readonly displayedColumns = computed(() => this.columns().map((column) => column.key));

  // Mientras cargo, dibujo solo las filas que caben en pantalla para no generar
  // un esqueleto desproporcionado.
  protected readonly skeletonRows = computed(() => Math.min(this.pageSize(), 8));

  protected ariaSort(column: DataTableColumn<T>): 'ascending' | 'descending' | 'none' {
    if (this.sortKey() !== this.orderKey(column)) {
      return 'none';
    }

    return this.sortDirection() === 'asc' ? 'ascending' : 'descending';
  }

  protected sortIcon(column: DataTableColumn<T>): string {
    if (this.sortKey() !== this.orderKey(column)) {
      return 'unfold_more';
    }

    return this.sortDirection() === 'asc' ? 'arrow_upward' : 'arrow_downward';
  }

  protected onSort(column: DataTableColumn<T>): void {
    const key = this.orderKey(column);
    // Si ya ordenaba por esta columna, invierto; si no, empiezo ascendente.
    const direction: SortDirection =
      this.sortKey() === key && this.sortDirection() === 'asc' ? 'desc' : 'asc';

    this.sortChange.emit({ key, direction });
  }

  protected onPage(event: PageEvent): void {
    // El paginador es 0-based y la API 1-based: la conversion vive aqui.
    this.pageChange.emit({ page: event.pageIndex + 1, pageSize: event.pageSize });
  }

  private orderKey(column: DataTableColumn<T>): string {
    return column.sortKey ?? column.key;
  }
}
