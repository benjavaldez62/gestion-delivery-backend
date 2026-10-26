<?php

use App\Models\Adicional;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPedidoConFecha(string $fecha): Pedido
{
    $cliente = Cliente::create([
        'username' => 'cliente-'.$fecha,
        'telefono' => '123456789',
        'direccion' => 'Dirección '.$fecha,
    ]);

    $estado = EstadoPedido::create([
        'nombre' => 'Pendiente',
        'descripcion' => 'Pedido pendiente',
    ]);

    $pedido = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estado->id,
        'subtotal' => 1000,
        'monto_total' => 1000,
    ]);

    $pedido->forceFill([
        'created_at' => $fecha,
        'updated_at' => $fecha,
    ])->saveQuietly();

    return $pedido->fresh();
}

it('permite listar pedidos solo a usuarios autenticados con permisos de staff y ordenados por fecha mas reciente', function () {
    $adminRole = Role::create([
        'nombre' => 'Administrador',
        'descripcion' => 'Acceso completo',
    ]);

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'activo' => true,
    ]);

    $pedidoViejo = createPedidoConFecha(now()->subDays(2)->toDateTimeString());
    $pedidoNuevo = createPedidoConFecha(now()->toDateTimeString());

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/pedidos');

    $response->assertOk()
        ->assertJsonPath('message', 'Pedidos obtenidos correctamente.');

    $ids = collect($response->json('pedidos'))->pluck('id');

    expect($ids->first())->toBe($pedidoNuevo->id)
        ->and($ids->last())->toBe($pedidoViejo->id);
});

it('requiere autenticacion para consultar pedidos', function () {
    $this->getJson('/api/pedidos')->assertUnauthorized();
});

it('rechaza usuarios no autorizados para consultar pedidos', function () {
    $user = User::factory()->create([
        'role_id' => null,
        'activo' => true,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/pedidos')
        ->assertForbidden();
});

it('incluye el array de adicionales en items al consultar pedidos por index y show (SD-306)', function () {
    $adminRole = Role::create([
        'nombre' => 'Administrador',
        'descripcion' => 'Acceso completo',
    ]);

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'activo' => true,
    ]);

    $categoria = Categoria::create([
        'nombre' => 'Comidas Rápidas',
        'descripcion' => 'Categoría de prueba',
        'activo' => true,
    ]);

    $producto = Producto::create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Hamburguesa Especial',
        'precio' => 2500.00,
        'activo' => true,
    ]);

    $adicional1 = Adicional::create([
        'nombre' => 'Papas Extra',
        'descripcion' => 'Porción de papas',
        'precio' => 450.00,
        'activo' => true,
    ]);

    $adicional2 = Adicional::create([
        'nombre' => 'Bacon Extra',
        'descripcion' => 'Tiras crocantes',
        'precio' => 350.00,
        'activo' => true,
    ]);

    $pedido = createPedidoConFecha(now()->toDateTimeString());

    $item = PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'cantidad' => 2,
        'precio_unitario' => 2500.00,
        'subtotal' => 5000.00,
    ]);

    $item->adicionales()->attach([
        $adicional1->id => ['precio_adicional' => 450.00, 'created_at' => now(), 'updated_at' => now()],
        $adicional2->id => ['precio_adicional' => 350.00, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Test GET /api/pedidos
    $responseIndex = $this->actingAs($admin, 'sanctum')->getJson('/api/pedidos');
    $responseIndex->assertOk()
        ->assertJsonPath('pedidos.0.id', $pedido->id)
        ->assertJsonCount(1, 'pedidos.0.items')
        ->assertJsonPath('pedidos.0.items.0.id', $item->id)
        ->assertJsonPath('pedidos.0.items.0.producto.nombre', 'Hamburguesa Especial')
        ->assertJsonCount(2, 'pedidos.0.items.0.adicionales')
        ->assertJsonPath('pedidos.0.items.0.adicionales.0.nombre', 'Papas Extra')
        ->assertJsonPath('pedidos.0.items.0.adicionales.1.nombre', 'Bacon Extra');

    // Test GET /api/pedidos/{id}
    $responseShow = $this->actingAs($admin, 'sanctum')->getJson("/api/pedidos/{$pedido->id}");
    $responseShow->assertOk()
        ->assertJsonPath('pedido.id', $pedido->id)
        ->assertJsonCount(1, 'pedido.items')
        ->assertJsonPath('pedido.items.0.id', $item->id)
        ->assertJsonPath('pedido.items.0.producto.nombre', 'Hamburguesa Especial')
        ->assertJsonCount(2, 'pedido.items.0.adicionales')
        ->assertJsonPath('pedido.items.0.adicionales.0.nombre', 'Papas Extra')
        ->assertJsonPath('pedido.items.0.adicionales.1.nombre', 'Bacon Extra');
});

