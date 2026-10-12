<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Enums\PermissionSlug;
use App\Models\Permission;
use Illuminate\Database\Seeder;

// Siembro el catálogo de permisos desde el enum porque así existe una sola
// fuente de verdad y un cambio de slug no deja la base desincronizada.
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Uso firstOrCreate con el slug como clave porque así la segunda
        // ejecución encuentra lo ya creado y no duplica nada.
        foreach (PermissionSlug::values() as $slug) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'module' => explode('.', $slug)[0],
                ]
            );
        }
    }
}
