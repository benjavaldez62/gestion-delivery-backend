<?php

use App\Models\Adicional;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('el seeder crea adicionales solo para las categorías configuradas y puede ejecutarse más de una vez', function () {
    $this->seed(CategoriaSeeder::class);
    $this->seed(AdicionalSeeder::class);
    $this->seed(AdicionalSeeder::class);

    expect(Adicional::count())->toBe(12)
        ->and(Adicional::whereHas('categoria', fn ($query) => $query->whereIn('nombre', [
            'Pizzas',
            'Hamburguesas',
            'Pastas',
            'Sandwiches',
        ]))->count())->toBe(12)
        ->and(Adicional::whereHas('categoria', fn ($query) => $query->whereIn('nombre', [
            'Empanadas',
            'Milanesas',
            'Ensaladas',
        ]))->count())->toBe(0);
});
