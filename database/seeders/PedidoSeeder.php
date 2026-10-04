<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Seeder;

class PedidoSeeder extends Seeder
{
    /**
     * Requiere los seeders de roles, usuarios, clientes y estados de pedido.
     */
    public function run(): void
    {
        $costoEnvio = 1500;

        $pedidos = [
            [
                'cliente' => 'juan-martin96',
                'estado' => 'Pendiente',
                'cocinero' => null,
                'repartidor' => null,
                'subtotal' => 14500,
            ],
            [
                'cliente' => 'gusmachax779',
                'estado' => 'En preparación',
                'cocinero' => 'felipemartinez@test.com',
                'repartidor' => null,
                'subtotal' => 11500,
            ],
            [
                'cliente' => 'cata_lopez001',
                'estado' => 'En camino',
                'cocinero' => 'felipemartinez@test.com',
                'repartidor' => 'sebastiangomez@test.com',
                'subtotal' => 24000,
            ],
            [
                'cliente' => 'alinablue21',
                'estado' => 'Entregado',
                'cocinero' => 'felipemartinez@test.com',
                'repartidor' => 'sebastiangomez@test.com',
                'subtotal' => 9500,
            ],
            [
                'cliente' => 'juan-martin96',
                'estado' => 'Cancelado',
                'cocinero' => null,
                'repartidor' => null,
                'subtotal' => 13500,
            ],
        ];

        foreach ($pedidos as $datos) {
            $cliente = Cliente::where('username', $datos['cliente'])->first();
            $estado = EstadoPedido::where('nombre', $datos['estado'])->first();

            if (! $cliente || ! $estado) {
                continue;
            }

            $cocinero = $datos['cocinero']
                ? User::where('email', $datos['cocinero'])->first()
                : null;
            $repartidor = $datos['repartidor']
                ? User::where('email', $datos['repartidor'])->first()
                : null;

            Pedido::updateOrCreate(
                [
                    'cliente_id' => $cliente->id,
                    'estado_pedidos_id' => $estado->id,
                    'subtotal' => $datos['subtotal'],
                ],
                [
                    'monto_total' => $datos['subtotal'] + $costoEnvio,
                    'cocinero_id' => $cocinero?->id,
                    'repartidor_id' => $repartidor?->id,
                ]
            );
        }
    }
}
