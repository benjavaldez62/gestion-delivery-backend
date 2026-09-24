<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            [
                'nombre' => 'Pizzas',
                'descripcion' => 'Deliciosas pizzas con variedad de sabores',
                'activo' => true,
            ],
            [
                'nombre' => 'Empanadas',
                'descripcion' => 'Empanadas caseras rellenas',
                'activo' => true,
            ],
            [
                'nombre' => 'Milanesas',
                'descripcion' => 'Milanesas de carne y pollo',
                'activo' => true,
            ],
            [
                'nombre' => 'Hamburguesas',
                'descripcion' => 'Hamburguesas gourmet y clásicas',
                'activo' => true,
            ],
            [
                'nombre' => 'Pastas',
                'descripcion' => 'Variedad de pastas frescas',
                'activo' => true,
            ],
            [
                'nombre' => 'Sandwiches',
                'descripcion' => 'Sandwiches variados y sabrosos',
                'activo' => true,
            ],
            [
                'nombre' => 'Ensaladas',
                'descripcion' => 'Ensaladas frescas y saludables',
                'activo' => true,
            ],
        ];

        foreach ($categorias as $categoria) {
            Categoria::create($categoria);
        }
    }
}
