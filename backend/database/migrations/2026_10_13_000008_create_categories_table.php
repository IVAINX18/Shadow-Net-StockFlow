<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Creo las categorías jerárquicas con desactivación lógica porque el §4 me
// obliga a no borrar una categoría con productos o hijas: la FK con RESTRICT
// es la red dura y el servicio añade la guarda de negocio con 409.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 150);
            // El slug es nulo cuando el cliente no lo envía: MySQL admite
            // varios NULL en una UNIQUE, así que la unicidad solo muerde
            // cuando el valor viene informado. [DECISIÓN]
            $table->string('slug', 180)->nullable()->unique('uq_categories_slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id', 'fk_categories_parent')
                ->references('id')->on('categories')
                ->restrictOnDelete();

            $table->index('parent_id', 'idx_categories_parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
