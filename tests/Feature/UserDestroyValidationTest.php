<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superAdminRole = Role::create([
        'nombre' => 'SuperAdmin',
        'descripcion' => 'Acceso completo al sistema',
    ]);

    $this->adminRole = Role::create([
        'nombre' => 'Administrador',
        'descripcion' => 'Rol de administracion general',
    ]);

    $this->cocineroRole = Role::create([
        'nombre' => 'Cocinero',
        'descripcion' => 'Personal de cocina',
    ]);

    $this->superAdmin = User::factory()->create([
        'name' => 'Super Admin',
        'email' => 'superadmin@laposta.com',
        'password' => 'secret1234',
        'role_id' => $this->superAdminRole->id,
        'telefono' => '1100000001',
        'direccion' => 'Av. Principal 100',
        'activo' => true,
    ]);

    $this->admin = User::factory()->create([
        'name' => 'Admin Uno',
        'email' => 'admin1@laposta.com',
        'password' => 'secret1234',
        'role_id' => $this->adminRole->id,
        'telefono' => '1100000002',
        'direccion' => 'Av. Principal 200',
        'activo' => true,
    ]);

    $this->otherAdmin = User::factory()->create([
        'name' => 'Admin Dos',
        'email' => 'admin2@laposta.com',
        'password' => 'secret1234',
        'role_id' => $this->adminRole->id,
        'telefono' => '1100000003',
        'direccion' => 'Av. Principal 300',
        'activo' => true,
    ]);

    $this->cocinero = User::factory()->create([
        'name' => 'Cocinero Uno',
        'email' => 'cocinero@laposta.com',
        'password' => 'secret1234',
        'role_id' => $this->cocineroRole->id,
        'telefono' => '1100000004',
        'direccion' => 'Av. Principal 400',
        'activo' => true,
    ]);
});

test('un usuario no puede auto-eliminarse y recibe 403', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/users/{$this->admin->id}");

    $response->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No puedes eliminar tu propio usuario.');

    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
        'activo' => true,
        'deleted_at' => null,
    ]);
});

test('nadie puede eliminar a un SuperAdmin y recibe 403', function () {
    $otherSuperAdmin = User::factory()->create([
        'name' => 'Super Admin Dos',
        'email' => 'superadmin2@laposta.com',
        'password' => 'secret1234',
        'role_id' => $this->superAdminRole->id,
        'telefono' => '1100000005',
        'direccion' => 'Av. Principal 500',
        'activo' => true,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/users/{$this->superAdmin->id}")
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No puedes eliminar a un superadmin.');

    $this->actingAs($otherSuperAdmin, 'sanctum')
        ->deleteJson("/api/users/{$this->superAdmin->id}")
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No puedes eliminar a un superadmin.');
});

test('un Administrador no puede eliminar a otro Administrador y recibe 403', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/users/{$this->otherAdmin->id}")
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No puedes eliminar a un administrador.');

    $this->assertDatabaseHas('users', [
        'id' => $this->otherAdmin->id,
        'activo' => true,
        'deleted_at' => null,
    ]);
});

test('un SuperAdmin puede eliminar a un Administrador correctamente', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->deleteJson("/api/users/{$this->admin->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Usuario eliminado correctamente.');

    $this->assertSoftDeleted('users', [
        'id' => $this->admin->id,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
        'activo' => false,
    ]);
});

test('un Administrador puede eliminar a un usuario con rol operativo', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/users/{$this->cocinero->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Usuario eliminado correctamente.');

    $this->assertSoftDeleted('users', [
        'id' => $this->cocinero->id,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->cocinero->id,
        'activo' => false,
    ]);
});

test('eliminar un usuario inexistente devuelve 404 en lugar de 500', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson('/api/users/999999')
        ->assertNotFound()
        ->assertJsonPath('message', 'Usuario no encontrado.');
});

