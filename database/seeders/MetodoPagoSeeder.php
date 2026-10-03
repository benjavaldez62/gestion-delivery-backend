<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MetodoPagoSeeder extends Seeder
{
   
    public function run(): void
    {
        MetodoPago::create([
            'nombre' => 'Efectivo',
            'descripcion' => 'Pago en efectivo al momento de la entrega',
            'activo' => 1
        ]);

        MetodoPago::create([
            'nombre' => 'Transferencia bancaria',
            'descripcion' => 'Pago mediante transferencia bancaria a la cuenta del restaurante',
            'activo' => 1
        
        ]);
    }
}
