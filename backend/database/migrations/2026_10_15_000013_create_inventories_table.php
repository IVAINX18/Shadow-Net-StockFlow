<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Creo el saldo materializado con una sola fila por par producto-almacén: esta
// es la fila que la Unidad 2 bloqueará con SELECT ... FOR UPDATE para
// serializar las confirmaciones concurrentes, así que la UNIQUE es la
// garantía dura contra la doble fila y el CHECK la última red del no
// negativo (la validación real vive en el servicio transaccional).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            // Cantidades siempre DECIMAL(15,3) y promedio con DECIMAL(18,6)
            // para no perder precisión en la división (§2).
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('reserved_quantity', 15, 3)->default(0);
            $table->decimal('average_cost', 18, 6)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id', 'fk_inventories_product')
                ->references('id')->on('products')
                ->restrictOnDelete();

            $table->foreign('warehouse_id', 'fk_inventories_warehouse')
                ->references('id')->on('warehouses')
                ->restrictOnDelete();

            $table->unique(['product_id', 'warehouse_id'], 'uq_inventories_product_warehouse');
            $table->index('warehouse_id', 'idx_inventories_warehouse_id');
        });

        // La existencia nunca es negativa: MySQL 8.4 sí aplica los CHECK, así
        // que es defensa en profundidad junto a la validación del servicio.
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT ck_inventories_quantity CHECK (quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
