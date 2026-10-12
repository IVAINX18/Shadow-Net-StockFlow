<?php

namespace App\Providers;

use App\Domain\Enums\PermissionSlug;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Claveo por email en minúsculas más IP para que el bloqueo frene la
        // fuerza bruta contra una cuenta sin castigar a otros usuarios (C-03).
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Registro un Gate por cada slug del catálogo para que `can()` y las
        // rutas compartan la misma fuente de verdad. Deliberadamente NO uso
        // Gate::before: un Administrador no debe saltarse el alcance por
        // almacén, solo lo concede `warehouses.view_all` en la Policy.
        foreach (PermissionSlug::values() as $slug) {
            Gate::define($slug, static fn (User $user): bool => $user->hasPermission($slug));
        }
    }
}
