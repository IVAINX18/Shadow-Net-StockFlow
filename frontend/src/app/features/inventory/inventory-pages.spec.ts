import { provideZonelessChangeDetection } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { ActivatedRoute, Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { AuthService } from '../../core/services/auth.service';
import { AppError } from '../../core/models/app-error.model';
import type { PaginatedResponse } from '../../core/models/api.model';
import type { InventoryDocument } from '../../core/models/inventory.model';
import { DocumentsPageComponent } from './documents-page.component';
import { DocumentDetailPageComponent } from './document-detail-page.component';
import { KardexPageComponent } from './kardex-page.component';
import { StockPageComponent } from './stock-page.component';
import { InventoryDocumentsService } from './inventory-documents.service';
import { KardexService } from './kardex.service';
import { StockService } from './stock.service';

// Verifico los cuatro estados de las pantallas de inventario con los
// servicios sustituidos por dobles: la API real se verifica en navegador,
// aqui compruebo que cada estado compone el mensaje y los datos correctos.
describe('Inventory pages', () => {
  const pageResponse = <T>(data: readonly T[]): PaginatedResponse<T> => ({
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

  const baseProviders = (routeData: Record<string, unknown>) => [
    provideZonelessChangeDetection(),
    { provide: ActivatedRoute, useValue: { snapshot: { data: routeData, paramMap: { get: () => null } } } },
    { provide: Router, useValue: { navigate: () => Promise.resolve(true) } },
    { provide: MatDialog, useValue: { open: () => ({ afterClosed: () => of(null) }) } },
    { provide: AuthService, useValue: { hasPermission: () => true } },
  ];

  describe('DocumentsPageComponent', () => {
    it('muestra los documentos poblados con su estado', async () => {
      const list = () => of(pageResponse([documentRow]));

      await TestBed.configureTestingModule({
        imports: [DocumentsPageComponent],
        providers: [
          ...baseProviders({}),
          { provide: InventoryDocumentsService, useValue: { list, get: () => of(documentRow) } },
        ],
      }).compileComponents();

      const fixture = TestBed.createComponent(DocumentsPageComponent);
      fixture.detectChanges();
      const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

      expect(text).toContain('ENT-2026-000001');
      expect(text).toContain('Confirmado');
    });

    it('muestra el error del sobre en lugar de una tabla vacia', async () => {
      const failure = new AppError({
        kind: 'authorization',
        code: 'SIN_PERMISO',
        message: 'No tiene permiso para ver el inventario.',
        status: 403,
      });

      await TestBed.configureTestingModule({
        imports: [DocumentsPageComponent],
        providers: [
          ...baseProviders({}),
          {
            provide: InventoryDocumentsService,
            useValue: { list: () => throwError(() => failure) },
          },
        ],
      }).compileComponents();

      const fixture = TestBed.createComponent(DocumentsPageComponent);
      fixture.detectChanges();
      const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

      expect(text).toContain('No tiene permiso para ver el inventario.');
      expect(text).not.toContain('ENT-2026-000001');
    });

    it('reutiliza la pagina para transferencias con el tipo fijo y sin filtro de tipo', async () => {
      let requestedType: unknown = null;
      const list = (params: Record<string, unknown>) => {
        requestedType = params['type'];
        return of(pageResponse([]));
      };

      await TestBed.configureTestingModule({
        imports: [DocumentsPageComponent],
        providers: [
          ...baseProviders({
            fixedType: 'transferencia',
            pageTitle: 'Transferencias',
            pageSubtitle: 'Traslados entre almacenes.',
          }),
          { provide: InventoryDocumentsService, useValue: { list, get: () => of(documentRow) } },
        ],
      }).compileComponents();

      const fixture = TestBed.createComponent(DocumentsPageComponent);
      fixture.detectChanges();
      const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

      expect(requestedType).toBe('transferencia');
      expect(text).toContain('Transferencias');
      expect(text).not.toContain('Disponible en Fase 3');
    });
  });

  describe('DocumentDetailPageComponent', () => {
    const detailProviders = () => [
      provideZonelessChangeDetection(),
      {
        provide: ActivatedRoute,
        useValue: { snapshot: { data: {}, paramMap: { get: () => documentRow.uuid } } },
      },
      { provide: Router, useValue: { navigate: () => Promise.resolve(true) } },
      { provide: MatDialog, useValue: { open: () => ({ afterClosed: () => of(null) }) } },
      { provide: AuthService, useValue: { hasPermission: () => true } },
    ];

    it('muestra las lineas con los decimales tal cual', async () => {
      await TestBed.configureTestingModule({
        imports: [DocumentDetailPageComponent],
        providers: [
          ...detailProviders(),
          { provide: InventoryDocumentsService, useValue: { get: () => of(documentRow) } },
        ],
      }).compileComponents();

      const fixture = TestBed.createComponent(DocumentDetailPageComponent);
      fixture.detectChanges();
      const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

      expect(text).toContain('ENT-2026-000001');
      expect(text).toContain('10.000');
      expect(text).toContain('10.000000');
    });

    it('muestra el error de negocio sin traza', async () => {
      const failure = new AppError({
        kind: 'business',
        code: 'NO_ENCONTRADO',
        message: 'El documento solicitado no existe.',
        status: 404,
      });

      await TestBed.configureTestingModule({
        imports: [DocumentDetailPageComponent],
        providers: [
          ...detailProviders(),
          {
            provide: InventoryDocumentsService,
            useValue: { get: () => throwError(() => failure) },
          },
        ],
      }).compileComponents();

      const fixture = TestBed.createComponent(DocumentDetailPageComponent);
      fixture.detectChanges();
      const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

      expect(text).toContain('El documento solicitado no existe.');
    });

    it('muestra el resumen de la reversion sin inventar un motivo ausente', async () => {
      const reverted: InventoryDocument = {
        ...documentRow,
        status: 'revertido',
        reversal: {
          uuid: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
          doc_number: 'REV-2026-000001',
          type: 'entrada',
          status: 'confirmado',
        },
      };

      await TestBed.configureTestingModule({
        imports: [DocumentDetailPageComponent],
        providers: [
          ...detailProviders(),
          { provide: InventoryDocumentsService, useValue: { get: () => of(reverted) } },
        ],
      }).compileComponents();

      const fixture = TestBed.createComponent(DocumentDetailPageComponent);
      fixture.detectChanges();
      const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

      expect(text).toContain('REV-2026-000001');
      expect(text).not.toContain('undefined');
    });

    it('muestra el 409 de negocio de una accion como aviso, no como traza', async () => {
      const draft: InventoryDocument = { ...documentRow, status: 'borrador' };
      const failure = new AppError({
        kind: 'business',
        code: 'STOCK_INSUFICIENTE',
        message: 'No hay existencias suficientes para completar la operación.',
        status: 409,
      });

      await TestBed.configureTestingModule({
        imports: [DocumentDetailPageComponent],
        providers: [
          provideZonelessChangeDetection(),
          {
            provide: ActivatedRoute,
            useValue: { snapshot: { data: {}, paramMap: { get: () => draft.uuid } } },
          },
          { provide: Router, useValue: { navigate: () => Promise.resolve(true) } },
          { provide: MatDialog, useValue: { open: () => ({ afterClosed: () => of(true) }) } },
          { provide: AuthService, useValue: { hasPermission: () => true } },
          {
            provide: InventoryDocumentsService,
            useValue: { get: () => of(draft), confirm: () => throwError(() => failure) },
          },
        ],
      })
        // MatDialogModule vuelve a proveer MatDialog en el componente y taparia
        // el doble de la prueba; lo quito para que el mock del dialogo se use.
        .overrideComponent(DocumentDetailPageComponent, { remove: { imports: [MatDialogModule] } });
      await TestBed.compileComponents();

      const fixture = TestBed.createComponent(DocumentDetailPageComponent);
      fixture.detectChanges();
      const element = fixture.nativeElement as HTMLElement;
      const confirmButton = [...element.querySelectorAll('button')].find((button) =>
        (button.textContent ?? '').includes('Confirmar'),
      );

      if (!(confirmButton instanceof HTMLButtonElement)) {
        throw new Error('Falta el boton Confirmar');
      }

      confirmButton.click();
      fixture.detectChanges();
      const text = element.textContent ?? '';

      expect(text).toContain('No hay existencias suficientes para completar la operación.');
      expect(text).not.toContain('STOCK_INSUFICIENTE');
    });
  });

  describe('KardexPageComponent', () => {
    it('no consulta hasta tener producto y almacen, y luego muestra saldos y filas', async () => {
      let calls = 0;
      const list = () => {
        calls += 1;
        return of({
          ...pageResponse([
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
          ]),
          balances: { initial_balance: '0.000', final_balance: '10.000' },
        });
      };

      await TestBed.configureTestingModule({
        imports: [KardexPageComponent],
        providers: [provideZonelessChangeDetection(), { provide: KardexService, useValue: { list } }],
      }).compileComponents();

      const fixture = TestBed.createComponent(KardexPageComponent);
      fixture.detectChanges();

      expect(calls).toBe(0);

      const element = fixture.nativeElement as HTMLElement;
      const inputs = element.querySelectorAll('input[formControlName]');
      const setValue = (name: string, value: string) => {
        const input = [...inputs].find((node) => node.getAttribute('formControlName') === name);
        if (input === null || !(input instanceof HTMLInputElement)) {
          throw new Error(`Falta el campo ${name}`);
        }
        input.value = value;
        input.dispatchEvent(new Event('input'));
      };
      setValue('productId', '8');
      setValue('warehouseId', '8');
      fixture.detectChanges();

      const submit = element.querySelector('button[type="submit"]');
      if (!(submit instanceof HTMLButtonElement)) {
        throw new Error('Falta el boton Consultar');
      }
      submit.click();
      fixture.detectChanges();

      expect(calls).toBe(1);
      const text = element.textContent ?? '';
      expect(text).toContain('10.000');
      expect(text).toContain('0.000');
    });
  });

  describe('StockPageComponent', () => {
    it('muestra las cifras tal cual y el error de permiso sin tabla', async () => {
      const rows = [
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

      await TestBed.configureTestingModule({
        imports: [StockPageComponent],
        providers: [
          provideZonelessChangeDetection(),
          {
            provide: StockService,
            useValue: {
              list: () =>
                of({
                  ...pageResponse(rows),
                  totals: { total_quantity: '10.000', total_value: '100.00' },
                }),
            },
          },
        ],
      }).compileComponents();

      const populated = TestBed.createComponent(StockPageComponent);
      populated.detectChanges();
      const populatedText = (populated.nativeElement as HTMLElement).textContent ?? '';
      expect(populatedText).toContain('DEMO-001');
      expect(populatedText).toContain('10.000');
      expect(populatedText).toContain('100.00');

      TestBed.resetTestingModule();
      const failure = new AppError({
        kind: 'authorization',
        code: 'SIN_PERMISO',
        message: 'No tiene permiso para ver los reportes.',
        status: 403,
      });

      await TestBed.configureTestingModule({
        imports: [StockPageComponent],
        providers: [
          provideZonelessChangeDetection(),
          { provide: StockService, useValue: { list: () => throwError(() => failure) } },
        ],
      }).compileComponents();

      const denied = TestBed.createComponent(StockPageComponent);
      denied.detectChanges();
      const deniedText = (denied.nativeElement as HTMLElement).textContent ?? '';
      expect(deniedText).toContain('No tiene permiso para ver los reportes.');
      expect(deniedText).not.toContain('DEMO-001');
    });
  });
});
