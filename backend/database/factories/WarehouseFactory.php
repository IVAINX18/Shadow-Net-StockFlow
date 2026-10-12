<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        // Genero códigos únicos porque uq_warehouses_code rechaza duplicados
        // y los tests de unicidad necesitan un primer registro válido.
        return [
            'code' => strtoupper(fake()->unique()->bothify('WH-####-??')),
            'name' => 'Almacén '.fake()->unique()->city(),
            'address' => fake()->optional()->streetAddress(),
            'city' => fake()->optional()->city(),
            'manager_user_id' => null,
            'is_active' => true,
        ];
    }
}
