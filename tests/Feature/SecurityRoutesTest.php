<?php

use App\Models\Adicional;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('las rutas del catalogo son publicas y accesibles sin autenticacion', function () {
    Categoria::create([
        'nombre' => 'Pizzas',
        'descripcion' => 'Pizzas artesanales',
    ]);

    Producto::create([
        'nombre' => 'Muzzarella',
        'descripcion' => 'Pizza de muzzarella',
        'precio' => 1200,
        'activo' => true,
        'categoria_id' => 1,
    ]);

    Adicional::create([
        'nombre' => 'Faina',
        'precio' => 300,
        'activo' => true,
    ]);

    $this->getJson('/api/productos')->assertOk();
    $this->getJson('/api/categorias')->assertOk();
    $this->getJson('/api/adicionales')->assertOk();
});

test('las rutas protegidas rechazan peticiones sin token con codigo 401', function () {
    $this->getJson('/api/clientes')->assertUnauthorized()
        ->assertJson([
            'success' => false,
            'message' => 'No autenticado. Inicia sesión para continuar.',
        ]);

    $this->postJson('/api/productos', [])->assertUnauthorized();
    $this->getJson('/api/pagos')->assertUnauthorized();
    $this->getJson('/api/roles')->assertUnauthorized();
    $this->getJson('/api/users')->assertUnauthorized();
});

test('un usuario con rol Administrador puede acceder a la gestion de roles y usuarios', function () {
    $adminRole = Role::create([
        'nombre' => 'Administrador',
        'descripcion' => 'Acceso total',
    ]);

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'activo' => true,
    ]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/roles')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/users')->assertOk();
});

test('un usuario con rol Cocinero es rechazado con 403 al intentar acceder a rutas exclusivas de Administrador', function () {
    $cocineroRole = Role::create([
        'nombre' => 'Cocinero',
        'descripcion' => 'Cocina',
    ]);

    $cocinero = User::factory()->create([
        'role_id' => $cocineroRole->id,
        'activo' => true,
    ]);

    $response = $this->actingAs($cocinero, 'sanctum')->getJson('/api/roles');

    $response->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'No autorizado para acceder a este recurso.',
        ]);

    $this->actingAs($cocinero, 'sanctum')->getJson('/api/users')->assertForbidden();
});

test('un usuario inactivo es rechazado con 403 en rutas protegidas', function () {
    $adminRole = Role::create([
        'nombre' => 'Administrador',
        'descripcion' => 'Acceso total',
    ]);

    $inactivo = User::factory()->create([
        'role_id' => $adminRole->id,
        'activo' => false,
    ]);

    $response = $this->actingAs($inactivo, 'sanctum')->getJson('/api/roles');

    $response->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'Usuario inactivo. No tiene permisos para operar en el sistema.',
        ]);
});

test('un usuario con rol SuperAdmin tiene acceso bypass completo', function () {
    $superAdminRole = Role::create([
        'nombre' => 'SuperAdmin',
        'descripcion' => 'Control total del sistema',
    ]);

    $superAdmin = User::factory()->create([
        'role_id' => $superAdminRole->id,
        'activo' => true,
    ]);

    $cliente = Cliente::create([
        'username' => 'cliente-superadmin',
        'telefono' => '123456789',
        'direccion' => 'Calle 123',
    ]);
    $estado = EstadoPedido::create([
        'nombre' => 'Pendiente',
        'descripcion' => 'Pendiente',
    ]);
    Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estado->id,
        'subtotal' => 500,
        'monto_total' => 500,
    ]);

    $this->actingAs($superAdmin, 'sanctum')->getJson('/api/roles')->assertOk();
    $this->actingAs($superAdmin, 'sanctum')->getJson('/api/users')->assertOk();
    $this->actingAs($superAdmin, 'sanctum')->getJson('/api/pedidos')->assertOk();
});
