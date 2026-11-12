<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Seeder;

class PedidoSeeder extends Seeder
{
    /**
     * Requiere los seeders de roles, usuarios, clientes, métodos de pago y estados de pedido.
     */
    public function run(): void
    {
        $costoEnvio = 1500;
        $efectivo = MetodoPago::where('nombre', 'Efectivo')->first();
        $transferencia = MetodoPago::where('nombre', 'Transferencia bancaria')->first();

        $pedidos = [
            [
                'cliente' => 'juan-martin96',
                'estado' => 'Pendiente',
                'cocinero' => null,
                'repartidor' => null,
                'subtotal' => 14500,
                'metodo_pago_id' => $transferencia?->id,
            ],
            [
                'cliente' => 'gusmachax779',
                'estado' => 'En preparación',
                'cocinero' => 'felipemartinez@test.com',
                'repartidor' => null,
                'subtotal' => 11500,
                'metodo_pago_id' => $efectivo?->id,
            ],
            [
                'cliente' => 'cata_lopez001',
                'estado' => 'En camino',
                'cocinero' => 'felipemartinez@test.com',
                'repartidor' => 'sebastiangomez@test.com',
                'subtotal' => 24000,
                'metodo_pago_id' => $transferencia?->id,
            ],
            [
                'cliente' => 'alinablue21',
                'estado' => 'Entregado',
                'cocinero' => 'felipemartinez@test.com',
                'repartidor' => 'sebastiangomez@test.com',
                'subtotal' => 9500,
                'metodo_pago_id' => $efectivo?->id,
            ],
            [
                'cliente' => 'juan-martin96',
                'estado' => 'Cancelado',
                'cocinero' => null,
                'repartidor' => null,
                'subtotal' => 13500,
                'metodo_pago_id' => $efectivo?->id,
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
                    'metodo_pago_id' => $datos['metodo_pago_id'],
                ]
            );
        }
    }
}
