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

function createTestContext(string $estadoNombre = 'Pendiente'): array
{
    $cliente = Cliente::create([
        'username' => 'cliente-test',
        'telefono' => '1122334455',
        'direccion' => 'Av. Corrientes 1234',
    ]);

    $estado = EstadoPedido::create([
        'nombre' => $estadoNombre,
        'descripcion' => 'Estado '.$estadoNombre,
    ]);

    $pedido = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estado->id,
        'subtotal' => 0,
        'monto_total' => 0,
    ]);

    $categoria = Categoria::create([
        'nombre' => 'Comidas Rápidas',
        'descripcion' => 'Categoría de prueba',
        'activo' => true,
    ]);

    $productoActivo = Producto::create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Hamburguesa Doble',
        'precio' => 2000.00,
        'activo' => true,
    ]);

    $productoInactivo = Producto::create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Pizza Descontinuada',
        'precio' => 3000.00,
        'activo' => false,
    ]);

    $user = User::factory()->create([
        'activo' => true,
    ]);

    return compact('cliente', 'estado', 'pedido', 'categoria', 'productoActivo', 'productoInactivo', 'user');
}

it('rechaza agregar un producto inactivo con codigo 422 y mensaje explicito', function () {
    $ctx = createTestContext();

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->postJson("/api/pedidos/{$ctx['pedido']->id}/items", [
            'producto_id' => $ctx['productoInactivo']->id,
            'cantidad' => 2,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['producto_id']);

    expect(PedidoItem::count())->toBe(0);
});

it('rechaza agregar un producto eliminado logicamente con codigo 422', function () {
    $ctx = createTestContext();
    $ctx['productoActivo']->delete(); // soft delete

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->postJson("/api/pedidos/{$ctx['pedido']->id}/items", [
            'producto_id' => $ctx['productoActivo']->id,
            'cantidad' => 1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['producto_id']);

    expect(PedidoItem::count())->toBe(0);
});

it('permite agregar un producto activo con codigo 201 y recalcula subtotales', function () {
    $ctx = createTestContext();

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->postJson("/api/pedidos/{$ctx['pedido']->id}/items", [
            'producto_id' => $ctx['productoActivo']->id,
            'cantidad' => 3,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Ítem agregado al pedido correctamente.')
        ->assertJsonPath('item.producto.id', $ctx['productoActivo']->id)
        ->assertJsonPath('item.cantidad', 3);

    $item = PedidoItem::first();
    expect($item)->not->toBeNull()
        ->and((float) $item->precio_unitario)->toEqual(2000.00)
        ->and((float) $item->subtotal)->toEqual(6000.00);

    expect((float) $ctx['pedido']->fresh()->monto_total)->toEqual(6000.00);
});

it('rechaza modificar la cantidad de un item cuyo producto fue desactivado posteriormente con codigo 422', function () {
    $ctx = createTestContext();

    // 1. Agregar producto activo normalmente
    $this->actingAs($ctx['user'], 'sanctum')
        ->postJson("/api/pedidos/{$ctx['pedido']->id}/items", [
            'producto_id' => $ctx['productoActivo']->id,
            'cantidad' => 1,
        ])
        ->assertCreated();

    $item = PedidoItem::first();

    // 2. Desactivar el producto posteriormente
    $ctx['productoActivo']->update(['activo' => false]);

    // 3. Intentar modificar cantidad del ítem
    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->putJson("/api/pedido-items/{$item->id}", [
            'cantidad' => 4,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['producto_id']);

    // Verificar que la cantidad no cambió
    expect($item->fresh()->cantidad)->toBe(1);
});

it('permite modificar la cantidad de un item si el producto permanece activo', function () {
    $ctx = createTestContext();

    $this->actingAs($ctx['user'], 'sanctum')
        ->postJson("/api/pedidos/{$ctx['pedido']->id}/items", [
            'producto_id' => $ctx['productoActivo']->id,
            'cantidad' => 2,
        ])
        ->assertCreated();

    $item = PedidoItem::first();

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->putJson("/api/pedido-items/{$item->id}", [
            'cantidad' => 5,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Ítem actualizado correctamente.');

    expect($item->fresh()->cantidad)->toBe(5)
        ->and((float) $item->fresh()->subtotal)->toEqual(10000.00)
        ->and((float) $ctx['pedido']->fresh()->monto_total)->toEqual(10000.00);
});

it('rechaza agregar items si el pedido esta en un estado no editable con codigo 400', function () {
    $ctx = createTestContext('Entregado');

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->postJson("/api/pedidos/{$ctx['pedido']->id}/items", [
            'producto_id' => $ctx['productoActivo']->id,
            'cantidad' => 1,
        ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'No se pueden agregar ítems a un pedido que ya no está en estado editable.');
});

it('requiere autenticacion sanctum para agregar items a un pedido con codigo 401', function () {
    $ctx = createTestContext();

    $response = $this->postJson("/api/pedidos/{$ctx['pedido']->id}/items", [
        'producto_id' => $ctx['productoActivo']->id,
        'cantidad' => 1,
    ]);

    $response->assertUnauthorized();
});

it('permite consultar un item de pedido exponiendo id y resolviendo relacion adicionales', function () {
    $ctx = createTestContext();

    $item = PedidoItem::create([
        'pedido_id' => $ctx['pedido']->id,
        'producto_id' => $ctx['productoActivo']->id,
        'cantidad' => 2,
        'precio_unitario' => 2000.00,
        'subtotal' => 4000.00,
    ]);

    $adicional = Adicional::create([
        'nombre' => 'Cheddar Extra',
        'descripcion' => 'Queso cheddar adicional',
        'precio' => 350.00,
        'activo' => true,
    ]);

    $item->adicionales()->attach($adicional->id, [
        'precio_adicional' => 350.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->getJson("/api/pedido-items/{$item->id}");

    $response->assertOk()
        ->assertJsonPath('item.id', $item->id)
        ->assertJsonPath('item.cantidad', 2)
        ->assertJsonPath('item.producto.id', $ctx['productoActivo']->id)
        ->assertJsonCount(1, 'item.adicionales')
        ->assertJsonPath('item.adicionales.0.id', $adicional->id)
        ->assertJsonPath('item.adicionales.0.nombre', 'Cheddar Extra');
});

