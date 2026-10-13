import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';
import { AppError } from '../../core/models/app-error.model';
import type {
  CatalogApplySummary,
  CatalogDryRunReport,
  ImportRowIssue,
} from '../../core/models/catalog.model';
import { readImportRowIssues } from '../../core/models/catalog.model';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../shared/components/data-table/data-table.component';
import type {
  DataTableColumn,
  PageChange,
} from '../../shared/components/data-table/data-table.model';
import { StatusChipComponent } from '../../shared/components/status-chip/status-chip.component';
import {
  CatalogImportService,
  type CatalogTemplateFormat,
} from './catalog-import.service';

/**
 * Importacion de catalogo (RF-126 a RF-132, AC-061 a AC-066, api-design.md
 * §7.13). Descarga la plantilla en XLSX o CSV, valida el archivo en seco con
 * su informe por fila sin escribir nada, y lo aplica en una transaccion
 * atomica idempotente por SKU. El 422 de aplicacion muestra el informe por
 * fila (fila, campo, motivo) porque nada se aplico.
 */
@Component({
  selector: 'app-catalog-import-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    RouterLink,
    MatButtonModule,
    MatIconModule,
    PageHeaderComponent,
    DataTableComponent,
    StatusChipComponent,
  ],
  templateUrl: './catalog-import-page.component.html',
  styleUrl: './catalog-import-page.component.scss',
})
export class CatalogImportPageComponent {
  private readonly imports = inject(CatalogImportService);

  protected readonly issueColumns: readonly DataTableColumn<ImportRowIssue>[] = [
    { key: 'fila', header: 'Fila', numeric: true, cell: (row) => String(row.fila) },
    { key: 'campo', header: 'Campo', mono: true, cell: (row) => row.campo },
    { key: 'motivo', header: 'Motivo', cell: (row) => row.motivo },
  ];

  protected readonly selectedFile = signal<File | null>(null);
  protected readonly downloading = signal<CatalogTemplateFormat | null>(null);
  protected readonly validating = signal(false);
  protected readonly applying = signal(false);

  protected readonly notice = signal<string | null>(null);
  protected readonly report = signal<CatalogDryRunReport | null>(null);
  protected readonly summary = signal<CatalogApplySummary | null>(null);
  protected readonly applyIssues = signal<readonly ImportRowIssue[]>([]);

  // Paginacion local del informe por fila: el informe ya esta completo en
  // memoria y la tabla solo necesita la pagina visible.
  protected readonly issuePage = signal(1);
  protected readonly issuePageSize = signal(15);

  protected readonly fileLabel = computed(() => {
    const file = this.selectedFile();
    return file === null ? 'Ningun archivo seleccionado' : `${file.name} (${formatBytes(file.size)})`;
  });

  protected readonly invalidIssues = computed<readonly ImportRowIssue[]>(() => {
    const report = this.report();

    if (report === null) {
      return [];
    }

    return report.filas
      .filter((row) => !row.valida)
      .flatMap((row) =>
        row.errores.map((error) => ({ fila: row.fila, campo: error.campo, motivo: error.motivo })),
      );
  });

  protected readonly visibleIssues = computed<readonly ImportRowIssue[]>(() => {
    const source = this.applyIssues().length > 0 ? this.applyIssues() : this.invalidIssues();
    const start = (this.issuePage() - 1) * this.issuePageSize();
    return source.slice(start, start + this.issuePageSize());
  });

  protected readonly visibleIssueTotal = computed(() =>
    this.applyIssues().length > 0 ? this.applyIssues().length : this.invalidIssues().length,
  );

  protected onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.item(0) ?? null;

    this.selectedFile.set(file);
    this.notice.set(null);
    this.report.set(null);
    this.summary.set(null);
    this.applyIssues.set([]);
    this.issuePage.set(1);
  }

  protected downloadTemplate(format: CatalogTemplateFormat): void {
    this.downloading.set(format);
    this.notice.set(null);

    this.imports.template(format).subscribe({
      next: (blob) => {
        this.downloading.set(null);
        saveBlob(blob, format === 'xlsx' ? 'plantilla-catalogo.xlsx' : 'plantilla-catalogo.csv');
      },
      error: (error: unknown) => {
        this.downloading.set(null);
        this.notice.set(
          error instanceof AppError ? error.message : 'No se pudo descargar la plantilla.',
        );
      },
    });
  }

  protected validate(): void {
    const file = this.selectedFile();

    if (file === null) {
      this.notice.set('Seleccione un archivo XLSX o CSV para validar.');
      return;
    }

    this.validating.set(true);
    this.notice.set(null);
    this.report.set(null);
    this.summary.set(null);
    this.applyIssues.set([]);
    this.issuePage.set(1);

    this.imports.validate(file).subscribe({
      next: (report) => {
        this.validating.set(false);
        this.report.set(report);
      },
      error: (error: unknown) => {
        this.validating.set(false);
        this.notice.set(
          error instanceof AppError ? error.message : 'No se pudo validar el archivo.',
        );
      },
    });
  }

  protected apply(): void {
    const file = this.selectedFile();

    if (file === null) {
      this.notice.set('Seleccione un archivo XLSX o CSV para aplicar.');
      return;
    }

    this.applying.set(true);
    this.notice.set(null);
    this.summary.set(null);
    this.applyIssues.set([]);
    this.issuePage.set(1);

    this.imports.apply(file).subscribe({
      next: (summary) => {
        this.applying.set(false);
        this.summary.set(summary);
      },
      error: (error: unknown) => {
        this.applying.set(false);

        if (!(error instanceof AppError)) {
          this.notice.set('No se pudo aplicar el archivo.');
          return;
        }

        // El 422 atomico trae el informe por fila en los detalles crudos con
        // las claves fila/campo/motivo; nada se aplico y se muestra tal cual.
        const issues = readImportRowIssues(error.rawDetails);

        if (issues.length > 0) {
          this.notice.set(error.message);
          this.applyIssues.set(issues);
          return;
        }

        this.notice.set(error.message);
      },
    });
  }

  protected onIssuePage(change: PageChange): void {
    this.issuePage.set(change.page);
    this.issuePageSize.set(change.pageSize);
  }
}

// Descargo el binario con un enlace temporal: el nombre coincide con el que
// sirve el backend para que el archivo guardado sea reconocible.
function saveBlob(blob: Blob, fileName: string): void {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = fileName;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}

// Presento el tamano en unidades legibles sin convertir ningun dato del
// dominio: es solo el peso del archivo elegido, no una cifra del negocio.
function formatBytes(size: number): string {
  if (size < 1024) {
    return `${size} B`;
  }

  const kilobytes = size / 1024;

  if (kilobytes < 1024) {
    return `${kilobytes.toFixed(1)} KB`;
  }

  return `${(kilobytes / 1024).toFixed(1)} MB`;
}
