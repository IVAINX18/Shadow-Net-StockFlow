import { FormControl, FormGroup } from '@angular/forms';
import { decimalStringValidator, minMaxValidator } from './catalog-validators';

// Verifico los validadores de presentacion del catalogo: anticipan la forma
// que exige el backend sin sustituirlo, y la comparacion min/max nunca pasa
// por float.
describe('catalog-validators', () => {
  describe('decimalStringValidator', () => {
    it('deja pasar vacios y nulos (el requerido decide aparte)', () => {
      const validate = decimalStringValidator(2);

      expect(validate(new FormControl(''))).toBeNull();
      expect(validate(new FormControl(null))).toBeNull();
    });

    it('acepta cadenas decimales validas y rechaza el resto', () => {
      const validate = decimalStringValidator(2);

      expect(validate(new FormControl('12.50'))).toBeNull();
      expect(validate(new FormControl('0'))).toBeNull();
      expect(validate(new FormControl('12.500'))).toEqual({ decimal: { maxDecimals: 2 } });
      expect(validate(new FormControl('-3'))).toEqual({ decimal: { maxDecimals: 2 } });
      expect(validate(new FormControl(12.5))).toEqual({ decimal: { maxDecimals: 2 } });
    });
  });

  describe('minMaxValidator', () => {
    function group(min: string, max: string): FormGroup {
      return new FormGroup(
        { min_stock: new FormControl(min), max_stock: new FormControl(max) },
        { validators: [minMaxValidator('min_stock', 'max_stock')] },
      );
    }

    it('acepta maximo mayor o igual que minimo y maximo vacio', () => {
      expect(group('5', '5').hasError('minMax')).toBe(false);
      expect(group('4.5', '10.25').hasError('minMax')).toBe(false);
      expect(group('5', '').hasError('minMax')).toBe(false);
    });

    it('rechaza maximo menor que minimo', () => {
      expect(group('10.25', '4.5').hasError('minMax')).toBe(true);
    });
  });
});
