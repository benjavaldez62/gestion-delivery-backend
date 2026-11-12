<?php

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearPedidoParaAsignacion(): Pedido
{
    $cliente = Cliente::create([
        'username' => 'cliente-asignacion',
        'telefono' => '123456789',
        'direccion' => 'Calle 123',
    ]);

    $estado = EstadoPedido::create([
        'nombre' => 'Sin Asignar',
        'descripcion' => 'Pedido pendiente de asignación',
    ]);

    return Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estado->id,
        'subtotal' => 1000,
        'monto_total' => 1000,
    ]);
}

it('permite al administrador asignar personal activo con el rol correspondiente', function () {
    $adminRole = Role::create(['nombre' => 'Administrador']);
    $cocineroRole = Role::create(['nombre' => 'Cocinero']);
    $repartidorRole = Role::create(['nombre' => 'Repartidor']);
    $estadoPendiente = EstadoPedido::create([
        'nombre' => 'Pendiente',
        'descripcion' => 'Pedido asignado y pendiente de confirmación',
    ]);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
    $cocinero = User::factory()->create(['role_id' => $cocineroRole->id, 'activo' => true]);
    $repartidor = User::factory()->create(['role_id' => $repartidorRole->id, 'activo' => true]);
    $pedido = crearPedidoParaAsignacion();

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/pedidos/{$pedido->id}/asignaciones", [
            'cocinero_id' => $cocinero->id,
            'repartidor_id' => $repartidor->id,
        ])
        ->assertOk()
        ->assertJsonPath('pedido.cocinero.id', $cocinero->id)
        ->assertJsonPath('pedido.repartidor.id', $repartidor->id)
        ->assertJsonPath('pedido.estado.id', $estadoPendiente->id)
        ->assertJsonPath('pedido.estado.nombre', 'Pendiente');

    expect($pedido->fresh()->cocinero_id)->toBe($cocinero->id)
        ->and($pedido->fresh()->repartidor_id)->toBe($repartidor->id)
        ->and($pedido->fresh()->estado_pedidos_id)->toBe($estadoPendiente->id);
});

it('avanza a Pendiente cuando se asigna un solo integrante del personal', function () {
    $adminRole = Role::create(['nombre' => 'Administrador']);
    $cocineroRole = Role::create(['nombre' => 'Cocinero']);
    $repartidorRole = Role::create(['nombre' => 'Repartidor']);
    $estadoPendiente = EstadoPedido::create([
        'nombre' => 'Pendiente',
        'descripcion' => 'Pedido asignado y pendiente de confirmación',
    ]);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
    $cocinero = User::factory()->create(['role_id' => $cocineroRole->id, 'activo' => true]);
    $repartidor = User::factory()->create(['role_id' => $repartidorRole->id, 'activo' => true]);
    $pedido = crearPedidoParaAsignacion();

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/pedidos/{$pedido->id}/asignaciones", [
            'cocinero_id' => $cocinero->id,
        ])
        ->assertOk()
        ->assertJsonPath('pedido.cocinero.id', $cocinero->id)
        ->assertJsonPath('pedido.estado.id', $estadoPendiente->id)
        ->assertJsonPath('pedido.estado.nombre', 'Pendiente');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/pedidos/{$pedido->id}/asignaciones", [
            'repartidor_id' => $repartidor->id,
        ])
        ->assertOk()
        ->assertJsonPath('pedido.cocinero.id', $cocinero->id)
        ->assertJsonPath('pedido.repartidor.id', $repartidor->id)
        ->assertJsonPath('pedido.estado.id', $estadoPendiente->id)
        ->assertJsonPath('pedido.estado.nombre', 'Pendiente');

    expect($pedido->fresh()->estado_pedidos_id)->toBe($estadoPendiente->id)
        ->and($pedido->fresh()->cocinero_id)->toBe($cocinero->id)
        ->and($pedido->fresh()->repartidor_id)->toBe($repartidor->id);
});

it('rechaza la asignación a usuarios que están inactivos o tienen otro rol', function () {
    $adminRole = Role::create(['nombre' => 'Administrador']);
    $cocineroRole = Role::create(['nombre' => 'Cocinero']);
    $repartidorRole = Role::create(['nombre' => 'Repartidor']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
    $inactivo = User::factory()->create(['role_id' => $cocineroRole->id, 'activo' => false]);
    $usuarioConOtroRol = User::factory()->create(['role_id' => $repartidorRole->id, 'activo' => true]);
    $pedido = crearPedidoParaAsignacion();

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/pedidos/{$pedido->id}/asignaciones", ['cocinero_id' => $inactivo->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cocinero_id');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/pedidos/{$pedido->id}/asignaciones", ['cocinero_id' => $usuarioConOtroRol->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cocinero_id');
});

it('restringe la asignación de personal a los administradores', function () {
    $cocineroRole = Role::create(['nombre' => 'Cocinero']);
    $cocinero = User::factory()->create(['role_id' => $cocineroRole->id, 'activo' => true]);
    $pedido = crearPedidoParaAsignacion();

    $this->actingAs($cocinero, 'sanctum')
        ->putJson("/api/pedidos/{$pedido->id}/asignaciones", ['cocinero_id' => $cocinero->id])
        ->assertForbidden();
});
