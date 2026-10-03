<?php

namespace Database\Seeders;

use App\Models\EstadoPagos;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstadoPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EstadoPagos::create([
            'nombre' => 'Pendiente',
            'descripcion' => 'El pago está pendiente de ser realizado',
        ]);

        EstadoPagos::create([
            'nombre' => 'Pagado',
            'descripcion' => 'El pago ha sido completado exitosamente',
        ]);

        EstadoPagos::create([
            'nombre' => 'Fallido',
            'descripcion' => 'El pago ha fallado y no se ha completado',
        ]);
    }
}
