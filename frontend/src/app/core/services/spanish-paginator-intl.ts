import { Injectable } from '@angular/core';
import { MatPaginatorIntl } from '@angular/material/paginator';

// Paginador en español para toda la aplicacion (design-system.md §12: todo el
// texto visible va en español). Se provee una sola vez en app.config.ts y cada
// MatPaginator lo consume por inyeccion, asi ninguna tabla queda en ingles,
// incluidas las de almacenes, usuarios y roles que ya existian.
@Injectable()
export class SpanishPaginatorIntl extends MatPaginatorIntl {
  public override itemsPerPageLabel = 'Registros por pagina:';
  public override nextPageLabel = 'Pagina siguiente';
  public override previousPageLabel = 'Pagina anterior';
  public override firstPageLabel = 'Primera pagina';
  public override lastPageLabel = 'Ultima pagina';

  // En esta version de Material el rango es una propiedad, no un metodo: lo
  // redefino como propiedad para que el compilador lo acepte.
  public override getRangeLabel = (page: number, pageSize: number, length: number): string => {
    if (length === 0 || pageSize === 0) {
      return `0 de ${formatCount(length)}`;
    }

    const start = page * pageSize + 1;
    const end = Math.min((page + 1) * pageSize, length);

    return `${formatCount(start)} a ${formatCount(end)} de ${formatCount(length)}`;
  };
}

// Las cifras del rango son conteos de paginacion, no datos del dominio: el
// separador de miles solo ayuda a leer totales grandes.
function formatCount(value: number): string {
  return new Intl.NumberFormat('es-CO').format(value);
}
