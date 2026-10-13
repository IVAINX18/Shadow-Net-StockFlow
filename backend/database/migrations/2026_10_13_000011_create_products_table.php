<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Creo los productos con SKU único y FK con RESTRICT a categorías y unidades
// porque el §4 me obliga a preservar el historial: nunca borro un producto
// referenciado, lo desactivo. El CHECK de costing_method es la red dura del
// §3 y el servicio valida además el valor permitido antes de insertar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->string('sku', 60)->unique('uq_products_sku');
            $table->string('name', 180);
            $table->text('description')->nullable();
            // El código de barras es único solo cuando viene informado: MySQL
            // admite varios NULL en una UNIQUE, así que varios productos sin
            // código conviven sin violar uq_products_barcode.
            $table->string('barcode', 80)->nullable()->unique('uq_products_barcode');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('unit_of_measure_id');
            $table->boolean('is_active')->default(true);
            // Dinero siempre DECIMAL(18,2): nunca tipos de punto flotante (§2).
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->decimal('sale_price', 18, 2)->default(0);
            $table->string('costing_method', 30)->default('promedio_ponderado');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id', 'fk_products_category')
                ->references('id')->on('categories')
                ->restrictOnDelete();

            $table->foreign('unit_of_measure_id', 'fk_products_unit_of_measure')
                ->references('id')->on('units_of_measure')
                ->restrictOnDelete();

            $table->index('category_id', 'idx_products_category_id');
            $table->index('is_active', 'idx_products_is_active');
            // Añado idx_products_name aunque el modelo documenta solo dos
            // índices: el backlog EP-004 pide búsqueda por SKU, nombre y
            // código con índices, y el nombre parcial (LIKE %...%) es el
            // único de los tres sin índice dedicado. [DECISIÓN Unidad 2]
            $table->index('name', 'idx_products_name');
        });

        // El CHECK limita el método de costeo a los literales del §3: el MVP
        // solo usa promedio_ponderado y fifo queda reservado. MySQL 8.4 sí
        // aplica los CHECK, así que es defensa en profundidad junto a la
        // validación del Form Request.
        DB::statement("ALTER TABLE products ADD CONSTRAINT ck_products_costing_method CHECK (costing_method IN ('promedio_ponderado','fifo'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
