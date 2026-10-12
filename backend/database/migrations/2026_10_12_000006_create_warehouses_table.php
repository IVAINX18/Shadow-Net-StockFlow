<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Creo los almacenes con desactivación más borrado lógico porque el §4 me
// obliga a preservar su kardex: nunca elimino físicamente un almacén con
// historia. El responsable usa RESTRICT para no dejar movimientos sin dueño.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->string('code', 30)->unique('uq_warehouses_code');
            $table->string('name', 150);
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedBigInteger('manager_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('manager_user_id', 'fk_warehouses_manager_user')
                ->references('id')->on('users')
                ->restrictOnDelete();

            $table->index('is_active', 'idx_warehouses_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
