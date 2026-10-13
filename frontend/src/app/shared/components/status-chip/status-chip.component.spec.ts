import { TestBed } from '@angular/core/testing';
import { StatusChipComponent, type ChipTone } from './status-chip.component';

// Verifico que el chip comunique el estado con texto e icono, no solo con color
// (design-system.md §13), y que el tono se refleje en la clase de estilo.
describe('StatusChipComponent', () => {
  async function render(label: string, tone: ChipTone) {
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

  // Declaro con honestidad lo que esta suite si puede y no puede probar: solo
  // compruebo clases e iconos. La distincion real de color no se puede afirmar
  // en jsdom porque no resuelve estilos computados; se verifica con medicion
  // en navegador real (design-system.md §16), asi que nadie debe leer un verde
  // aqui como prueba de colores distintos.
  // Cubro que info y warning conservan clase e icono propios, porque el defecto
  // medido fue que exito y advertencia colisionaban al resolver primario y
  // terciario al mismo valor. La clase distinta es condicion necesaria pero no
  // prueba de color distinto.
  it('separa informacion de advertencia con clase e icono propios', async () => {
    const infoElement = (await render('En revision', 'info')).nativeElement as HTMLElement;
    const warningElement = (await render('Por vencer', 'warning')).nativeElement as HTMLElement;

    expect(infoElement.querySelector('.chip')?.classList).toContain('chip--info');
    expect(warningElement.querySelector('.chip')?.classList).toContain('chip--warning');
    expect(infoElement.querySelector('.chip__icon')?.textContent).toContain('info');
    expect(warningElement.querySelector('.chip__icon')?.textContent).toContain('warning');
  });
});
