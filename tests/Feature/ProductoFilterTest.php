<?php

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET /api/productos lista todos los productos activos cuando no se envian filtros', function () {
    $categoria = Categoria::create(['nombre' => 'Pizzas', 'activo' => true]);
    Producto::create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Pizza Napolitana',
        'descripcion' => 'Salsa de tomate, muzzarella y rodajas de tomate',
        'precio' => 4500,
        'activo' => true,
    ]);
    Producto::create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Pizza Fugazzeta',
        'descripcion' => 'Muzzarella y cebolla',
        'precio' => 4800,
        'activo' => true,
    ]);

    $response = $this->getJson('/api/productos');

    $response->assertOk()
        ->assertJsonCount(2, 'productos')
        ->assertJsonPath('message', 'Productos obtenidos correctamente.');
});

test('GET /api/productos filtra correctamente por categoria_id (SD-54)', function () {
    $catPizzas = Categoria::create(['nombre' => 'Pizzas', 'activo' => true]);
    $catBebidas = Categoria::create(['nombre' => 'Bebidas', 'activo' => true]);

    $p1 = Producto::create([
        'categoria_id' => $catPizzas->id,
        'nombre' => 'Pizza Muzzarella',
        'descripcion' => 'Clasica con aceitunas',
        'precio' => 4000,
        'activo' => true,
    ]);

    $p2 = Producto::create([
        'categoria_id' => $catBebidas->id,
        'nombre' => 'Gaseosa Cola 1.5L',
        'descripcion' => 'Bebida sin alcohol',
        'precio' => 1500,
        'activo' => true,
    ]);

    $response = $this->getJson("/api/productos?categoria_id={$catPizzas->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.id', $p1->id)
        ->assertJsonPath('productos.0.nombre', 'Pizza Muzzarella');
});

test('GET /api/productos busca por nombre de forma insensible a mayusculas y minusculas (SD-54)', function () {
    $cat = Categoria::create(['nombre' => 'Hamburguesas', 'activo' => true]);
    $p = Producto::create([
        'categoria_id' => $cat->id,
        'nombre' => 'Hamburguesa Doble Cuarto',
        'descripcion' => 'Doble medallon con cheddar',
        'precio' => 5200,
        'activo' => true,
    ]);

    // Busqueda en mayusculas
    $responseUpper = $this->getJson('/api/productos?q=DOBLE');
    $responseUpper->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.id', $p->id);

    // Busqueda en minusculas
    $responseLower = $this->getJson('/api/productos?q=cuarto');
    $responseLower->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.id', $p->id);
});

test('GET /api/productos busca por descripcion (SD-54)', function () {
    $cat = Categoria::create(['nombre' => 'Empanadas', 'activo' => true]);
    $p = Producto::create([
        'categoria_id' => $cat->id,
        'nombre' => 'Empanada Criolla',
        'descripcion' => 'Carne cortada a cuchillo con huevo y verdeo',
        'precio' => 900,
        'activo' => true,
    ]);

    $response = $this->getJson('/api/productos?q=cuchillo');

    $response->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.id', $p->id)
        ->assertJsonPath('productos.0.nombre', 'Empanada Criolla');
});

test('GET /api/productos filtra por categoria y termino de busqueda combinados (SD-54)', function () {
    $catPizzas = Categoria::create(['nombre' => 'Pizzas', 'activo' => true]);
    $catPastas = Categoria::create(['nombre' => 'Pastas', 'activo' => true]);

    Producto::create([
        'categoria_id' => $catPizzas->id,
        'nombre' => 'Pizza Especial de Jamon',
        'descripcion' => 'Con jamon cocido y morrones',
        'precio' => 5000,
        'activo' => true,
    ]);

    Producto::create([
        'categoria_id' => $catPastas->id,
        'nombre' => 'Sorrentinos de Jamon y Queso',
        'descripcion' => 'Con salsa a eleccion',
        'precio' => 4800,
        'activo' => true,
    ]);

    $response = $this->getJson("/api/productos?categoria_id={$catPizzas->id}&q=jamon");

    $response->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.nombre', 'Pizza Especial de Jamon');
});

test('GET /api/productos retorna HTTP 200 con array vacio si no hay coincidencias con filtros (SD-54)', function () {
    $cat = Categoria::create(['nombre' => 'Postres', 'activo' => true]);
    Producto::create([
        'categoria_id' => $cat->id,
        'nombre' => 'Flan Casero',
        'descripcion' => 'Con dulce de leche y crema',
        'precio' => 2000,
        'activo' => true,
    ]);

    $response = $this->getJson('/api/productos?q=inexistente_total_12345');

    $response->assertOk()
        ->assertJsonCount(0, 'productos')
        ->assertJsonPath('productos', []);
});

test('GET /api/productos retorna HTTP 404 cuando la base esta vacia y no se enviaron filtros', function () {
    $response = $this->getJson('/api/productos');

    $response->assertNotFound()
        ->assertJsonPath('message', 'No hay productos disponibles para mostrar.');
});

