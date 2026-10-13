import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import type { ItemResponse } from '../../core/models/api.model';
import type {
  CatalogApplySummary,
  CatalogDryRunReport,
} from '../../core/models/catalog.model';

/** Formato de plantilla admitido por el endpoint (api-design.md §7.13). */
export type CatalogTemplateFormat = 'xlsx' | 'csv';

/**
 * Acceso a la importacion de catalogo (api-design.md §7.13, RF-126 a RF-132).
 * Los tres endpoints exigen catalog.create. La plantilla se descarga como
 * binario, la validacion previa no escribe nada y la aplicacion es atomica e
 * idempotente por SKU. El archivo viaja como multipart en el campo archivo.
 */
@Injectable({ providedIn: 'root' })
export class CatalogImportService {
  private readonly api = inject(ApiService);

  /** Descarga la plantilla con columnas y fila de ejemplo en el formato pedido. */
  public template(format: CatalogTemplateFormat): Observable<Blob> {
    return this.api.download('/imports/catalog/template', { format });
  }

  /** Dry-run: informe por fila sin modificar datos (AC-062). */
  public validate(file: File): Observable<CatalogDryRunReport> {
    return this.api
      .upload<ItemResponse<CatalogDryRunReport>>('/imports/catalog/validate', file, 'archivo')
      .pipe(map((response) => response.data));
  }

  /**
   * Aplicacion atomica: 201 con creadas, actualizadas y referencia de
   * auditoria, o 422 con el informe por fila sin haber aplicado nada.
   */
  public apply(file: File): Observable<CatalogApplySummary> {
    return this.api
      .upload<ItemResponse<CatalogApplySummary>>('/imports/catalog', file, 'archivo')
      .pipe(map((response) => response.data));
  }
}
