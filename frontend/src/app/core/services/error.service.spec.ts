import { HttpErrorResponse } from '@angular/common/http';
import { ErrorService } from './error.service';
import { AppError } from '../models/app-error.model';

// Verifico que el interceptor normalice el sobre uniforme del backend
// (api-design.md §5) a un AppError tipado, porque de esa clasificacion depende
// como cada pantalla presenta el fallo.
describe('ErrorService', () => {
  const service = new ErrorService();

  it('clasifica el 422 como validacion y conserva el detalle por campo', () => {
    const response = new HttpErrorResponse({
      status: 422,
      url: 'http://localhost:8000/api/v1/auth/login',
      error: {
        error: {
          code: 'VALIDACION',
          message: 'Los datos enviados no son validos.',
          details: [{ field: 'email', code: 'VALIDACION', message: 'El correo es obligatorio.' }],
        },
      },
    });

    const result = service.toAppError(response);

    expect(result).toBeInstanceOf(AppError);
    expect(result.kind).toBe('validation');
    expect(result.code).toBe('VALIDACION');
    expect(result.fieldMessage('email')).toBe('El correo es obligatorio.');
  });

  it('clasifica el 403 CUENTA_INACTIVA como autorizacion', () => {
    const response = new HttpErrorResponse({
      status: 403,
      url: 'http://localhost:8000/api/v1/auth/login',
      error: {
        error: { code: 'CUENTA_INACTIVA', message: 'Cuenta desactivada.', details: [] },
      },
    });

    const result = service.toAppError(response);

    expect(result.kind).toBe('authorization');
    expect(result.code).toBe('CUENTA_INACTIVA');
    expect(result.isAuthorization).toBe(true);
  });

  it('extrae el error_id del 500 y no lo trata como campo', () => {
    const response = new HttpErrorResponse({
      status: 500,
      url: 'http://localhost:8000/api/v1/warehouses',
      error: {
        error: {
          code: 'ERROR_INTERNO',
          message: 'Ocurrio un error inesperado.',
          details: [{ error_id: 'abc-123' }],
        },
      },
    });

    const result = service.toAppError(response);

    expect(result.kind).toBe('server');
    expect(result.errorId).toBe('abc-123');
    expect(result.details).toHaveLength(0);
  });

  it('trata el estado 0 como fallo de red', () => {
    const response = new HttpErrorResponse({ status: 0, url: 'http://localhost:8000/api/v1/health' });

    const result = service.toAppError(response);

    expect(result.kind).toBe('network');
    expect(result.code).toBe('SIN_CONEXION');
  });

  it('devuelve el mismo AppError si ya venia normalizado', () => {
    const original = new AppError({
      kind: 'business',
      code: 'STOCK_INSUFICIENTE',
      message: 'Sin existencias.',
      status: 409,
    });

    expect(service.toAppError(original)).toBe(original);
  });
});
