<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Creo el documento de inventario como intención de negocio: en esta unidad
// solo nace y vive en `borrador` (la confirmación llega en la Unidad 2), así
// que los movimientos y el saldo aún no lo tocan. La UNIQUE sobre
// idempotency_key es la garantía dura de la idempotencia (I-06): aunque dos
// peticiones con la misma clave lleguen a la vez, solo una inserta la fila.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_documents', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            // Identificador público: la API lo expone en la URL y nunca el id
            // interno (§1 de database-design.md).
            $table->char('uuid', 36)->unique('uq_inventory_documents_uuid');
            $table->string('doc_number', 40)->unique('uq_inventory_documents_doc_number');
            $table->string('type', 20);
            $table->string('status', 20)->default('borrador');
            $table->unsignedBigInteger('origin_warehouse_id');
            $table->unsignedBigInteger('destination_warehouse_id')->nullable();
            $table->string('idempotency_key', 80)->unique('uq_inventory_documents_idempotency_key');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('reason_code', 30)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->timestamps();

            $table->foreign('origin_warehouse_id', 'fk_inventory_documents_origin')
                ->references('id')->on('warehouses')
                ->restrictOnDelete();

            $table->foreign('destination_warehouse_id', 'fk_inventory_documents_destination')
                ->references('id')->on('warehouses')
                ->restrictOnDelete();

            $table->foreign('created_by', 'fk_inventory_documents_created_by')
                ->references('id')->on('users')
                ->restrictOnDelete();

            $table->foreign('confirmed_by', 'fk_inventory_documents_confirmed_by')
                ->references('id')->on('users')
                ->restrictOnDelete();

            // Autorreferencia al documento de reversión: RESTRICT porque el
            // libro es append-only y ningún eslabón puede desaparecer (§4).
            $table->foreign('reversed_by_id', 'fk_inventory_documents_reversed_by')
                ->references('id')->on('inventory_documents')
                ->restrictOnDelete();

            $table->index('origin_warehouse_id', 'idx_inventory_documents_origin_warehouse');
            $table->index('destination_warehouse_id', 'idx_inventory_documents_destination_warehouse');
            $table->index('status', 'idx_inventory_documents_status');
            $table->index(['type', 'created_at'], 'idx_inventory_documents_type_created');
        });

        // Las enumeraciones se controlan con CHECK como defensa en profundidad
        // (§3): el servicio valida primero y la base rechaza lo que se cuele.
        DB::statement("ALTER TABLE inventory_documents ADD CONSTRAINT ck_inventory_documents_type CHECK (type IN ('entrada','salida','ajuste','transferencia'))");
        DB::statement("ALTER TABLE inventory_documents ADD CONSTRAINT ck_inventory_documents_status CHECK (status IN ('borrador','confirmado','revertido','descartado'))");
        DB::statement("ALTER TABLE inventory_documents ADD CONSTRAINT ck_inventory_documents_reason CHECK (reason_code IS NULL OR reason_code IN ('inventario_fisico','merma','robo','vencimiento','error_registro','donacion','otro'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_documents');
    }
};
