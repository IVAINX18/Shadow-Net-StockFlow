import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { roleGuard } from './core/guards/role.guard';

// Rutas de carga perezosa (frontend-architecture.md §8). Cada funcionalidad se
// carga con loadComponent/loadChildren; el armazon es la ruta padre protegida.
// Los modulos sin API apuntan a una pantalla honesta de "modulo pendiente" que
// nombra la fase y los endpoints que faltan: nunca se simulan datos.
export const routes: Routes = [
  {
    path: 'login',
    title: 'Iniciar sesion | StockFlow',
    loadComponent: () =>
      import('./features/auth/login-page.component').then((m) => m.LoginPageComponent),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/shell/app-shell.component').then((m) => m.AppShellComponent),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      {
        path: 'dashboard',
        title: 'Dashboard | StockFlow',
        data: { crumb: 'Dashboard' },
        loadComponent: () =>
          import('./features/dashboard/dashboard-page.component').then((m) => m.DashboardPageComponent),
      },
      {
        path: 'almacenes',
        title: 'Almacenes | StockFlow',
        data: { crumb: 'Almacenes' },
        canActivate: [roleGuard(['warehouses.view'])],
        loadComponent: () =>
          import('./features/warehouses/warehouses-page.component').then((m) => m.WarehousesPageComponent),
      },
      {
        path: 'usuarios',
        title: 'Usuarios y roles | StockFlow',
        data: { crumb: 'Usuarios y roles' },
        canActivate: [roleGuard(['users.view', 'roles.view'])],
        loadComponent: () =>
          import('./features/users/users-roles-page.component').then((m) => m.UsersRolesPageComponent),
      },
      {
        path: 'guia-estilo',
        title: 'Guia de estilo | StockFlow',
        data: { crumb: 'Guia de estilo' },
        loadComponent: () =>
          import('./features/style-guide/style-guide-page.component').then((m) => m.StyleGuidePageComponent),
      },
      {
        path: 'sin-acceso',
        title: 'Acceso denegado | StockFlow',
        data: { crumb: 'Acceso denegado' },
        loadComponent: () =>
          import('./shared/components/access-denied/access-denied.component').then((m) => m.AccessDeniedComponent),
      },
      {
        path: 'productos',
        title: 'Productos | StockFlow',
        data: { crumb: 'Productos' },
        loadComponent: () =>
          import('./features/catalog/products-page.component').then((m) => m.ProductsPageComponent),
      },
      {
        path: 'productos/:id',
        title: 'Ficha de producto | StockFlow',
        data: { crumb: 'Ficha de producto' },
        loadComponent: () =>
          import('./features/catalog/product-detail-page.component').then(
            (m) => m.ProductDetailPageComponent,
          ),
      },
      {
        path: 'categorias',
        title: 'Categorias y unidades | StockFlow',
        data: { crumb: 'Categorias' },
        loadComponent: () =>
          import('./features/catalog/categories-page.component').then((m) => m.CategoriesPageComponent),
      },
      {
        path: 'catalogo/importacion',
        title: 'Importacion de catalogo | StockFlow',
        data: { crumb: 'Importacion de catalogo' },
        canActivate: [roleGuard(['catalog.create'])],
        loadComponent: () =>
          import('./features/catalog/catalog-import-page.component').then(
            (m) => m.CatalogImportPageComponent,
          ),
      },
      {
        path: 'inventario',
        title: 'Inventario | StockFlow',
        data: { crumb: 'Inventario' },
        canActivate: [roleGuard(['inventory.view'])],
        loadComponent: () =>
          import('./features/inventory/documents-page.component').then((m) => m.DocumentsPageComponent),
      },
      {
        path: 'inventario/:uuid',
        title: 'Documento de inventario | StockFlow',
        data: { crumb: 'Documento' },
        canActivate: [roleGuard(['inventory.view'])],
        loadComponent: () =>
          import('./features/inventory/document-detail-page.component').then(
            (m) => m.DocumentDetailPageComponent,
          ),
      },
      {
        path: 'existencias',
        title: 'Existencias | StockFlow',
        data: { crumb: 'Existencias' },
        canActivate: [roleGuard(['reports.view'])],
        loadComponent: () =>
          import('./features/inventory/stock-page.component').then((m) => m.StockPageComponent),
      },
      {
        path: 'movimientos',
        title: 'Movimientos | StockFlow',
        data: { crumb: 'Movimientos' },
        canActivate: [roleGuard(['inventory.view'])],
        loadComponent: () =>
          import('./features/inventory/kardex-page.component').then((m) => m.KardexPageComponent),
      },
      {
        path: 'transferencias',
        title: 'Transferencias | StockFlow',
        data: {
          crumb: 'Transferencias',
          fixedType: 'transferencia',
          pageTitle: 'Transferencias',
          pageSubtitle: 'Documentos de traslado atomico entre almacenes (solo lectura).',
        },
        canActivate: [roleGuard(['inventory.view'])],
        loadComponent: () =>
          import('./features/inventory/documents-page.component').then((m) => m.DocumentsPageComponent),
      },
      {
        path: 'pendiente/reportes',
        title: 'Reportes | StockFlow',
        data: {
          crumb: 'Reportes',
          pageTitle: 'Reportes',
          summary: 'Existencias, movimientos y valorizacion exportables.',
          phase: 'Fase 4',
          endpoints: [
            'GET /api/v1/reports/stock',
            'GET /api/v1/reports/movements',
            'GET /api/v1/reports/valuation',
          ],
        },
        canActivate: [roleGuard(['reports.view'])],
        loadComponent: () =>
          import('./shared/components/module-pending/module-pending.component').then((m) => m.ModulePendingComponent),
      },
      {
        path: 'pendiente/auditoria',
        title: 'Auditoria | StockFlow',
        data: {
          crumb: 'Auditoria',
          pageTitle: 'Auditoria',
          summary: 'Bitacora de auditoria de solo lectura.',
          phase: 'Fase 5',
          endpoints: ['GET /api/v1/audit-logs'],
        },
        canActivate: [roleGuard(['audit.view'])],
        loadComponent: () =>
          import('./shared/components/module-pending/module-pending.component').then((m) => m.ModulePendingComponent),
      },
      {
        path: 'pendiente/configuracion',
        title: 'Configuracion | StockFlow',
        data: {
          crumb: 'Configuracion',
          pageTitle: 'Configuracion',
          summary: 'Parametros generales del sistema.',
          phase: 'Fase 5',
          endpoints: [],
        },
        loadComponent: () =>
          import('./shared/components/module-pending/module-pending.component').then((m) => m.ModulePendingComponent),
      },
    ],
  },
  {
    path: '**',
    redirectTo: '',
  },
];
