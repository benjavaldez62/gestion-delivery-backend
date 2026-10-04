<?php

namespace Database\Seeders;

use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PedidoItemSeeder extends Seeder
{
    use WithoutModelEvents; //para desactivar temporalmente los eventos y observers de los modelos mientras corre este seeder. Buena práctica

    public function run(): void
    {
       
    }
}