<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Creo el pivote usuario-rol con borrado en cascada porque al eliminar un rol
// o desactivar definitivamente un usuario sus asignaciones no deben quedar
// huérfanas; la PK compuesta impide duplicar la misma asignación.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');

            $table->primary(['role_id', 'user_id']);

            $table->foreign('role_id', 'fk_role_user_roles')
                ->references('id')->on('roles')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'fk_role_user_users')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
