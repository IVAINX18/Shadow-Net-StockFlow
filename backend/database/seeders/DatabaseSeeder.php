<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Mantengo este orden porque los roles referencian permisos y el
        // administrador referencía el rol: sembrar fuera de orden fallaría.
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            DevAdminUserSeeder::class,
            UnitOfMeasureSeeder::class,
        ]);
    }
}
