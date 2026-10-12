import { TestBed } from '@angular/core/testing';
import { StatusChipComponent } from './status-chip.component';

// Verifico que el chip comunique el estado con texto e icono, no solo con color
// (design-system.md §13), y que el tono se refleje en la clase de estilo.
describe('StatusChipComponent', () => {
  async function render(label: string, tone: 'success' | 'error' | 'neutral') {
    const fixture = TestBed.createComponent(StatusChipComponent);
    fixture.componentRef.setInput('label', label);
    fixture.componentRef.setInput('tone', tone);
    await fixture.whenStable();
    return fixture;
  }

  it('muestra la etiqueta y el icono del tono', async () => {
    const fixture = await render('Activo', 'success');
    const element = fixture.nativeElement as HTMLElement;

    expect(element.querySelector('.chip__label')?.textContent).toContain('Activo');
    expect(element.querySelector('.chip__icon')?.textContent).toContain('check_circle');
    expect(element.querySelector('.chip')?.classList).toContain('chip--success');
  });

  it('usa el icono de error para el tono error', async () => {
    const fixture = await render('Inactivo', 'error');
    const element = fixture.nativeElement as HTMLElement;

    expect(element.querySelector('.chip__icon')?.textContent).toContain('error');
    expect(element.querySelector('.chip')?.classList).toContain('chip--error');
  });
});
