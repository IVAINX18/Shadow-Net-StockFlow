<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Creo las líneas del documento con CASCADE hacia su documento porque forman
// el mismo agregado: un borrador se edita reponiendo sus líneas y nunca
// sobrevive a su cabecera. Hacia el producto uso RESTRICT porque la línea es
// historia contable y no puede quedar huérfana (§4).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_document_lines', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('product_id');
            // Cantidad siempre positiva: la dirección la fija el tipo de
            // documento, nunca el signo (RN-INV-01).
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();

            $table->foreign('document_id', 'fk_inventory_document_lines_document')
                ->references('id')->on('inventory_documents')
                ->cascadeOnDelete();

            $table->foreign('product_id', 'fk_inventory_document_lines_product')
                ->references('id')->on('products')
                ->restrictOnDelete();

            $table->index('document_id', 'idx_inventory_document_lines_document');
            $table->index('product_id', 'idx_inventory_document_lines_product');
        });

        DB::statement('ALTER TABLE inventory_document_lines ADD CONSTRAINT ck_inventory_document_lines_quantity CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_document_lines');
    }
};
