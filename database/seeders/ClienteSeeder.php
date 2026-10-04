<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Creamos unos clientes de prueba
        Cliente::create([
            'username' => 'juan-martin96',
            'telefono' => '3452213786',
            'direccion' => 'Liniers 123'
        ]);
        Cliente::create([
            'username' => 'gusmachax779',
            'telefono' => '3459908716',
            'direccion' => 'Catamarca 76'
        ]);
        Cliente::create([
            'username' => 'cata_lopez001',
            'telefono' => '3459876624',
            'direccion' => 'Urquiza 886'
        ]);
        Cliente::create([
            'username' => 'alinablue21',
            'telefono' => '3451123246',
            'direccion' => 'P. Echagüe 1143'
        ]);
    }
}
