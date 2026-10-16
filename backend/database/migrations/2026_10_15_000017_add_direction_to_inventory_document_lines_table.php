<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Añado la dirección por línea porque el dominio exige que cada línea de
// ajuste traiga "la cantidad y el signo" (§4.3) y ningún campo persistido lo
// expresaba: la cantidad siempre es positiva por RN-INV-01 y el motivo no
// distingue el signo. Solo el ajuste la usa (los demás tipos la fijan por
// tipo); nula equivale a no aplicable y el servicio la normaliza a `entrada`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_document_lines', function (Blueprint $table) {
            $table->string('direction', 10)->nullable()->after('quantity');
        });

        DB::statement("ALTER TABLE inventory_document_lines ADD CONSTRAINT ck_inventory_document_lines_direction CHECK (direction IS NULL OR direction IN ('entrada','salida'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_document_lines DROP CHECK ck_inventory_document_lines_direction');

        Schema::table('inventory_document_lines', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
