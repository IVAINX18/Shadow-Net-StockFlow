import { Injectable, computed, signal } from '@angular/core';

/** Modo de tema resuelto por la aplicacion. */
export type ThemeMode = 'light' | 'dark';

const STORAGE_KEY = 'stockflow.theme';

/**
 * Tema claro y oscuro del sistema de diseno (design-system.md §5). El tema
 * inicial sigue la preferencia del sistema operativo y existe un conmutador
 * manual que persiste la eleccion. La raiz se marca escribiendo `color-scheme`,
 * porque los tokens M3 se emiten con light-dark().
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly mode = signal<ThemeMode>(resolveInitialMode());

  /** Modo activo. */
  public readonly theme = this.mode.asReadonly();
  /** Indica si el modo activo es oscuro, para el boton del conmutador. */
  public readonly isDark = computed(() => this.mode() === 'dark');

  public constructor() {
    // Aplico el tema en el constructor, antes del primer render, para evitar el
    // destello del tema equivocado que ocurriria si esperase a un effect.
    this.apply(this.mode());
  }

  /** Invierte el tema activo y lo persiste. */
  public toggle(): void {
    this.set(this.mode() === 'dark' ? 'light' : 'dark');
  }

  /** Fija un tema concreto y lo persiste. */
  public set(next: ThemeMode): void {
    this.mode.set(next);
    this.apply(next);
  }

  private apply(next: ThemeMode): void {
    const root = document.documentElement;
    // Escribo `color-scheme` en la raiz y nada mas: como los tokens de Material
    // 3 usan light-dark(), este valor resuelve los dos esquemas y ademas
    // acompana a los controles nativos (scroll, campos). No hay clase de tema
    // oscuro que mantener (design-system.md §5).
    root.style.colorScheme = next;
    writeStored(next);
  }
}

function resolveInitialMode(): ThemeMode {
  const stored = readStored();

  if (stored !== null) {
    return stored;
  }

  // Sin eleccion previa, sigo la preferencia del sistema operativo.
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function readStored(): ThemeMode | null {
  const value = localStorage.getItem(STORAGE_KEY);
  return value === 'light' || value === 'dark' ? value : null;
}

function writeStored(mode: ThemeMode): void {
  localStorage.setItem(STORAGE_KEY, mode);
}
