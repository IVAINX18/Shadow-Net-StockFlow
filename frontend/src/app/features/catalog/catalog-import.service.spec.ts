import { TestBed } from '@angular/core/testing';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { API_BASE_URL } from '../../core/services/api-base-url.token';
import { CatalogImportService } from './catalog-import.service';
import { readImportRowIssues } from '../../core/models/catalog.model';

// Verifico el flujo de importacion con la capa HTTP simulada: la plantilla se
// pide como binario, el dry-run y la aplicacion suben multipart en el campo
// archivo, y el informe atomico del 422 se lee desde los detalles crudos.
describe('CatalogImportService', () => {
  let http: HttpTestingController;
  let service: CatalogImportService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: 'http://localhost:8000' },
      ],
    });

    http = TestBed.inject(HttpTestingController);
    service = TestBed.inject(CatalogImportService);
  });

  afterEach(() => {
    http.verify();
  });

  it('descarga la plantilla como binario con el formato pedido', () => {
    service.template('xlsx').subscribe((blob) => {
      expect(blob).toBeInstanceOf(Blob);
    });

    const request = http.expectOne(
      'http://localhost:8000/api/v1/imports/catalog/template?format=xlsx',
    );
    expect(request.request.method).toBe('GET');
    expect(request.request.responseType).toBe('blob');
    request.flush(new Blob(['contenido'], { type: 'application/octet-stream' }));
  });

  it('valida en seco con multipart en el campo archivo', () => {
    const report = {
      archivo: 'catalogo.csv',
      total_filas: 2,
      filas_validas: 1,
      filas_invalidas: 1,
      valida: false,
      filas: [
        { fila: 2, sku: 'ARROZ-001', valida: true, errores: [] },
        {
          fila: 3,
          sku: null,
          valida: false,
          errores: [{ campo: 'nombre', motivo: 'El nombre es obligatorio.' }],
        },
      ],
    };

    service.validate(new File(['x'], 'catalogo.csv', { type: 'text/csv' })).subscribe((result) => {
      expect(result.filas_invalidas).toBe(1);
      expect(result.filas[1]?.errores[0]?.campo).toBe('nombre');
    });

    const request = http.expectOne('http://localhost:8000/api/v1/imports/catalog/validate');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toBeInstanceOf(FormData);
    expect((request.request.body as FormData).has('archivo')).toBe(true);
    request.flush({ data: report });
  });

  it('aplica y desempaqueta el resumen con la referencia de auditoria', () => {
    const summary = {
      archivo: 'catalogo.csv',
      filas_creadas: 1,
      filas_actualizadas: 1,
      filas_totales: 2,
      auditoria_id: 7,
    };

    service.apply(new File(['x'], 'catalogo.csv', { type: 'text/csv' })).subscribe((result) => {
      expect(result.filas_creadas).toBe(1);
      expect(result.auditoria_id).toBe(7);
    });

    http
      .expectOne('http://localhost:8000/api/v1/imports/catalog')
      .flush({ data: summary });
  });

  it('lee el informe atomico del 422 desde los detalles crudos', () => {
    const raw = [
      { fila: 3, campo: 'nombre', codigo: 'VALIDACION', motivo: 'El nombre es obligatorio.' },
      { fila: 4, campo: 'unidad', codigo: 'VALIDACION', motivo: 'La unidad no existe.' },
      { field: 'otro', message: 'Ruido con otra forma' },
    ];

    expect(readImportRowIssues(raw)).toEqual([
      { fila: 3, campo: 'nombre', motivo: 'El nombre es obligatorio.' },
      { fila: 4, campo: 'unidad', motivo: 'La unidad no existe.' },
    ]);
    expect(readImportRowIssues([])).toEqual([]);
    expect(readImportRowIssues([null, 'texto', 42])).toEqual([]);
  });
});
