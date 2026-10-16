<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        // Genero slugs únicos porque la restricción uq_roles_slug rechaza
        // duplicados y los tests necesitan crear varios roles sin choques.
        // Recorto el cargo a 80 porque Faker genera títulos de hasta 80 y pico
        // caracteres y la columna roles.name es varchar(80): sin el recorte el
        // alta falla al azar según el título que toque.
        $name = mb_substr(fake()->unique()->jobTitle(), 0, 80);

        return [
            'name' => $name,
            'slug' => str(fake()->unique()->slug(2))->append('-'.fake()->unique()->randomNumber(5))->toString(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
