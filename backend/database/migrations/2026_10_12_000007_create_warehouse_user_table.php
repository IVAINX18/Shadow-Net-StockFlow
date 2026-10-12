<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Creo el pivote usuario-almacén porque el alcance por almacén del §5.1 se
// modela como asignación explícita; la cascada evita alcances huérfanos y la
// PK compuesta impide asignar dos veces el mismo almacén a un usuario.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_user', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('warehouse_id');

            $table->primary(['user_id', 'warehouse_id']);

            $table->foreign('user_id', 'fk_warehouse_user_users')
                ->references('id')->on('users')
                ->cascadeOnDelete();

            $table->foreign('warehouse_id', 'fk_warehouse_user_warehouses')
                ->references('id')->on('warehouses')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_user');
    }
};
