<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Creo el catálogo de permisos porque cada acción protegida de la API se
// autoriza por slug (`modulo.recurso.accion`); el módulo me permite agrupar
// permisos por pantalla sin consultas adicionales.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique('uq_permissions_slug');
            $table->string('module', 60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
