<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Bloqueo aquí a las cuentas desactivadas para que ni siquiera lleguen al
// controlador, porque el §7.1 exige rechazar también la operación.
final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->is_active === false) {
            // Cierro la sesión para no dejar una cookie válida en manos de una
            // cuenta que ya no puede operar.
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Lanzo esta excepción marcada para que el render de 403 conserve el
            // código CUENTA_INACTIVA en lugar del SIN_PERMISO genérico.
            throw new AuthorizationException('CUENTA_INACTIVA');
        }

        return $next($request);
    }
}
