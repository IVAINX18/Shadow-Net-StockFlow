<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductWarehouse>
 */
class ProductWarehouseFactory extends Factory
{
    public function definition(): array
    {
        // Entrego las cantidades como cadena con tres decimales porque el
        // contrato las exige como string y nunca como flotante (§3).
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'min_stock' => '0.000',
            'max_stock' => null,
            'reorder_point' => null,
        ];
    }
}
