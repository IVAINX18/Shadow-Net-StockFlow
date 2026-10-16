import { TestBed } from '@angular/core/testing';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { API_BASE_URL } from '../../core/services/api-base-url.token';
import { InventoryDocumentsService } from './inventory-documents.service';
import { KardexService } from './kardex.service';
import { StockService } from './stock.service';
import type {
  InventoryDocument,
  KardexMovement,
  StockRow,
} from '../../core/models/inventory.model';

// Verifico que los servicios de inventario pidan los endpoints del contrato
// con sus parametros reales y conserven los decimales como cadena, con la
// capa HTTP simulada.
describe('Inventory services', () => {
  let http: HttpTestingController;
  let documents: InventoryDocumentsService;
  let kardex: KardexService;
  let stock: StockService;

  const pageResponse = <T>(data: readonly T[]) => ({
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, per_page: 15, total: data.length, last_page: 1 },
  });

  const documentRow: InventoryDocument = {
    uuid: 'cf3ca03b-dd4d-4e6d-bcef-d7342b030c07',
    doc_number: 'ENT-2026-000001',
    type: 'entrada',
    status: 'confirmado',
    origin_warehouse_id: 8,
    destination_warehouse_id: null,
    reference: 'Carga de demostracion',
    notes: null,
    reason_code: null,
    created_by: 1,
    confirmed_by: 1,
    confirmed_at: '2026-10-16T16:48:28+00:00',
    created_at: '2026-10-16T16:48:28+00:00',
    updated_at: '2026-10-16T16:48:28+00:00',
    lines: [
      {
        id: 1,
        product_id: 8,
        quantity: '10.000',
        direction: null,
        unit_cost: '10.000000',
        line_total: '100.00',
      },
    ],
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: 'http://localhost:8000' },
      ],
    });

    http = TestBed.inject(HttpTestingController);
    documents = TestBed.inject(InventoryDocumentsService);
    kardex = TestBed.inject(KardexService);
    stock = TestBed.inject(StockService);
  });

  afterEach(() => {
    http.verify();
  });

  it('lista documentos con filtros, orden y paginacion del servidor', () => {
    documents
      .list({ type: 'entrada', status: 'confirmado', sort: '-created_at', page: 1, per_page: 15 })
      .subscribe((response) => {
        expect(response.data).toEqual([documentRow]);
        expect(response.meta.total).toBe(1);
      });

    const request = http.expectOne(
      (req) =>
        req.method === 'GET' &&
        req.url === 'http://localhost:8000/api/v1/inventory-documents' &&
        req.params.get('type') === 'entrada' &&
        req.params.get('status') === 'confirmado' &&
        req.params.get('sort') === '-created_at',
    );
    request.flush(pageResponse([documentRow]));
  });

  it('lista transferencias con el filtro de tipo fijo', () => {
    documents.list({ type: 'transferencia', sort: '-created_at' }).subscribe((response) => {
      expect(response.data).toEqual([]);
    });

    const request = http.expectOne(
      (req) =>
        req.method === 'GET' &&
        req.url === 'http://localhost:8000/api/v1/inventory-documents' &&
        req.params.get('type') === 'transferencia',
    );
    request.flush(pageResponse([]));
  });

  it('desempaqueta el detalle con sus lineas', () => {
    documents.get(documentRow.uuid).subscribe((result) => {
      expect(result.doc_number).toBe('ENT-2026-000001');
      expect(result.lines[0]?.quantity).toBe('10.000');
      expect(typeof result.lines[0]?.quantity).toBe('string');
    });

    http
      .expectOne(`http://localhost:8000/api/v1/inventory-documents/${documentRow.uuid}`)
      .flush({ data: documentRow });
  });

  it('confirma con cabecera de idempotencia', () => {
    documents.confirm(documentRow.uuid).subscribe((result) => {
      expect(result.status).toBe('confirmado');
    });

    const request = http.expectOne(
      (req) =>
        req.method === 'POST' &&
        req.url === `http://localhost:8000/api/v1/inventory-documents/${documentRow.uuid}/confirm`,
    );
    expect(request.request.headers.get('Idempotency-Key')).toBeTruthy();
    request.flush({ data: documentRow });
  });

  it('descarta y revierte contra sus endpoints con el motivo en el cuerpo', () => {
    documents.cancel('uuid-borrador').subscribe((result) => {
      expect(result.status).toBe('descartado');
    });

    http
      .expectOne('http://localhost:8000/api/v1/inventory-documents/uuid-borrador/cancel')
      .flush({ data: { ...documentRow, status: 'descartado' } });

    documents.reverse(documentRow.uuid, { reason: 'Error de digitacion' }).subscribe((result) => {
      expect(result.status).toBe('revertido');
    });

    const reversed = http.expectOne(
      (req) =>
        req.method === 'POST' &&
        req.url === `http://localhost:8000/api/v1/inventory-documents/${documentRow.uuid}/reverse`,
    );
    expect(reversed.request.body).toEqual({ reason: 'Error de digitacion' });
    reversed.flush({ data: { ...documentRow, status: 'revertido' } });
  });

  it('consulta el kardex con producto y almacen obligatorios y conserva los saldos', () => {
    const rows: readonly KardexMovement[] = [
      {
        id: 1,
        document_id: 1,
        product_id: 8,
        warehouse_id: 8,
        direction: 'entrada',
        quantity_delta: '10.000',
        unit_cost: '10.000000',
        balance_after: '10.000',
        created_at: '2026-10-16T16:48:28+00:00',
      },
    ];

    kardex
      .list({ product_id: 8, warehouse_id: 8, page: 1, per_page: 15 })
      .subscribe((response) => {
        expect(response.data).toEqual(rows);
        expect(response.balances.initial_balance).toBe('0.000');
        expect(response.balances.final_balance).toBe('10.000');
        expect(typeof response.data[0]?.balance_after).toBe('string');
      });

    const request = http.expectOne(
      (req) =>
        req.method === 'GET' &&
        req.url === 'http://localhost:8000/api/v1/inventory/kardex' &&
        req.params.get('product_id') === '8' &&
        req.params.get('warehouse_id') === '8',
    );
    request.flush({ ...pageResponse(rows), balances: { initial_balance: '0.000', final_balance: '10.000' } });
  });

  it('lista existencias y serializa below_min como cadena explicita', () => {
    const rows: readonly StockRow[] = [
      {
        product_id: 8,
        sku: 'DEMO-001',
        product_name: 'Producto de demostracion',
        category_id: 9,
        warehouse_id: 8,
        warehouse_code: 'ALM-DEMO',
        quantity: '10.000',
        average_cost: '10.000000',
        stock_value: '100.00',
        min_stock: '5.000',
        max_stock: '100.000',
        reorder_point: null,
        below_min: false,
        last_movement_at: '2026-10-16 16:48:28',
      },
    ];
    const body = {
      ...pageResponse(rows),
      totals: { total_quantity: '10.000', total_value: '100.00' },
    };

    stock.list({ below_min: true }).subscribe((response) => {
      expect(response.data[0]?.quantity).toBe('10.000');
      expect(response.totals.total_value).toBe('100.00');
    });

    const onlyLow = http.expectOne(
      (req) =>
        req.method === 'GET' &&
        req.url === 'http://localhost:8000/api/v1/reports/stock' &&
        req.params.get('below_min') === 'true',
    );
    onlyLow.flush(body);

    // El `false` tambien viaja explicito: un casteo a ciegas lo omitiria y el
    // filtro dejaria de significar "sobre el minimo".
    stock.list({ below_min: false }).subscribe((response) => {
      expect(response.data).toEqual(rows);
    });

    const aboveMin = http.expectOne(
      (req) =>
        req.method === 'GET' &&
        req.url === 'http://localhost:8000/api/v1/reports/stock' &&
        req.params.get('below_min') === 'false',
    );
    aboveMin.flush(body);
  });
});
