import { provideZonelessChangeDetection } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { App } from './app';
import { NAV_GROUPS } from './features/shell/navigation-items';

// Pruebo que la raiz arranca con el enrutador. El archivo anterior comprobaba el
// titulo del andamiaje del CLI, que ya no existe: ahora la raiz solo hospeda la
// salida del enrutador y el armazon es una ruta hija (frontend-architecture.md §3).
describe('App', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [provideZonelessChangeDetection(), provideRouter([])],
    }).compileComponents();
  });

  it('crea el componente raiz', () => {
    const fixture = TestBed.createComponent(App);
    expect(fixture.componentInstance).toBeTruthy();
  });
});

// La navegacion es la lista de secciones del producto. Si una ruta se repite o un
// grupo queda vacio, el menu muestra opciones muertas o enlaza a la pantalla
// equivocada. Es barato comprobarlo aqui y evita que el armazon se degrade sin
// que nadie lo note.
describe('NAV_GROUPS', () => {
  it('declara rutas unicas y absolutas', () => {
    const rutas = NAV_GROUPS.flatMap((grupo) => grupo.items.map((item) => item.route));

    expect(new Set(rutas).size).toBe(rutas.length);
    rutas.forEach((ruta) => expect(ruta.startsWith('/')).toBe(true));
  });

  it('no deja grupos vacios y da etiqueta e icono a cada seccion', () => {
    NAV_GROUPS.forEach((grupo) => {
      expect(grupo.items.length).toBeGreaterThan(0);
      grupo.items.forEach((item) => {
        expect(item.label.trim().length).toBeGreaterThan(0);
        expect(item.icon.trim().length).toBeGreaterThan(0);
      });
    });
  });
});
