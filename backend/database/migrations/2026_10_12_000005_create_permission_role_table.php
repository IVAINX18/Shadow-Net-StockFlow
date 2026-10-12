<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Creo el pivote permiso-rol con borrado en cascada porque define qué puede
// hacer cada rol: si un permiso o un rol desaparece, su vínculo debe irse con
// él en lugar de autorizar algo inexistente.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_role', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->primary(['permission_id', 'role_id']);

            $table->foreign('permission_id', 'fk_permission_role_permissions')
                ->references('id')->on('permissions')
                ->cascadeOnDelete();

            $table->foreign('role_id', 'fk_permission_role_roles')
                ->references('id')->on('roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
