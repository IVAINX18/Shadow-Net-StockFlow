import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';
import { compareDecimalStrings, isDecimalString } from './decimal-helpers';

// Validadores de presentacion para el catalogo. Anticipan la forma que exige
// el backend, pero nunca lo sustituyen: el servidor revalida todo y decide.

/** Exige una cadena decimal no negativa con el maximo de decimales dado. */
export function decimalStringValidator(maxDecimals: number): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const value = control.value as unknown;

    if (value === null || value === undefined || value === '') {
      return null;
    }

    if (typeof value !== 'string' || !isDecimalString(value.trim(), maxDecimals)) {
      return { decimal: { maxDecimals } };
    }

    return null;
  };
}

/**
 * Exige maximo mayor o igual que minimo dentro del mismo grupo, comparando
 * cadenas sin pasar por float. El maximo vacio significa sin tope.
 */
export function minMaxValidator(minKey: string, maxKey: string): ValidatorFn {
  return (group: AbstractControl): ValidationErrors | null => {
    const min = group.get(minKey)?.value as unknown;
    const max = group.get(maxKey)?.value as unknown;

    if (typeof min !== 'string' || min.trim() === '') {
      return null;
    }

    if (typeof max !== 'string' || max.trim() === '') {
      return null;
    }

    if (!isDecimalString(min.trim(), 3) || !isDecimalString(max.trim(), 3)) {
      return null;
    }

    return compareDecimalStrings(min.trim(), max.trim()) > 0 ? { minMax: true } : null;
  };
}
