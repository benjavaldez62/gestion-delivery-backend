<?php

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::create([
        'nombre' => 'Administrador',
        'descripcion' => 'Rol administrativo',
    ]);

    $this->admin = User::factory()->create([
        'name' => 'Admin Test',
        'email' => 'admin.test@laposta.com',
        'role_id' => $this->adminRole->id,
        'activo' => true,
    ]);
});

test('GET /api/estado-pedidos requiere autenticacion sanctum', function () {
    $this->getJson('/api/estado-pedidos')->assertUnauthorized();
});

test('GET /api/estado-pedidos lista todos los estados activos', function () {
    EstadoPedido::create(['nombre' => 'Pendiente', 'descripcion' => 'Pedido recien creado']);
    EstadoPedido::create(['nombre' => 'En Preparacion', 'descripcion' => 'Cocinando']);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/estado-pedidos');

    $response->assertOk()
        ->assertJsonPath('message', 'Estados de pedidos obtenidos exitosamente.')
        ->assertJsonCount(2, 'estadoPedidos');
});

test('POST /api/estado-pedidos crea un nuevo estado de pedido', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/estado-pedidos', [
            'nombre' => 'Listo para Entrega',
            'descripcion' => 'Esperando repartidor',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Estado de pedido creado correctamente.')
        ->assertJsonPath('estadoPedido.nombre', 'Listo para Entrega')
        ->assertJsonPath('estadoPedido.descripcion', 'Esperando repartidor');

    $this->assertDatabaseHas('estado_pedidos', [
        'nombre' => 'Listo para Entrega',
        'descripcion' => 'Esperando repartidor',
    ]);
});

test('POST /api/estado-pedidos valida unicidad del nombre', function () {
    EstadoPedido::create(['nombre' => 'Pendiente']);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/estado-pedidos', [
            'nombre' => 'Pendiente',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nombre']);
});

test('GET /api/estado-pedidos/{id} retorna el detalle del estado de pedido', function () {
    $estado = EstadoPedido::create(['nombre' => 'Entregado', 'descripcion' => 'Completado']);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/estado-pedidos/{$estado->id}");

    $response->assertOk()
        ->assertJsonPath('estadoPedido.id', $estado->id)
        ->assertJsonPath('estadoPedido.nombre', 'Entregado');
});

test('GET /api/estado-pedidos/{id} retorna 404 para ID inexistente', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/estado-pedidos/99999')
        ->assertNotFound();
});

test('PUT /api/estado-pedidos/{id} actualiza parcialmente los atributos', function () {
    $estado = EstadoPedido::create(['nombre' => 'En Camino', 'descripcion' => 'En transito']);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/estado-pedidos/{$estado->id}", [
            'descripcion' => 'El repartidor va hacia el destino',
        ]);

    $response->assertOk()
        ->assertJsonPath('estadoPedido.nombre', 'En Camino')
        ->assertJsonPath('estadoPedido.descripcion', 'El repartidor va hacia el destino');

    $estado->refresh();
    expect($estado->descripcion)->toBe('El repartidor va hacia el destino')
        ->and($estado->nombre)->toBe('En Camino');
});

test('DELETE /api/estado-pedidos/{id} realiza baja logica y se refleja en indexDesactivados', function () {
    $estado = EstadoPedido::create(['nombre' => 'Cancelado']);

    $deleteResponse = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/estado-pedidos/{$estado->id}");

    $deleteResponse->assertOk()
        ->assertJsonPath('message', 'Estado de pedido eliminado correctamente.');

    $this->assertSoftDeleted('estado_pedidos', ['id' => $estado->id]);

    // No debe aparecer en index normal
    $indexResponse = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/estado-pedidos');
    $indexResponse->assertNotFound(); // Ya que no hay otros estados activos

    // Debe aparecer en indexDesactivados
    $desactivadosResponse = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/estado-pedidos-desactivados');
    $desactivadosResponse->assertOk()
        ->assertJsonCount(1, 'estadoPedidosDesactivados')
        ->assertJsonPath('estadoPedidosDesactivados.0.id', $estado->id);
});

test('PUT /api/estado-pedidos/{id}/restore restaura un estado dado de baja', function () {
    $estado = EstadoPedido::create(['nombre' => 'En Espera']);
    $estado->delete();

    $restoreResponse = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/estado-pedidos/{$estado->id}/restore");

    $restoreResponse->assertOk()
        ->assertJsonPath('message', 'Estado de pedido restaurado correctamente.');

    $this->assertNotSoftDeleted('estado_pedidos', ['id' => $estado->id]);
});
