<?php

use App\Models\Adicional;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCategoriasAndAdicionalesContext(): array
{
    $catBurgers = Categoria::create([
        'nombre' => 'Hamburguesas',
        'descripcion' => 'Hamburguesas artesanales',
        'activo' => true,
    ]);

    $catPizzas = Categoria::create([
        'nombre' => 'Pizzas',
        'descripcion' => 'Pizzas al horno',
        'activo' => true,
    ]);

    $adicBurger = Adicional::create([
        'categoria_id' => $catBurgers->id,
        'nombre' => 'Doble Medallón',
        'descripcion' => 'Carne 120g extra',
        'precio' => 1500.00,
        'activo' => true,
    ]);

    $adicGlobal = Adicional::create([
        'categoria_id' => null,
        'nombre' => 'Salsa Especial',
        'descripcion' => 'Aderezo de la casa',
        'precio' => 400.00,
        'activo' => true,
    ]);

    $adicPizza = Adicional::create([
        'categoria_id' => $catPizzas->id,
        'nombre' => 'Borde Relleno de Queso',
        'descripcion' => 'Muzzarella en el borde',
        'precio' => 1800.00,
        'activo' => true,
    ]);

    $adicInactivo = Adicional::create([
        'categoria_id' => $catBurgers->id,
        'nombre' => 'Cebolla Crispy',
        'descripcion' => 'Temporada finalizada',
        'precio' => 600.00,
        'activo' => false,
    ]);

    $adminUser = User::factory()->create([
        'activo' => true,
    ]);

    return compact('catBurgers', 'catPizzas', 'adicBurger', 'adicGlobal', 'adicPizza', 'adicInactivo', 'adminUser');
}

it('GET /api/categorias/{categoria}/adicionales retorna adicionales especificos y globales activos', function () {
    $ctx = createCategoriasAndAdicionalesContext();

    $response = $this->getJson("/api/categorias/{$ctx['catBurgers']->id}/adicionales");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'categoria' => ['id', 'nombre'],
            'adicionales' => [
                '*' => ['id', 'categoria_id', 'nombre', 'descripcion', 'precio', 'activo'],
            ],
        ]);

    $ids = collect($response->json('adicionales'))->pluck('id');

    expect($ids)->toContain($ctx['adicBurger']->id);
    expect($ids)->toContain($ctx['adicGlobal']->id);
    expect($ids)->not->toContain($ctx['adicPizza']->id);
    expect($ids)->not->toContain($ctx['adicInactivo']->id);
});

it('GET /api/categorias/{categoria}/adicionales retorna 404 si la categoria no existe', function () {
    $response = $this->getJson('/api/categorias/99999/adicionales');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Categoría no encontrada.',
        ]);
});

it('GET /api/adicionales filtra correctamente con query param categoria_id', function () {
    $ctx = createCategoriasAndAdicionalesContext();

    $response = $this->getJson("/api/adicionales?categoria_id={$ctx['catPizzas']->id}");

    $response->assertStatus(200);

    $ids = collect($response->json('adicionales'))->pluck('id');

    expect($ids)->toContain($ctx['adicPizza']->id);
    expect($ids)->toContain($ctx['adicGlobal']->id);
    expect($ids)->not->toContain($ctx['adicBurger']->id);
});

it('permite crear y actualizar un adicional asociando categoria_id', function () {
    $ctx = createCategoriasAndAdicionalesContext();

    $responseStore = $this->actingAs($ctx['adminUser'], 'sanctum')
        ->postJson('/api/adicionales', [
            'categoria_id' => $ctx['catBurgers']->id,
            'nombre' => 'Bacon Extra Ahumado',
            'descripcion' => 'Panceta crocante',
            'precio' => 1200.00,
            'activo' => true,
        ]);

    $responseStore->assertStatus(201);
    $adicionalId = $responseStore->json('adicional.id');
    expect($responseStore->json('adicional.categoria_id'))->toBe($ctx['catBurgers']->id);

    $responseUpdate = $this->actingAs($ctx['adminUser'], 'sanctum')
        ->putJson("/api/adicionales/{$adicionalId}", [
            'categoria_id' => $ctx['catPizzas']->id,
            'nombre' => 'Bacon Extra Ahumado',
            'precio' => 1350.00,
            'activo' => true,
        ]);

    $responseUpdate->assertStatus(200);
    expect($responseUpdate->json('adicional.categoria_id'))->toBe($ctx['catPizzas']->id);
});

it('expone categoria_id del producto en PedidoItemResource', function () {
    $ctx = createCategoriasAndAdicionalesContext();

    $cliente = Cliente::create([
        'username' => 'cliente-resource-test',
        'telefono' => '1144556677',
        'direccion' => 'Calle 10 500',
    ]);

    $estado = EstadoPedido::create([
        'nombre' => 'Pendiente',
        'descripcion' => 'En espera',
    ]);

    $pedido = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estado->id,
        'subtotal' => 2000.00,
        'monto_total' => 2000.00,
    ]);

    $producto = Producto::create([
        'categoria_id' => $ctx['catBurgers']->id,
        'nombre' => 'Burger Cheese',
        'precio' => 2000.00,
        'activo' => true,
    ]);

    $item = PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'precio_unitario' => 2000.00,
        'subtotal' => 2000.00,
    ]);

    $response = $this->actingAs($ctx['adminUser'], 'sanctum')
        ->getJson("/api/pedido-items/{$item->id}");

    $response->assertStatus(200)
        ->assertJsonPath('item.producto.categoria_id', $ctx['catBurgers']->id);
});
