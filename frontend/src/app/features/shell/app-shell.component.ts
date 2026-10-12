import { BreakpointObserver } from '@angular/cdk/layout';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatDividerModule } from '@angular/material/divider';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatMenuModule } from '@angular/material/menu';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatTooltipModule } from '@angular/material/tooltip';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { AuthService } from '../../core/services/auth.service';
import { ThemeService } from '../../core/services/theme.service';
import { NAV_GROUPS, type NavGroup, type NavItem } from './navigation-items';

/** Miga de pan del armazon. */
interface Crumb {
  readonly label: string;
  readonly url: string;
}

const COMPACT_QUERY = '(max-width: 959px)';

/**
 * Armazon de la aplicacion: navegacion lateral colapsable, barra superior con
 * marca, migas, conmutador de tema y menu de usuario, y el contenedor de rutas.
 * Filtra las opciones con los slugs de permiso reales de /auth/me; no autoriza,
 * solo decide que se muestra (security-design.md §1).
 */
@Component({
  selector: 'app-shell',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatSidenavModule,
    MatToolbarModule,
    MatListModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatTooltipModule,
  ],
  templateUrl: './app-shell.component.html',
  styleUrl: './app-shell.component.scss',
})
export class AppShellComponent {
  protected readonly auth = inject(AuthService);
  protected readonly theme = inject(ThemeService);
  private readonly router = inject(Router);
  private readonly breakpoints = inject(BreakpointObserver);

  // En pantallas reducidas la navegacion pasa a superpuesta; el patron de
  // escritorio no se comprime (design-system.md §13).
  protected readonly isCompact = toSignal(
    this.breakpoints.observe(COMPACT_QUERY).pipe(map((state) => state.matches)),
    { initialValue: false },
  );

  protected readonly opened = signal(true);

  // Derivadas: solo muestro los grupos que conservan al menos una opcion tras
  // filtrar por permisos del usuario.
  protected readonly navGroups = computed<readonly NavGroup[]>(() =>
    NAV_GROUPS.map((group) => ({
      label: group.label,
      items: group.items.filter((item) => this.canSee(item)),
    })).filter((group) => group.items.length > 0),
  );

  protected readonly crumbs = toSignal(
    this.router.events.pipe(
      filter((event): event is NavigationEnd => event instanceof NavigationEnd),
      map(() => this.buildCrumbs()),
      startWith<readonly Crumb[]>(this.buildCrumbs()),
    ),
    { initialValue: [] as readonly Crumb[] },
  );

  protected readonly userInitials = computed(() => {
    const name = this.auth.user()?.name ?? '';
    return name
      .split(' ')
      .filter((part) => part.length > 0)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join('');
  });

  protected toggleSidenav(): void {
    this.opened.update((value) => !value);
  }

  // Cierro la navegacion superpuesta al elegir una opcion, para no dejar el
  // panel tapando el contenido en pantallas reducidas.
  protected onNavigate(): void {
    if (this.isCompact()) {
      this.opened.set(false);
    }
  }

  protected onToggleTheme(): void {
    this.theme.toggle();
  }

  protected onLogout(): void {
    this.auth.logout().subscribe();
  }

  private canSee(item: NavItem): boolean {
    return item.permissions.length === 0 || this.auth.hasAnyPermission(item.permissions);
  }

  // Construyo las migas recorriendo el arbol de rutas activo: cada ruta declara
  // `data.crumb` y su URL se arma con los segmentos acumulados.
  private buildCrumbs(): readonly Crumb[] {
    const crumbs: Crumb[] = [];
    let route = this.router.routerState.root.firstChild;
    let url = '';

    while (route !== null) {
      const segment = route.snapshot.url.map((part) => part.path).join('/');
      url = segment.length > 0 ? `${url}/${segment}` : url;

      const crumb = route.snapshot.data['crumb'];

      if (typeof crumb === 'string') {
        crumbs.push({ label: crumb, url });
      }

      route = route.firstChild;
    }

    return crumbs;
  }
}
