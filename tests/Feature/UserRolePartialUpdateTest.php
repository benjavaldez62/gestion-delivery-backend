<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::create([
        'nombre' => 'Administrador',
        'descripcion' => 'Rol de administracion general',
    ]);

    $this->admin = User::factory()->create([
        'name' => 'Admin General',
        'email' => 'admin@laposta.com',
        'password' => 'secret1234',
        'role_id' => $this->adminRole->id,
        'telefono' => '1122334455',
        'direccion' => 'Av. Corrientes 1234',
        'activo' => true,
    ]);
});

test('RoleController@store crea un rol exitosamente omitiendo el campo opcional descripcion', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/roles', [
            'nombre' => 'Auditor',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Rol creado correctamente')
        ->assertJsonPath('rol.nombre', 'Auditor')
        ->assertJsonPath('rol.descripcion', null);

    $this->assertDatabaseHas('roles', [
        'nombre' => 'Auditor',
        'descripcion' => null,
    ]);
});

test('RoleController@update permite actualizar parcialmente solo el nombre sin omitir descripcion existente', function () {
    $role = Role::create([
        'nombre' => 'Subgerente',
        'descripcion' => 'Supervisa el local en ausencia del gerente',
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/roles/{$role->id}", [
            'nombre' => 'Subgerente General',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Rol actualizado correctamente.')
        ->assertJsonPath('rol.nombre', 'Subgerente General');

    $role->refresh();
    expect($role->nombre)->toBe('Subgerente General')
        ->and($role->descripcion)->toBe('Supervisa el local en ausencia del gerente');
});

test('RoleController@update permite actualizar solo la descripcion de forma independiente', function () {
    $role = Role::create([
        'nombre' => 'Personal Limpieza',
        'descripcion' => 'Descripcion inicial',
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/roles/{$role->id}", [
            'descripcion' => 'Mantenimiento e higiene de cocina y salon',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Rol actualizado correctamente.');

    $role->refresh();
    expect($role->nombre)->toBe('Personal Limpieza')
        ->and($role->descripcion)->toBe('Mantenimiento e higiene de cocina y salon');
});

test('UserController@update actualiza parcialmente telefono y direccion sin requerir los demas campos', function () {
    $targetUser = User::factory()->create([
        'name' => 'Empleado Sucursal',
        'email' => 'empleado@laposta.com',
        'role_id' => $this->adminRole->id,
        'telefono' => '1199887766',
        'direccion' => 'Calle Falsa 123',
        'activo' => true,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$targetUser->id}", [
            'telefono' => '1155443322',
            'direccion' => 'Av. San Martin 999',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Usuario actualizado correctamente.');

    $targetUser->refresh();
    expect($targetUser->telefono)->toBe('1155443322')
        ->and($targetUser->direccion)->toBe('Av. San Martin 999')
        ->and($targetUser->name)->toBe('Empleado Sucursal')
        ->and($targetUser->email)->toBe('empleado@laposta.com');
});

test('UserController@update actualiza parcialmente solo el estado activo sin modificar demas campos', function () {
    $targetUser = User::factory()->create([
        'name' => 'Empleado Activo',
        'email' => 'activo@laposta.com',
        'role_id' => $this->adminRole->id,
        'activo' => true,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$targetUser->id}", [
            'activo' => false,
        ]);

    $response->assertOk();

    $targetUser->refresh();
    expect($targetUser->activo)->toBeFalse()
        ->and($targetUser->name)->toBe('Empleado Activo');
});
