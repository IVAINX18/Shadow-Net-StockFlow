<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Creo la configuración de stock por almacén con CASCADE en ambas FK porque
// así lo ordena literal el modelo (§5.2): al borrar un producto o un almacén
// sus filas de configuración desaparecen con él, a diferencia del RESTRICT
// que preserva historia en el resto del esquema. [DECISIÓN documentada]
// Llevo timestamps porque el modelo afirma que cada tabla los lleva.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_warehouse', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            // Cantidades siempre DECIMAL(15,3), coherente con el §2.
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->decimal('max_stock', 15, 3)->nullable();
            $table->decimal('reorder_point', 15, 3)->nullable();
            $table->timestamps();

            $table->foreign('product_id', 'fk_product_warehouse_product')
                ->references('id')->on('products')
                ->cascadeOnDelete();

            $table->foreign('warehouse_id', 'fk_product_warehouse_warehouse')
                ->references('id')->on('warehouses')
                ->cascadeOnDelete();

            $table->unique(['product_id', 'warehouse_id'], 'uq_product_warehouse');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_warehouse');
    }
};
