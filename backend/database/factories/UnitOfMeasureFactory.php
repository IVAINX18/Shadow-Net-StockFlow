<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    public function definition(): array
    {
        // Genero códigos únicos porque uq_units_of_measure_code rechaza
        // duplicados y las pruebas de unicidad necesitan un primer registro.
        return [
            'code' => strtoupper(fake()->unique()->lexify('??').fake()->unique()->numerify('###')),
            'name' => 'Unidad '.fake()->unique()->word(),
            'allows_decimals' => false,
        ];
    }
}
