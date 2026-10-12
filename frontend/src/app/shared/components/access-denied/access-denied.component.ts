import { ChangeDetectionStrategy, Component } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';

/**
 * Pantalla de acceso denegado. La guardia de navegacion redirige aqui cuando el
 * usuario no tiene el permiso, pero recuerda que la denegacion definitiva la da
 * el backend con 403 (security-design.md §1).
 */
@Component({
  selector: 'app-access-denied',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatButtonModule, MatIconModule, RouterLink],
  template: `
    <div class="denied" role="alert">
      <mat-icon class="denied__icon" aria-hidden="true">lock</mat-icon>
      <h1 class="denied__title">Acceso denegado</h1>
      <p class="denied__message">
        Su cuenta no tiene el permiso necesario para ver esta seccion. Si cree que
        es un error, solicite al administrador que revise sus roles.
      </p>
      <a mat-flat-button routerLink="/dashboard">
        <mat-icon aria-hidden="true">dashboard</mat-icon>
        Volver al panel
      </a>
    </div>
  `,
  styles: `
    .denied {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: var(--app-space-3);
      padding: var(--app-space-8) var(--app-space-4);
    }

    .denied__icon {
      font-size: 48px;
      width: 48px;
      height: 48px;
      color: var(--mat-sys-on-surface-variant);
    }

    .denied__title {
      margin: 0;
      font: var(--mat-sys-headline-small);
    }

    .denied__message {
      margin: 0;
      max-inline-size: 52ch;
      font: var(--mat-sys-body-medium);
      color: var(--mat-sys-on-surface-variant);
    }
  `,
})
export class AccessDeniedComponent {}
