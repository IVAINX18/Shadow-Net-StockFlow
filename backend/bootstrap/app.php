<?php

use App\Http\Errors\ApiErrorResponse;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Activo la cookie de sesión para la SPA propia, que es como el §2 de
        // api-design.md exige autenticar al cliente de primera parte.
        $middleware->statefulApi();

        // Esta API no tiene pagina de login web. Devuelvo null para que el
        // middleware de autenticacion no busque la ruta "login" (que no existe)
        // cuando la peticion no pide JSON, y el fallo termine en el 401
        // uniforme en lugar de un error 500 por ruta inexistente.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Mapeo cada excepción al sobre uniforme para que ningún error de la
        // API filtre trazas, SQL ni datos ajenos al cliente.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                'NO_AUTENTICADO',
                'No autenticado. Inicie sesión para continuar.',
                401
            );
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Preservo CUENTA_INACTIVA cuando el login o el middleware ya la
            // marcaron, para no enmascarar la desactivación como permiso.
            if ($e->getMessage() === 'CUENTA_INACTIVA') {
                return ApiErrorResponse::make(
                    'CUENTA_INACTIVA',
                    'Cuenta desactivada: el usuario no puede iniciar sesión ni operar.',
                    403
                );
            }

            return ApiErrorResponse::make(
                'SIN_PERMISO',
                'No tiene permiso para realizar esta acción.',
                403
            );
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Preservo CUENTA_INACTIVA cuando el middleware ya la marcó, para no
            // enmascarar la desactivación como un permiso genérico.
            $previous = $e->getPrevious();

            if ($previous instanceof AuthorizationException && $previous->getMessage() === 'CUENTA_INACTIVA') {
                return ApiErrorResponse::make(
                    'CUENTA_INACTIVA',
                    'Cuenta desactivada: el usuario no puede iniciar sesión ni operar.',
                    403
                );
            }

            return ApiErrorResponse::make(
                'SIN_PERMISO',
                'No tiene permiso para realizar esta acción.',
                403
            );
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                'NO_ENCONTRADO',
                'El recurso solicitado no existe.',
                404
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                'NO_ENCONTRADO',
                'El recurso solicitado no existe.',
                404
            );
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $details = [];

            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $details[] = [
                        'field' => $field,
                        'code' => 'VALIDACION',
                        'message' => $message,
                    ];
                }
            }

            return ApiErrorResponse::make(
                'VALIDACION',
                'Los datos enviados no son válidos.',
                422,
                $details
            );
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                'LIMITE_EXCEDIDO',
                'Demasiadas solicitudes. Inténtelo de nuevo más tarde.',
                429
            );
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                'LIMITE_EXCEDIDO',
                'Demasiadas solicitudes. Inténtelo de nuevo más tarde.',
                429
            );
        });

        // Los errores HTTP de Symfony llegan como HttpException, no como clases
        // propias: el 419 del CSRF es el caso tipico. Sin este mapeo acabarian
        // en el manejador generico y la API devolveria 500 en lugar del estado
        // real, confundiendo al cliente de la SPA.
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();

            if ($status === 419) {
                return ApiErrorResponse::make(
                    'CSRF_INVALIDO',
                    'La sesión de seguridad expiró. Vuelva a iniciar sesión.',
                    419
                );
            }

            return ApiErrorResponse::make(
                $status === 405 ? 'METODO_NO_PERMITIDO' : 'SOLICITUD_INVALIDA',
                $status === 405
                    ? 'El método HTTP no está permitido para este recurso.'
                    : 'La solicitud no pudo procesarse.',
                $status
            );
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Registro el error real con un id y solo expongo el id, para poder
            // rastrearlo en logs sin filtrar trazas ni SQL al cliente.
            $errorId = (string) Str::uuid();

            logger()->error('Error interno de la API', [
                'error_id' => $errorId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return ApiErrorResponse::make(
                'ERROR_INTERNO',
                'Ocurrió un error inesperado. Inténtelo de nuevo más tarde.',
                500,
                [['error_id' => $errorId]]
            );
        });
    })->create();
