<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener las categorías
        $pizzas = Categoria::where('nombre', 'Pizzas')->first();
        $empanadas = Categoria::where('nombre', 'Empanadas')->first();
        $milanesas = Categoria::where('nombre', 'Milanesas')->first();
        $hamburguesas = Categoria::where('nombre', 'Hamburguesas')->first();
        $pastas = Categoria::where('nombre', 'Pastas')->first();
        $sandwiches = Categoria::where('nombre', 'Sandwiches')->first();
        $ensaladas = Categoria::where('nombre', 'Ensaladas')->first();

        // Pizzas
        $productoPizzas = [
            [
                'categoria_id' => $pizzas->id,
                'nombre' => 'Pizza Margherita',
                'descripcion' => 'Tomate, mozzarella, basílico',
                'precio' => 9500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $pizzas->id,
                'nombre' => 'Pizza Pepperoni',
                'descripcion' => 'Tomate, mozzarella, pepperoni',
                'precio' => 11500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $pizzas->id,
                'nombre' => 'Pizza Cuatro Quesos',
                'descripcion' => 'Mozzarella, cheddar, azul, parmesano',
                'precio' => 13500.00,
                'activo' => true,
            ],
        ];

        // Empanadas
        $productoEmpanadas = [
            [
                'categoria_id' => $empanadas->id,
                'nombre' => 'Empanada de Carne',
                'descripcion' => 'Empanada rellena de carne molida',
                'precio' => 2500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $empanadas->id,
                'nombre' => 'Empanada de Pollo',
                'descripcion' => 'Empanada rellena de pollo desmenuzado',
                'precio' => 2500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $empanadas->id,
                'nombre' => 'Empanada de Jamón y Queso',
                'descripcion' => 'Empanada rellena de jamón y queso',
                'precio' => 3000.00,
                'activo' => true,
            ],
        ];

        // Milanesas
        $productoMilanesas = [
            [
                'categoria_id' => $milanesas->id,
                'nombre' => 'Milanesa de Carne',
                'descripcion' => 'Milanesa de carne de res',
                'precio' => 8500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $milanesas->id,
                'nombre' => 'Milanesa de Pollo',
                'descripcion' => 'Milanesa de pechuga de pollo',
                'precio' => 7000.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $milanesas->id,
                'nombre' => 'Milanesa Napolitana',
                'descripcion' => 'Milanesa con queso y tomate',
                'precio' => 9500.00,
                'activo' => true,
            ],
        ];

        // Hamburguesas
        $productoHamburguesas = [
            [
                'categoria_id' => $hamburguesas->id,
                'nombre' => 'Hamburguesa Clásica',
                'descripcion' => 'Pan, carne, lechuga, tomate, cebolla',
                'precio' => 5500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $hamburguesas->id,
                'nombre' => 'Hamburguesa Doble',
                'descripcion' => 'Dos carnes, queso doble, lechuga, tomate',
                'precio' => 7500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $hamburguesas->id,
                'nombre' => 'Hamburguesa Bacon',
                'descripcion' => 'Carne, bacon, queso cheddar, cebolla caramelizada',
                'precio' => 8000.00,
                'activo' => true,
            ],
        ];

        // Pastas
        $productoPasstas = [
            [
                'categoria_id' => $pastas->id,
                'nombre' => 'Tallarín a la Bolognesa',
                'descripcion' => 'Tallarín con salsa bolognesa casera',
                'precio' => 7500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $pastas->id,
                'nombre' => 'Ravioles de Queso',
                'descripcion' => 'Ravioles rellenos de queso ricota',
                'precio' => 8500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $pastas->id,
                'nombre' => 'Penne a la Carbonara',
                'descripcion' => 'Penne con salsa de huevo y bacon',
                'precio' => 9000.00,
                'activo' => true,
            ],
        ];

        // Sandwiches
        $productoSandwiches = [
            [
                'categoria_id' => $sandwiches->id,
                'nombre' => 'Sandwich de Jamón y Queso',
                'descripcion' => 'Jamón, queso, pan de molde tostado',
                'precio' => 3500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $sandwiches->id,
                'nombre' => 'Sandwich de Milanesa',
                'descripcion' => 'Milanesa, lechuga, tomate, mayonesa',
                'precio' => 4500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $sandwiches->id,
                'nombre' => 'Sandwich Completo',
                'descripcion' => 'Jamón, queso, tomate, lechuga, huevo',
                'precio' => 5500.00,
                'activo' => true,
            ],
        ];

        // Ensaladas
        $productoEnsaladas = [
            [
                'categoria_id' => $ensaladas->id,
                'nombre' => 'Ensalada César',
                'descripcion' => 'Lechuga, pollo, crutones, salsa césar',
                'precio' => 6000.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $ensaladas->id,
                'nombre' => 'Ensalada Griega',
                'descripcion' => 'Tomate, pepino, queso feta, aceitunas',
                'precio' => 5500.00,
                'activo' => true,
            ],
            [
                'categoria_id' => $ensaladas->id,
                'nombre' => 'Ensalada Mixta',
                'descripcion' => 'Lechuga, tomate, pepino, zanahoria, cebolla',
                'precio' => 4500.00,
                'activo' => true,
            ],
        ];

        // Crear todos los productos
        foreach (array_merge(
            $productoPizzas,
            $productoEmpanadas,
            $productoMilanesas,
            $productoHamburguesas,
            $productoPasstas,
            $productoSandwiches,
            $productoEnsaladas
        ) as $producto) {
            Producto::create($producto);
        }
    }
}
