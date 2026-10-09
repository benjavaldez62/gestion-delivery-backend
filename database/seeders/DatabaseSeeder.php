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
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ClienteSeeder::class,
            CategoriaSeeder::class,
            ProductoSeeder::class,
            MetodoPagoSeeder::class,
            PedidoSeeder::class,
            PedidoItemSeeder::class,
            EstadoPedidoSeeder::class,
            EstadoPagoSeeder::class,
        //PagoSeeder::class,
        ]);
    }
}
