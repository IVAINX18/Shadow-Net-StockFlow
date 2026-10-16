<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Creo el libro inmutable (kardex): la Unidad 1 solo deja la tabla lista, sin
// escribir en ella, para que la Unidad 2 confirme contra el esquema exacto
// del §5.3. No lleva updated_at porque un movimiento jamás se modifica; todas
// las FK son RESTRICT porque ningún eslabón del kardex puede desaparecer.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_line_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->string('direction', 10);
            $table->decimal('quantity_delta', 15, 3);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('balance_after', 15, 3);
            // Solo created_at y con valor por defecto: el libro es append-only
            // y el modelo desactiva los timestamps, igual que audit_logs.
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedBigInteger('created_by');

            $table->foreign('document_id', 'fk_inventory_movements_document')
                ->references('id')->on('inventory_documents')
                ->restrictOnDelete();

            $table->foreign('document_line_id', 'fk_inventory_movements_line')
                ->references('id')->on('inventory_document_lines')
                ->restrictOnDelete();

            $table->foreign('product_id', 'fk_inventory_movements_product')
                ->references('id')->on('products')
                ->restrictOnDelete();

            $table->foreign('warehouse_id', 'fk_inventory_movements_warehouse')
                ->references('id')->on('warehouses')
                ->restrictOnDelete();

            $table->foreign('created_by', 'fk_inventory_movements_created_by')
                ->references('id')->on('users')
                ->restrictOnDelete();

            $table->index(['product_id', 'warehouse_id', 'created_at'], 'idx_inventory_movements_kardex');
            $table->index('document_id', 'idx_inventory_movements_document');
            $table->index(['warehouse_id', 'created_at'], 'idx_inventory_movements_warehouse_date');
        });

        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT ck_inventory_movements_direction CHECK (direction IN ('entrada','salida'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
