<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Creo la bitácora de auditoría como tabla append-only: solo lleva
// created_at y nunca updated_at, porque el §4 prohíbe editar o borrar
// evidencias. La FK al usuario usa RESTRICT para no dejar entradas huérfanas.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 60);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            // Solo created_at y con valor por defecto: la tabla es append-only y
            // el modelo desactiva los timestamps, así que la base estampa la
            // fecha aunque el llamante no la envíe.
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_audit_logs_user')
                ->references('id')->on('users')
                ->restrictOnDelete();

            $table->index('user_id', 'idx_audit_logs_user');
            $table->index(['entity_type', 'entity_id'], 'idx_audit_logs_entity');
            $table->index('created_at', 'idx_audit_logs_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
