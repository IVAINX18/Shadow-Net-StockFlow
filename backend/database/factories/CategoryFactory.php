<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        // Genero nombres únicos porque las pruebas de slug y de jerarquía
        // necesitan varios nodos sin colisionar en la restricción única.
        return [
            'name' => 'Categoría '.fake()->unique()->words(2, true),
            'slug' => null,
            'parent_id' => null,
            'is_active' => true,
        ];
    }
}
