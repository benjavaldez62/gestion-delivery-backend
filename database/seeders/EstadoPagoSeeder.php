<?php

namespace Database\Seeders;

use App\Models\EstadoPagos;
use Illuminate\Database\Seeder;

class EstadoPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EstadoPagos::firstOrCreate(['nombre' => 'Pendiente'], [
            'descripcion' => 'El pago está pendiente de ser realizado',
        ]);

        EstadoPagos::firstOrCreate(['nombre' => 'Pagado'], [
            'descripcion' => 'El pago ha sido completado exitosamente',
        ]);

        EstadoPagos::firstOrCreate(['nombre' => 'Fallido'], [
            'descripcion' => 'El pago ha fallado y no se ha completado',
        ]);
    }
}
