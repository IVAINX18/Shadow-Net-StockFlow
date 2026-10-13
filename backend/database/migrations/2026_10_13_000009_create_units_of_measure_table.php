<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Creo las unidades de medida con código único porque RF-031 lo exige como
// identificador estable (UND, KG, LT) y la unicidad vive en el motor, no en
// el servicio, para que ni dos peticiones simultáneas la rompan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->id();
            $table->string('code', 20)->unique('uq_units_of_measure_code');
            $table->string('name', 80);
            $table->boolean('allows_decimals')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};
