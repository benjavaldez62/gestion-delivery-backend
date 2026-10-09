<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class PedidoItemSeeder extends Seeder
{

    public function run(): void
    {
        $pedidos = [
            [
                'cliente' => 'juan-martin96',
                'estado' => 'Pendiente',
                'subtotal' => 14500,
                'items' => [
                    ['producto' => 'Pizza Margherita', 'cantidad' => 1],
                    ['producto' => 'Empanada de Carne', 'cantidad' => 2],
                ],
            ],
            [
                'cliente' => 'gusmachax779',
                'estado' => 'En preparación',
                'subtotal' => 11500,
                'items' => [
                    ['producto' => 'Pizza Pepperoni', 'cantidad' => 1],
                ],
            ],
            [
                'cliente' => 'cata_lopez001',
                'estado' => 'En camino',
                'subtotal' => 24000,
                'items' => [
                    ['producto' => 'Milanesa Napolitana', 'cantidad' => 1],
                    ['producto' => 'Ravioles de Queso', 'cantidad' => 1],
                    ['producto' => 'Ensalada César', 'cantidad' => 1],
                ],
            ],
            [
                'cliente' => 'alinablue21',
                'estado' => 'Entregado',
                'subtotal' => 9500,
                'items' => [
                    ['producto' => 'Milanesa de Pollo', 'cantidad' => 1],
                    ['producto' => 'Empanada de Pollo', 'cantidad' => 1],
                ],
            ],
            [
                'cliente' => 'juan-martin96',
                'estado' => 'Cancelado',
                'subtotal' => 13500,
                'items' => [
                    ['producto' => 'Hamburguesa Doble', 'cantidad' => 1],
                    ['producto' => 'Ensalada César', 'cantidad' => 1],
                ],
            ],
        ];

        foreach ($pedidos as $datos) {
            $cliente = Cliente::where('username', $datos['cliente'])->first();
            $estado = EstadoPedido::where('nombre', $datos['estado'])->first();

            if (! $cliente || ! $estado) {
                continue;
            }

            $pedido = Pedido::where('cliente_id', $cliente->id)
                ->where('estado_pedidos_id', $estado->id)
                ->where('subtotal', $datos['subtotal'])
                ->first();

            if (! $pedido) {
                continue;
            }

            foreach ($datos['items'] as $item) {
                $producto = Producto::where('nombre', $item['producto'])->first();

                if (! $producto) {
                    continue;
                }

                PedidoItem::updateOrCreate(
                    [
                        'pedido_id' => $pedido->id,
                        'producto_id' => $producto->id,
                    ],
                    [
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $producto->precio,
                        'subtotal' => $producto->precio * $item['cantidad'],
                    ]
                );
            }
        }
    }
}