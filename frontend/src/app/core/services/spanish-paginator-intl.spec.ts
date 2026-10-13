import { SpanishPaginatorIntl } from './spanish-paginator-intl';

// Verifico que el paginador global hable español en todas sus etiquetas y que
// el rango use el texto natural "a ... de ..." con separador de miles.
describe('SpanishPaginatorIntl', () => {
  const paginator = new SpanishPaginatorIntl();

  it('traduce las etiquetas del paginador', () => {
    expect(paginator.itemsPerPageLabel).toBe('Registros por pagina:');
    expect(paginator.nextPageLabel).toBe('Pagina siguiente');
    expect(paginator.previousPageLabel).toBe('Pagina anterior');
    expect(paginator.firstPageLabel).toBe('Primera pagina');
    expect(paginator.lastPageLabel).toBe('Ultima pagina');
  });

  it('muestra el rango en español con la pagina actual', () => {
    expect(paginator.getRangeLabel(0, 15, 3)).toBe('1 a 3 de 3');
    expect(paginator.getRangeLabel(1, 10, 25)).toBe('11 a 20 de 25');
    expect(paginator.getRangeLabel(2, 10, 25)).toBe('21 a 25 de 25');
  });

  it('resuelve el vacio sin divisiones imposibles', () => {
    expect(paginator.getRangeLabel(0, 15, 0)).toBe('0 de 0');
    expect(paginator.getRangeLabel(0, 0, 0)).toBe('0 de 0');
  });

  it('agrupa los miles del total', () => {
    expect(paginator.getRangeLabel(0, 15, 1234)).toBe('1 a 15 de 1.234');
  });
});
