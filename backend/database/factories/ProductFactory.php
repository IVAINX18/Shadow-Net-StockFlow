<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        // Genero SKU únicos porque uq_products_sku rechaza duplicados y las
        // pruebas de unicidad necesitan un primer registro válido.
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'name' => 'Producto '.fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'barcode' => null,
            'category_id' => Category::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'is_active' => true,
            // Entrego los montos como cadena con dos decimales porque el
            // contrato los exige como string y nunca como flotante.
            'cost_price' => '0.00',
            'sale_price' => '0.00',
            'costing_method' => 'promedio_ponderado',
        ];
    }
}
