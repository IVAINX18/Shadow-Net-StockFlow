<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Creo al administrador de desarrollo solo desde variables de entorno porque
// así ninguna credencial queda fija en el código, en los logs ni en el repo.
class DevAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // No creo nada en producción porque una cuenta sembrada con acceso
        // total sería una puerta trasera; allí los accesos se provisionan por
        // el proceso operativo, nunca por un seeder.
        if (app()->environment('production')) {
            $this->command?->warn('DevAdminUserSeeder omitido: no se crea el administrador en producción.');

            return;
        }

        $email = (string) env('DEV_ADMIN_EMAIL', '');
        $password = (string) env('DEV_ADMIN_PASSWORD', '');
        $name = (string) env('DEV_ADMIN_NAME', 'Administrador de desarrollo');

        // Sin correo o sin clave no hay nada que sembrar: prefiero omitir en
        // silencio controlado antes que crear una cuenta con secreto vacío.
        if ($email === '' || $password === '') {
            $this->command?->warn('DevAdminUserSeeder omitido: faltan DEV_ADMIN_EMAIL o DEV_ADMIN_PASSWORD.');

            return;
        }

        if ($name === '') {
            $name = 'Administrador de desarrollo';
        }

        // Actualizo por correo porque es el identificador único del usuario; así
        // la segunda ejecución refresca el hash en vez de duplicar la cuenta.
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = new User;
            $user->email = $email;
        }

        $user->name = $name;
        $user->password = Hash::make($password);
        $user->is_active = true;
        $user->save();

        $admin = Role::query()->where('slug', 'administrador')->first();

        if ($admin !== null) {
            // Sincronizo el rol para que el usuario quede exactamente como
            // administrador aunque antes tuviera otros roles asignados.
            $user->roles()->sync([$admin->id]);
        }

        // Nunca imprimo la clave porque acabaría en los logs del despliegue y
        // violaría el control C-07 de gestión de secretos.
        $this->command?->info("DevAdminUserSeeder: administrador listo ({$email}).");
    }
}
