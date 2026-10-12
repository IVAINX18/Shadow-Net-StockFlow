<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        // Compongo el slug como modulo.recurso.accion porque ese es el
        // formato canónico que el User::hasPermission espera comparar.
        $module = fake()->randomElement(['inventory', 'warehouses', 'products', 'users', 'reports']);

        return [
            'name' => fake()->unique()->sentence(3),
            'slug' => $module.'.'.fake()->unique()->slug(2),
            'module' => $module,
        ];
    }
}
