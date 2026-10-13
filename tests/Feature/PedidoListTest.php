<?php

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
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
