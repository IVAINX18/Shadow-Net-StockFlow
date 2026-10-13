<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

// Siembro las tres unidades base del §5.2 con updateOrCreate porque RF-032 las
// exige precargadas y la semilla debe ser idempotente ante reejecuciones.
class UnitOfMeasureSeeder extends Seeder
{
    public function run(): void
    {
        // Uso el código como clave porque es el identificador estable y único;
        // el nombre o el permiso de decimales pueden corregirse sin duplicar.
        UnitOfMeasure::updateOrCreate(
            ['code' => 'UND'],
            ['name' => 'Unidad', 'allows_decimals' => false]
        );

        UnitOfMeasure::updateOrCreate(
            ['code' => 'KG'],
            ['name' => 'Kilogramo', 'allows_decimals' => true]
        );

        UnitOfMeasure::updateOrCreate(
            ['code' => 'LT'],
            ['name' => 'Litro', 'allows_decimals' => true]
        );
    }
}
