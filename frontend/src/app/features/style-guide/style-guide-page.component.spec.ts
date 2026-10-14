import { provideZonelessChangeDetection, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { StyleGuidePageComponent } from './style-guide-page.component';
import { ThemeService } from '../../core/services/theme.service';

// Prueba de regresion del reparto en bloques: la pagina debe componer las doce
// secciones con sus titulos, textos y muestras, igual que la pagina monolitica.
// Solo compruebo estructura y copy en DOM simulado; el aspecto real en tema
// claro y oscuro se verifica en navegador, como exige design-system.md §16.
describe('StyleGuidePageComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StyleGuidePageComponent],
      providers: [
        provideZonelessChangeDetection(),
        // El conmutador de tema toca document y localStorage; en esta prueba
        // solo necesito que la pagina arranque, asi que lo sustituyo por un
        // doble con la misma forma de Signal.
        { provide: ThemeService, useValue: { isDark: signal(false), toggle: () => undefined } },
      ],
    }).compileComponents();
  });

  // Titulos reales de la guia, en orden de lectura. La pagina conserva las
  // secciones que ya tenia: ni crea ni quita ninguna con el reparto.
  const expectedTitles = [
    'Roles de superficie y acento',
    'Tipografia',
    'Vocabulario de forma',
    'Elevacion y separacion',
    'Ritmo de composicion',
    'Botones y estados de interaccion',
    'Campos de formulario',
    'Chips de estado',
    'Tarjeta de indicador',
    'Tabla de datos',
    'Estados de interfaz',
    'Escala de indices de apilamiento',
  ];

  it('compone todas las secciones con sus titulos', () => {
    const fixture = TestBed.createComponent(StyleGuidePageComponent);
    fixture.detectChanges();
    const element = fixture.nativeElement as HTMLElement;

    const titles = [...element.querySelectorAll('section h2.guide__title')].map((node) =>
      (node.textContent ?? '').trim(),
    );

    expect(titles).toEqual(expectedTitles);
  });

  it('mantiene el enlace accesible entre cada seccion y su encabezado', () => {
    const fixture = TestBed.createComponent(StyleGuidePageComponent);
    fixture.detectChanges();
    const element = fixture.nativeElement as HTMLElement;

    const sections = [...element.querySelectorAll('section[aria-labelledby]')];
    expect(sections.length).toBe(expectedTitles.length);
    sections.forEach((section) => {
      const labelledBy = section.getAttribute('aria-labelledby') ?? '';
      const heading = section.querySelector('h2.guide__title');
      expect(heading?.getAttribute('id')).toBe(labelledBy);
    });
  });

  it('conserva las muestras de cada bloque (color, forma, chips y apilamiento)', () => {
    const fixture = TestBed.createComponent(StyleGuidePageComponent);
    fixture.detectChanges();
    const element = fixture.nativeElement as HTMLElement;

    // 5 superficies mas 6 acentos, cada muestra con su etiqueta en superficie neutra.
    expect(element.querySelectorAll('.swatch').length).toBe(11);
    // Las cinco filas del vocabulario de forma con su muestra aplicada.
    expect(element.querySelectorAll('.shape-table tbody tr').length).toBe(5);
    // Un chip por tono semantico del contrato en su seccion (la tabla de
    // ejemplo pinta otros dos en sus filas, asi que cuento dentro de la seccion).
    expect(
      element.querySelectorAll('section[aria-labelledby="chips-title"] app-status-chip').length,
    ).toBe(5);
    // Los seis niveles de la escala de apilamiento.
    expect(element.querySelectorAll('.guide__z li').length).toBe(6);
  });
});
