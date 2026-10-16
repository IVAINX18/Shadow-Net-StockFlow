<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryDocument;
use App\Models\InventoryDocumentLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryDocumentLine>
 */
class InventoryDocumentLineFactory extends Factory
{
    public function definition(): array
    {
        // Entrego cantidad y costos como cadena porque el contrato los exige
        // como string y nunca como flotante; el total ya viene redondeado a
        // dos decimales como lo calcularía el servicio.
        return [
            'document_id' => InventoryDocument::factory(),
            'product_id' => Product::factory(),
            'quantity' => '2.500',
            'unit_cost' => '10.250000',
            'line_total' => '25.63',
        ];
    }
}
