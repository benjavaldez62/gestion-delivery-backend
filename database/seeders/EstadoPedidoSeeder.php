<?php

namespace Database\Seeders;

use App\Models\EstadoPedido;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstadoPedidoSeeder extends Seeder
{
    public function run(): void
    {
        EstadoPedido::create([
            'nombre' => 'Pendiente',
            'descripcion' => 'El pedido fue creado y está pendiente de confirmación',
        ]);

        EstadoPedido::create([
            'nombre' => 'En preparación',
            'descripcion' => 'El pedido está siendo preparado por el cocinero',
        ]);

        EstadoPedido::create([
            'nombre' => 'En camino',
            'descripcion' => 'El pedido fue despachado y está en camino con el repartidor',
        ]);

        EstadoPedido::create([
            'nombre' => 'Entregado',
            'descripcion' => 'El pedido fue entregado al cliente exitosamente',
        ]);

        EstadoPedido::create([
            'nombre' => 'Cancelado',
            'descripcion' => 'El pedido fue cancelado',
        ]);
    }
}