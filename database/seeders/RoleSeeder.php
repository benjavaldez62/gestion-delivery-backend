<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'Acceso completo al sistema',
        ]);

        Role::create([
            'nombre' => 'Vendedor',
            'descripcion' => 'Gestión de ventas y clientes',
        ]);

        Role::create([
            'nombre' => 'Supervisor',
            'descripcion' => 'Supervisión de ventas y stock',
        ]);
    }
}
