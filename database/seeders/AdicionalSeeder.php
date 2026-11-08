<?php

namespace Database\Seeders;

use App\Models\Adicional;
use App\Models\Categoria;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdicionalSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adicionalesPorCategoria = [
            'Pizzas' => [
                ['nombre' => 'Mozzarella extra', 'descripcion' => 'Porción adicional de queso mozzarella', 'precio' => 1200],
                ['nombre' => 'Pepperoni extra', 'descripcion' => 'Porción adicional de pepperoni', 'precio' => 1500],
                ['nombre' => 'Aceitunas', 'descripcion' => 'Porción adicional de aceitunas', 'precio' => 500],
            ],
            'Hamburguesas' => [
                ['nombre' => 'Queso cheddar extra', 'descripcion' => 'Fetas adicionales de queso cheddar', 'precio' => 700],
                ['nombre' => 'Panceta crocante', 'descripcion' => 'Porción adicional de panceta crocante', 'precio' => 1200],
                ['nombre' => 'Huevo', 'descripcion' => 'Huevo adicional', 'precio' => 500],
            ],
            'Pastas' => [
                ['nombre' => 'Queso parmesano', 'descripcion' => 'Porción adicional de queso parmesano rallado', 'precio' => 800],
                ['nombre' => 'Salsa bolognesa', 'descripcion' => 'Porción adicional de salsa bolognesa', 'precio' => 1200],
                ['nombre' => 'Salsa crema', 'descripcion' => 'Porción adicional de salsa crema', 'precio' => 900],
            ],
            'Sandwiches' => [
                ['nombre' => 'Queso extra', 'descripcion' => 'Porción adicional de queso', 'precio' => 700],
                ['nombre' => 'Huevo', 'descripcion' => 'Huevo adicional', 'precio' => 500],
                ['nombre' => 'Panceta', 'descripcion' => 'Porción adicional de panceta', 'precio' => 1200],
            ],
        ];

        foreach ($adicionalesPorCategoria as $nombreCategoria => $adicionales) {
            $categoria = Categoria::where('nombre', $nombreCategoria)->firstOrFail();

            foreach ($adicionales as $datosAdicional) {
                Adicional::updateOrCreate(
                    [
                        'categoria_id' => $categoria->id,
                        'nombre' => $datosAdicional['nombre'],
                    ],
                    [
                        'descripcion' => $datosAdicional['descripcion'],
                        'precio' => $datosAdicional['precio'],
                        'activo' => true,
                    ]
                );
            }
        }
    }
}
