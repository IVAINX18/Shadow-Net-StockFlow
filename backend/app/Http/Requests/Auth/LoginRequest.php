<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

// Valido solo la forma (email y password) porque la existencia de la cuenta y
// su estado se deciden después, con mensajes genéricos anti-enumeración.
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    // Intento primero las credenciales y solo con éxito miro is_active, para
    // que una clave errónea en cuenta desactivada siga dando 401 y no 403.
    public function authenticate(): void
    {
        $credentials = $this->only('email', 'password');

        if (! Auth::attempt($credentials, false)) {
            throw new AuthenticationException('Las credenciales no coinciden con nuestros registros.');
        }

        $user = Auth::user();

        if ($user !== null && $user->is_active === false) {
            Auth::guard('web')->logout();

            $this->session()->invalidate();
            $this->session()->regenerateToken();

            // Lanzo esta excepción marcada para que el render de 403 conserve
            // el código CUENTA_INACTIVA en lugar del SIN_PERMISO genérico.
            throw new AuthorizationException('CUENTA_INACTIVA');
        }
    }
}
