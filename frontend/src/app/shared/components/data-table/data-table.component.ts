import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatPaginatorModule, MatPaginatorIntl, type PageEvent } from '@angular/material/paginator';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { EmptyStateComponent } from '../empty-state/empty-state.component';
import { ErrorStateComponent } from '../error-state/error-state.component';
import { LoadingSkeletonComponent } from '../loading-skeleton/loading-skeleton.component';
import { StatusChipComponent } from '../status-chip/status-chip.component';
import { SpanishPaginatorIntl } from '../../../core/services/spanish-paginator-intl';
import type {
  DataTableAction,
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
  // El paginador en español se provee aqui y no en la raiz: este componente
  // solo vive en paginas perezosas y ya trae el modulo del paginador, asi que
  // traducirlo aqui no mueve ni un byte al paquete inicial (design-system.md
  // §12 y §14). Cada MatPaginator de la tabla lo consume por inyeccion.
  providers: [{ provide: MatPaginatorIntl, useClass: SpanishPaginatorIntl }],
  imports: [
    MatTableModule,
    MatPaginatorModule,
    MatIconModule,
    MatButtonModule,
    MatTooltipModule,
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
  /** Acciones por fila. Vacio significa sin columna de acciones. */
  public readonly actions = input<readonly DataTableAction<T>[]>([]);
  /** Etiqueta de la accion del estado vacio. Vacia significa sin accion. */
  public readonly emptyActionLabel = input('');

  /** Solicitud de orden. */
  public readonly sortChange = output<SortChange>();
  /** Solicitud de cambio de pagina. */
  public readonly pageChange = output<PageChange>();
  /** Solicitud de reintento tras un error. */
  public readonly retry = output<void>();
  /** Accion del estado vacio (poblar la tabla). */
  public readonly emptyAction = output<void>();

  protected readonly hasActions = computed(() => this.actions().length > 0);
  protected readonly hasEmptyAction = computed(() => this.emptyActionLabel().length > 0);
  protected readonly displayedColumns = computed(() => {
    const keys = this.columns().map((column) => column.key);
    return this.hasActions() ? [...keys, ACTIONS_COLUMN] : keys;
  });

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

  // Acciones visibles para la fila: las ocultas por permiso no se dibujan.
  protected visibleActions(row: T): readonly DataTableAction<T>[] {
    return this.actions().filter((action) => action.hidden?.(row) !== true);
  }

  protected isDisabled(action: DataTableAction<T>, row: T): boolean {
    return action.disabled?.(row) === true;
  }

  private orderKey(column: DataTableColumn<T>): string {
    return column.sortKey ?? column.key;
  }
}

// La columna de acciones no pertenece a los datos: usa una clave reservada que
// ningun campo de la API puede colisionar.
const ACTIONS_COLUMN = '__actions';
