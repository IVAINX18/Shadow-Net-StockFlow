<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Enums\InventoryDocumentStatus;
use App\Domain\Enums\InventoryDocumentType;
use App\Models\InventoryDocument;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryDocument>
 */
class InventoryDocumentFactory extends Factory
{
    public function definition(): array
    {
        // Genero uuid y clave únicos porque ambas columnas tienen UNIQUE y las
        // pruebas de idempotencia necesitan un primer registro válido; el
        // número legible lo deja el servicio, aquí solo evito colisiones.
        return [
            'uuid' => (string) Str::uuid(),
            'doc_number' => 'TMP-'.(string) Str::uuid(),
            'type' => InventoryDocumentType::Entrada->value,
            'status' => InventoryDocumentStatus::Borrador->value,
            'origin_warehouse_id' => Warehouse::factory(),
            'destination_warehouse_id' => null,
            'idempotency_key' => (string) Str::uuid(),
            'reference' => null,
            'notes' => null,
            'reason_code' => null,
            'created_by' => User::factory(),
            'confirmed_by' => null,
            'confirmed_at' => null,
            'reversed_by_id' => null,
        ];
    }
}
