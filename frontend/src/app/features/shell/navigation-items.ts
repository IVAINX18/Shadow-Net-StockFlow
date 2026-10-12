// Modelo de la navegacion del armazon. Cada seccion declara la ruta, el icono
// de Material Symbols y los permisos (slugs RBAC) que la hacen visible. La
// visibilidad se filtra con los slugs reales que devuelve /auth/me; no es
// autorizacion, solo navegacion.

/** Una opcion del menu lateral. */
export interface NavItem {
  readonly label: string;
  readonly icon: string;
  readonly route: string;
  /** Slugs de permiso; basta con tener uno. Vacio significa "cualquier sesion". */
  readonly permissions: readonly string[];
}

/** Un grupo de opciones relacionadas. */
export interface NavGroup {
  readonly label: string;
  readonly items: readonly NavItem[];
}

// Las once secciones del producto. Solo Dashboard, Almacenes y Usuarios y roles
// tienen API real hoy; el resto apunta a la pantalla de modulo pendiente, que
// nombra la fase y los endpoints que faltan, sin simular datos.
export const NAV_GROUPS: readonly NavGroup[] = [
  {
    label: 'General',
    items: [
      { label: 'Dashboard', icon: 'dashboard', route: '/dashboard', permissions: [] },
    ],
  },
  {
    label: 'Catalogo',
    items: [
      { label: 'Productos', icon: 'inventory_2', route: '/pendiente/productos', permissions: [] },
      { label: 'Categorias', icon: 'category', route: '/pendiente/categorias', permissions: [] },
      { label: 'Almacenes', icon: 'warehouse', route: '/almacenes', permissions: ['warehouses.view'] },
    ],
  },
  {
    label: 'Operacion',
    items: [
      { label: 'Inventario', icon: 'assignment', route: '/pendiente/inventario', permissions: ['inventory.view'] },
      { label: 'Movimientos', icon: 'swap_vert', route: '/pendiente/movimientos', permissions: ['inventory.view'] },
      { label: 'Transferencias', icon: 'sync_alt', route: '/pendiente/transferencias', permissions: ['inventory.view'] },
    ],
  },
  {
    label: 'Analisis',
    items: [
      { label: 'Reportes', icon: 'bar_chart', route: '/pendiente/reportes', permissions: ['reports.view'] },
      { label: 'Auditoria', icon: 'history', route: '/pendiente/auditoria', permissions: ['audit.view'] },
    ],
  },
  {
    label: 'Administracion',
    items: [
      { label: 'Usuarios y roles', icon: 'manage_accounts', route: '/usuarios', permissions: ['users.view', 'roles.view'] },
      { label: 'Configuracion', icon: 'settings', route: '/pendiente/configuracion', permissions: [] },
    ],
  },
];
