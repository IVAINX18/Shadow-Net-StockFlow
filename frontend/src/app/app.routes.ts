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
        path: 'pendiente/productos',
        title: 'Productos | StockFlow',
        data: {
          crumb: 'Productos',
          pageTitle: 'Productos',
          summary: 'Catalogo de productos por SKU.',
          phase: 'Fase 2',
          endpoints: ['GET /api/v1/products', 'POST /api/v1/products'],
        },
        loadComponent: () =>
          import('./shared/components/module-pending/module-pending.component').then((m) => m.ModulePendingComponent),
      },
      {
        path: 'pendiente/categorias',
        title: 'Categorias | StockFlow',
        data: {
          crumb: 'Categorias',
          pageTitle: 'Categorias',
          summary: 'Jerarquia de categorias del catalogo.',
          phase: 'Fase 2',
          endpoints: ['GET /api/v1/categories', 'GET /api/v1/units'],
        },
        loadComponent: () =>
          import('./shared/components/module-pending/module-pending.component').then((m) => m.ModulePendingComponent),
      },
      {
        path: 'pendiente/inventario',
        title: 'Inventario | StockFlow',
        data: {
          crumb: 'Inventario',
          pageTitle: 'Inventario',
          summary: 'Documentos de inventario y existencias.',
          phase: 'Fase 3',
          endpoints: ['GET /api/v1/inventory-documents', 'GET /api/v1/inventory/kardex'],
        },
        canActivate: [roleGuard(['inventory.view'])],
        loadComponent: () =>
          import('./shared/components/module-pending/module-pending.component').then((m) => m.ModulePendingComponent),
      },
      {
        path: 'pendiente/movimientos',
        title: 'Movimientos | StockFlow',
        data: {
          crumb: 'Movimientos',
          pageTitle: 'Movimientos',
          summary: 'Kardex de movimientos por producto y almacen.',
          phase: 'Fase 3',
          endpoints: ['GET /api/v1/inventory/kardex', 'GET /api/v1/inventory-documents'],
        },
        canActivate: [roleGuard(['inventory.view'])],
        loadComponent: () =>
          import('./shared/components/module-pending/module-pending.component').then((m) => m.ModulePendingComponent),
      },
      {
        path: 'pendiente/transferencias',
        title: 'Transferencias | StockFlow',
        data: {
          crumb: 'Transferencias',
          pageTitle: 'Transferencias',
          summary: 'Traslados atomicos entre almacenes.',
          phase: 'Fase 3',
          endpoints: ['POST /api/v1/inventory-documents (type=transferencia)'],
        },
        canActivate: [roleGuard(['inventory.view'])],
        loadComponent: () =>
          import('./shared/components/module-pending/module-pending.component').then((m) => m.ModulePendingComponent),
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
