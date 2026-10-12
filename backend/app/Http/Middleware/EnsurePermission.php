<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Exijo un permiso RBAC concreto por ruta para que ninguna acción llegue al
// controlador sin el derecho correspondiente (AC-005): aunque la SPA oculte el
// botón, una petición directa sigue rebotando aquí.
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        // Si no hay usuario, el middleware de autenticación ya habría cortado
        // con 401; pero por defensa no trato un usuario nulo como autorizado.
        if ($user === null || ! $user->hasPermission($permission)) {
            // Lanzo el mensaje genérico de autorización para no revelar cuál
            // permiso falta más allá del código SIN_PERMISO del §5.
            throw new AuthorizationException('SIN_PERMISO');
        }

        return $next($request);
    }
}
