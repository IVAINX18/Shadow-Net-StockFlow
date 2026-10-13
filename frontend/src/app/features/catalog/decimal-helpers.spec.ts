import { compareDecimalStrings, isDecimalString, minFitsInMax } from './decimal-helpers';

// Verifico las ayudas de decimales como cadena: el catalogo nunca convierte
// dinero ni cantidades a float, asi que la comparacion por partes debe ser
// exacta, incluido el caso de distinta longitud de fraccion.
describe('decimal-helpers', () => {
  describe('isDecimalString', () => {
    it('acepta enteros y decimales dentro del maximo', () => {
      expect(isDecimalString('0', 2)).toBe(true);
      expect(isDecimalString('12', 2)).toBe(true);
      expect(isDecimalString('12.5', 2)).toBe(true);
      expect(isDecimalString('12.50', 2)).toBe(true);
    });

    it('rechaza vacios, negativos, letras y exceso de decimales', () => {
      expect(isDecimalString('', 2)).toBe(false);
      expect(isDecimalString('-1', 2)).toBe(false);
      expect(isDecimalString('12.500', 2)).toBe(false);
      expect(isDecimalString('12,50', 2)).toBe(false);
      expect(isDecimalString('doce', 2)).toBe(false);
      expect(isDecimalString('12.', 2)).toBe(false);
    });

    it('respeta el maximo de decimales pedido', () => {
      expect(isDecimalString('4.125', 3)).toBe(true);
      expect(isDecimalString('4.1255', 3)).toBe(false);
      expect(isDecimalString('4.1', 0)).toBe(false);
      expect(isDecimalString('4', 0)).toBe(true);
    });
  });

  describe('compareDecimalStrings', () => {
    it('ordena por la parte entera sin importar los ceros a la izquierda', () => {
      expect(compareDecimalStrings('2', '10')).toBe(-1);
      expect(compareDecimalStrings('10', '2')).toBe(1);
      expect(compareDecimalStrings('007', '7')).toBe(0);
    });

    it('compara la fraccion digito por digito con distinta longitud', () => {
      expect(compareDecimalStrings('1.5', '1.50')).toBe(0);
      expect(compareDecimalStrings('1.5', '1.51')).toBe(-1);
      expect(compareDecimalStrings('1.51', '1.5')).toBe(1);
      expect(compareDecimalStrings('0.125', '0.13')).toBe(-1);
    });

    it('distingue valores que un float confundiria', () => {
      expect(compareDecimalStrings('0.1', '0.10')).toBe(0);
      expect(compareDecimalStrings('999999999999.99', '1000000000000')).toBe(-1);
    });
  });

  describe('minFitsInMax', () => {
    it('acepta el maximo nulo como sin tope', () => {
      expect(minFitsInMax('100', null)).toBe(true);
      expect(minFitsInMax('100', '')).toBe(true);
    });

    it('exige maximo mayor o igual que minimo', () => {
      expect(minFitsInMax('5', '5')).toBe(true);
      expect(minFitsInMax('4.5', '10.25')).toBe(true);
      expect(minFitsInMax('10.25', '4.5')).toBe(false);
    });
  });
});
