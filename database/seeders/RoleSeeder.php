<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'Acceso completo al sistema',
        ]);

        Role::create([
            'nombre' => 'Cocinero',
            'descripcion' => 'Preparación de alimentos',
        ]);

        Role::create([
            'nombre' => 'Repartidor',
            'descripcion' => 'Entrega de pedidos a los clientes',
        ]);
    }
}
