// Ayudas puras para decimales como cadena (design-system.md §11 y
// api-design.md §3). Nunca convierto dinero ni cantidades a float: comparo por
// partes (entera y fraccionaria) para no perder exactitud.

/** Indica si el texto es un decimal no negativo con el maximo de decimales dado. */
export function isDecimalString(value: string, maxDecimals: number): boolean {
  if (value.length === 0) {
    return false;
  }

  const pattern = maxDecimals === 0 ? /^\d+$/ : new RegExp(`^\\d+(\\.\\d{1,${maxDecimals}})?$`);

  return pattern.test(value);
}

/**
 * Compara dos cadenas decimales validas sin pasar por float: -1 si a < b,
 * 0 si son iguales y 1 si a > b. Normalizo la fraccion a la misma longitud
 * para comparar digito por digito.
 */
export function compareDecimalStrings(a: string, b: string): number {
  const [aInt, aFrac = ''] = a.split('.');
  const [bInt, bFrac = ''] = b.split('.');

  const aIntNorm = stripLeadingZeros(aInt ?? '');
  const bIntNorm = stripLeadingZeros(bInt ?? '');

  if (aIntNorm.length !== bIntNorm.length) {
    return aIntNorm.length < bIntNorm.length ? -1 : 1;
  }

  if (aIntNorm !== bIntNorm) {
    return aIntNorm < bIntNorm ? -1 : 1;
  }

  const length = Math.max(aFrac.length, bFrac.length);
  const aFracNorm = aFrac.padEnd(length, '0');
  const bFracNorm = bFrac.padEnd(length, '0');

  if (aFracNorm === bFracNorm) {
    return 0;
  }

  return aFracNorm < bFracNorm ? -1 : 1;
}

/** Indica si el minimo cabe en el maximo (maximo nulo significa sin tope). */
export function minFitsInMax(min: string, max: string | null): boolean {
  if (max === null || max.length === 0) {
    return true;
  }

  return compareDecimalStrings(min, max) <= 0;
}

function stripLeadingZeros(digits: string): string {
  const stripped = digits.replace(/^0+/, '');
  return stripped === '' ? '0' : stripped;
}
