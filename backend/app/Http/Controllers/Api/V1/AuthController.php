<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Mantengo este controlador fino porque las reglas (credenciales, estado de la
// cuenta) viven en el Form Request y el middleware; aquí solo traduzco HTTP.
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        // Regenero la sesión tras el login para que un id previo no quede
        // reutilizable (fijación de sesión, C-06).
        $request->session()->regenerate();

        /** @var User $user */
        $user = User::query()->findOrFail(Auth::id());
        $user->last_login_at = now()->utc();
        $user->save();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Invalido la sesión en el servidor para que la cookie robada o
        // reutilizada ya no autorice nada (C-06).
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        // Devuelvo la misma forma que el login (objeto "user") para que el
        // cliente tenga un unico contrato al leer el perfil autenticado.
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }
}
