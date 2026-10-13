import { TestBed } from '@angular/core/testing';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { API_BASE_URL } from '../../core/services/api-base-url.token';
import { CategoriesService } from './categories.service';
import { ProductsService } from './products.service';
import { UnitsService } from './units.service';
import type { Category, Product, UnitOfMeasure } from '../../core/models/catalog.model';

// Verifico que los servicios del catalogo pidan los endpoints del contrato con
// sus parametros y desempaqueten el sobre data, con la capa HTTP simulada.
describe('Catalog services', () => {
  let http: HttpTestingController;
  let categories: CategoriesService;
  let units: UnitsService;
  let products: ProductsService;

  const pageResponse = <T>(data: readonly T[]) => ({
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, per_page: 15, total: data.length, last_page: 1 },
  });

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: 'http://localhost:8000' },
      ],
    });

    http = TestBed.inject(HttpTestingController);
    categories = TestBed.inject(CategoriesService);
    units = TestBed.inject(UnitsService);
    products = TestBed.inject(ProductsService);
  });

  afterEach(() => {
    http.verify();
  });

  it('lista categorias con filtros, busqueda y orden', () => {
    const rows: readonly Category[] = [
      { id: 1, name: 'Granos', slug: 'granos', parent_id: null, is_active: true },
    ];

    categories
      .list({ page: 1, per_page: 15, sort: 'name', is_active: true, q: 'gran' })
      .subscribe((response) => {
        expect(response.data).toEqual(rows);
        expect(response.meta.total).toBe(1);
      });

    const request = http.expectOne(
      (req) =>
        req.method === 'GET' &&
        req.url === 'http://localhost:8000/api/v1/categories' &&
        req.params.get('sort') === 'name' &&
        req.params.get('q') === 'gran' &&
        req.params.get('is_active') === '1',
    );
    request.flush(pageResponse(rows));
  });

  it('desempaqueta el detalle y el alta de categoria', () => {
    const category: Category = { id: 2, name: 'Harinas', slug: 'harinas', parent_id: 1, is_active: true };

    categories.get(2).subscribe((result) => expect(result).toEqual(category));
    http.expectOne('http://localhost:8000/api/v1/categories/2').flush({ data: category });

    categories.create({ name: 'Harinas', parent_id: 1 }).subscribe((result) => {
      expect(result).toEqual(category);
    });

    const created = http.expectOne(
      (req) => req.method === 'POST' && req.url === 'http://localhost:8000/api/v1/categories',
    );
    expect(created.request.body).toEqual({ name: 'Harinas', parent_id: 1 });
    created.flush({ data: category });
  });

  it('desactiva la categoria con POST al endpoint dedicado', () => {
    const category: Category = { id: 3, name: 'Viejas', slug: 'viejas', parent_id: null, is_active: false };

    categories.deactivate(3).subscribe((result) => expect(result).toEqual(category));

    http
      .expectOne('http://localhost:8000/api/v1/categories/3/deactivate')
      .flush({ data: category });
  });

  it('lista y crea unidades sin ofrecer edicion', () => {
    const rows: readonly UnitOfMeasure[] = [
      { id: 1, code: 'UND', name: 'Unidad', allows_decimals: false },
    ];

    units.list({ sort: 'code' }).subscribe((response) => expect(response.data).toEqual(rows));
    http.expectOne('http://localhost:8000/api/v1/units?sort=code').flush(pageResponse(rows));

    units.create({ code: 'KG', name: 'Kilogramo', allows_decimals: true }).subscribe((result) => {
      expect(result.code).toBe('KG');
    });

    const created = http.expectOne(
      (req) => req.method === 'POST' && req.url === 'http://localhost:8000/api/v1/units',
    );
    expect(created.request.body).toEqual({ code: 'KG', name: 'Kilogramo', allows_decimals: true });
    created.flush({ data: { id: 2, code: 'KG', name: 'Kilogramo', allows_decimals: true } });

    // El servicio no expone update ni delete porque el contrato no los define.
    expect('update' in units).toBe(false);
    expect('delete' in units).toBe(false);
  });

  it('lista productos con busqueda y conserva los montos como cadena', () => {
    const rows: readonly Product[] = [
      {
        id: 1,
        sku: 'ARROZ-001',
        name: 'Arroz',
        description: null,
        barcode: null,
        category_id: 1,
        unit_of_measure_id: 1,
        is_active: true,
        cost_price: '12.50',
        sale_price: '15.00',
        costing_method: 'promedio_ponderado',
      },
    ];

    products.list({ q: 'arroz', sort: 'sku' }).subscribe((response) => {
      expect(response.data[0]?.cost_price).toBe('12.50');
      expect(typeof response.data[0]?.cost_price).toBe('string');
    });

    const request = http.expectOne(
      (req) =>
        req.method === 'GET' &&
        req.url === 'http://localhost:8000/api/v1/products' &&
        req.params.get('q') === 'arroz',
    );
    expect(request.request.params.get('sort')).toBe('sku');
    request.flush(pageResponse(rows));
  });

  it('actualiza la configuracion por almacen con cadenas decimales', () => {
    products
      .updateWarehouseConfig(1, 2, { min_stock: '5.000', max_stock: '50.000' })
      .subscribe((result) => {
        expect(result.min_stock).toBe('5.000');
      });

    const request = http.expectOne('http://localhost:8000/api/v1/products/1/warehouses/2');
    expect(request.request.method).toBe('PUT');
    expect(request.request.body).toEqual({ min_stock: '5.000', max_stock: '50.000' });
    request.flush({
      data: { id: 9, product_id: 1, warehouse_id: 2, min_stock: '5.000', max_stock: '50.000', reorder_point: null },
    });
  });
});
