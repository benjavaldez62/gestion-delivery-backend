<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('PagoSeeder solo puede crear pagos ficticios en el entorno de pruebas.');
        }

        $cliente = Cliente::create(['username' => 'pago_test_'.Str::lower(Str::random(12))]);
        $estado = EstadoPedido::where('nombre', 'En camino')->firstOrFail();
        $metodo = MetodoPago::where('nombre', 'Efectivo')->firstOrFail();
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'estado_pedidos_id' => $estado->id,
            'metodo_pago_id' => $metodo->id,
            'monto_total' => 1000,
        ]);
        Pago::factory()->create(['pedido_id' => $pedido->id]);
    }
}
