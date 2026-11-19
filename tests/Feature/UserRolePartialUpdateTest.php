<?php

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
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

test('RoleController@store impide crear roles con nombres reservados', function (string $nombre) {
    $nombreNormalizado = mb_strtolower(trim($nombre));
    $rolesReservadosExistentes = Role::whereRaw('LOWER(TRIM(nombre)) = ?', [$nombreNormalizado])->count();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/roles', [
            'nombre' => $nombre,
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede crear un rol con nombre reservado del sistema.');

    expect(Role::whereRaw('LOWER(TRIM(nombre)) = ?', [$nombreNormalizado])->count())
        ->toBe($rolesReservadosExistentes);
})->with([
    'Administrador',
    ' SuperAdmin ',
    'cocinero',
    'REPARTIDOR',
]);

test('un Administrador no puede crear usuarios con rol SuperAdmin', function () {
    $superAdminRole = Role::create(['nombre' => 'SuperAdmin']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/users', [
            'name' => 'Nuevo Super Admin',
            'email' => 'nuevo.superadmin@laposta.com',
            'password' => 'secret1234',
            'role_id' => $superAdminRole->id,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Solo un SuperAdmin puede asignar ese rol.');

    expect(User::where('email', 'nuevo.superadmin@laposta.com')->exists())->toBeFalse();
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

test('RoleController@update impide renombrar roles base', function (string $nombre) {
    $superAdminRole = Role::create(['nombre' => 'SuperAdmin']);
    $this->admin->role_id = $superAdminRole->id;
    $this->admin->save();

    $role = match ($nombre) {
        'Administrador' => $this->adminRole,
        'SuperAdmin' => $superAdminRole,
        default => Role::create(['nombre' => $nombre]),
    };

    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/roles/{$role->id}", [
            'nombre' => 'Rol Renombrado',
        ]);

    if ($nombre === 'SuperAdmin') {
        $response->assertForbidden()
            ->assertJsonPath('message', 'No puedes modificar el rol asignado a tu cuenta.');
    } else {
        $response->assertStatus(409)
            ->assertJsonPath('message', 'No se puede cambiar el nombre de un rol base ni asignar ese nombre a otro rol.');
    }

    expect($role->refresh()->nombre)->toBe($nombre);
})->with([
    'Administrador',
    'SuperAdmin',
    'Cocinero',
    'Repartidor',
]);

test('RoleController@update permite editar la descripcion del rol base propio', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/roles/{$this->adminRole->id}", [
            'descripcion' => 'Administración protegida',
        ]);

    $response->assertOk();
    expect($this->adminRole->refresh()->descripcion)->toBe('Administración protegida');
});

test('RoleController@update no permite renombrar un rol adicional a un nombre reservado', function (string $nombre) {
    $role = Role::create(['nombre' => 'Auditor']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/roles/{$role->id}", [
            'nombre' => $nombre,
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede cambiar el nombre de un rol base ni asignar ese nombre a otro rol.');

    expect($role->refresh()->nombre)->toBe('Auditor');
})->with([
    'SuperAdmin',
    'administrador',
    ' Cocinero ',
    'Repartidor',
]);

test('RoleController@destroy impide dar de baja roles base', function (string $nombre) {
    $role = $nombre === 'Administrador'
        ? $this->adminRole
        : Role::create(['nombre' => $nombre]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede desactivar un rol base del sistema.');

    expect($role->refresh()->trashed())->toBeFalse();
})->with([
    'Administrador',
    'SuperAdmin',
    'Cocinero',
    'Repartidor',
]);

test('RoleController@destroy impide dar de baja roles con usuarios asociados', function () {
    $role = Role::create(['nombre' => 'Auditor']);
    User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede desactivar un rol que tiene usuarios asociados.');

    expect($role->refresh()->trashed())->toBeFalse();
});

test('RoleController@destroy permite dar de baja un rol adicional sin usuarios asociados', function () {
    $role = Role::create(['nombre' => 'Auditor']);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/roles/{$role->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Rol eliminado correctamente.');

    expect($role->fresh()?->trashed())->toBeTrue()
        ->and(Role::withTrashed()->find($role->id))->not->toBeNull();
});

test('RoleController@destroy considera usuarios eliminados logicamente como asociados al rol', function () {
    $role = Role::create(['nombre' => 'Auditor']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->delete();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede desactivar un rol que tiene usuarios asociados.');

    expect($role->refresh()->trashed())->toBeFalse();
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

test('un usuario no puede modificar el rol de su propia cuenta', function () {
    $otherRole = Role::create([
        'nombre' => 'Cocinero',
        'descripcion' => 'Personal de cocina',
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$this->admin->id}", [
            'role_id' => $otherRole->id,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'No puedes modificar el rol de tu propia cuenta.');

    $this->admin->refresh();
    expect($this->admin->role_id)->toBe($this->adminRole->id);
});

test('un usuario no puede desactivar su propia cuenta', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$this->admin->id}", [
            'activo' => false,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'No puedes desactivar tu propia cuenta.');

    expect($this->admin->refresh()->activo)->toBeTrue();
});

test('un usuario puede actualizar su cuenta sin cambiar su rol ni desactivarse', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$this->admin->id}", [
            'activo' => true,
            'name' => 'Admin Actualizado',
        ])
        ->assertOk();

    $this->admin->refresh();
    expect($this->admin->activo)->toBeTrue()
        ->and($this->admin->role_id)->toBe($this->adminRole->id)
        ->and($this->admin->name)->toBe('Admin Actualizado');
});

test('un Administrador puede cambiar el rol de otro usuario', function () {
    $otherRole = Role::create(['nombre' => 'Cocinero']);
    $currentRole = Role::create(['nombre' => 'Cajero']);
    $targetUser = User::factory()->create(['role_id' => $currentRole->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$targetUser->id}", [
            'role_id' => $otherRole->id,
        ])
        ->assertOk();

    expect($targetUser->refresh()->role_id)->toBe($otherRole->id);
});

test('un Administrador no puede cambiar el rol de una cuenta SuperAdmin', function () {
    $superAdminRole = Role::create(['nombre' => 'SuperAdmin']);
    $otherRole = Role::create(['nombre' => 'Cocinero']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$superAdmin->id}", [
            'role_id' => $otherRole->id,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Solo un SuperAdmin puede modificar cuentas SuperAdmin.');

    expect($superAdmin->refresh()->role_id)->toBe($superAdminRole->id);
});

test('un Administrador no puede editar el perfil de una cuenta SuperAdmin', function () {
    $superAdminRole = Role::create(['nombre' => 'SuperAdmin']);
    $superAdmin = User::factory()->create([
        'name' => 'Super Administrador',
        'role_id' => $superAdminRole->id,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$superAdmin->id}", [
            'name' => 'Nombre Modificado',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Solo un SuperAdmin puede modificar cuentas SuperAdmin.');

    expect($superAdmin->refresh()->name)->toBe('Super Administrador');
});

test('un SuperAdmin puede editar el perfil de otra cuenta SuperAdmin', function () {
    $superAdminRole = Role::create(['nombre' => 'SuperAdmin']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id, 'activo' => true]);
    $otherSuperAdmin = User::factory()->create([
        'name' => 'Super Administrador',
        'role_id' => $superAdminRole->id,
    ]);

    $this->actingAs($superAdmin, 'sanctum')
        ->putJson("/api/users/{$otherSuperAdmin->id}", [
            'name' => 'Super Administrador Editado',
        ])
        ->assertOk();

    expect($otherSuperAdmin->refresh()->name)->toBe('Super Administrador Editado');
});

test('un Administrador no puede cambiar el rol de otra cuenta Administrador', function () {
    $targetAdmin = User::factory()->create([
        'name' => 'Admin Secundario',
        'email' => 'admin.secundario@laposta.com',
        'role_id' => $this->adminRole->id,
    ]);
    $otherRole = Role::create(['nombre' => 'Cocinero']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$targetAdmin->id}", [
            'role_id' => $otherRole->id,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Solo un SuperAdmin puede cambiar el rol de una cuenta Administrador.');

    expect($targetAdmin->refresh()->role_id)->toBe($this->adminRole->id);
});

test('un Administrador puede cambiar otros datos sin cambiar el rol de otra cuenta Administrador', function () {
    $targetAdmin = User::factory()->create([
        'name' => 'Admin Secundario',
        'email' => 'admin.secundario@laposta.com',
        'role_id' => $this->adminRole->id,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$targetAdmin->id}", [
            'name' => 'Admin Actualizado',
        ])
        ->assertOk();

    expect($targetAdmin->refresh()->name)->toBe('Admin Actualizado')
        ->and($targetAdmin->role_id)->toBe($this->adminRole->id);
});

test('un SuperAdmin puede cambiar el rol de una cuenta Administrador', function () {
    $superAdminRole = Role::create(['nombre' => 'SuperAdmin']);
    $otherRole = Role::create(['nombre' => 'Cocinero']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id, 'activo' => true]);
    $targetAdmin = User::factory()->create(['role_id' => $this->adminRole->id]);

    $this->actingAs($superAdmin, 'sanctum')
        ->putJson("/api/users/{$targetAdmin->id}", [
            'role_id' => $otherRole->id,
        ])
        ->assertOk();

    expect($targetAdmin->refresh()->role_id)->toBe($otherRole->id);
});

test('no se puede cambiar el rol de un Cocinero con un pedido activo asignado', function () {
    $cocineroRole = Role::create(['nombre' => 'Cocinero']);
    $cocinero = User::factory()->create(['role_id' => $cocineroRole->id]);
    $pedido = crearPedidoAsignadoAUsuario($cocinero, 'cocinero_id', 'En preparación');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$cocinero->id}", [
            'role_id' => $this->adminRole->id,
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede cambiar el rol porque tiene pedidos activos asignados.');

    expect($cocinero->refresh()->role_id)->toBe($cocineroRole->id)
        ->and($pedido->fresh()->cocinero_id)->toBe($cocinero->id);
});

test('no se puede cambiar el rol de un Repartidor con un pedido activo asignado', function () {
    $repartidorRole = Role::create(['nombre' => 'Repartidor']);
    $repartidor = User::factory()->create(['role_id' => $repartidorRole->id]);
    $pedido = crearPedidoAsignadoAUsuario($repartidor, 'repartidor_id', 'En camino');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$repartidor->id}", [
            'role_id' => $this->adminRole->id,
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede cambiar el rol porque tiene pedidos activos asignados.');

    expect($repartidor->refresh()->role_id)->toBe($repartidorRole->id)
        ->and($pedido->fresh()->repartidor_id)->toBe($repartidor->id);
});

test('se puede cambiar el rol de personal sin pedidos activos aunque conserve pedidos completados', function () {
    $repartidorRole = Role::create(['nombre' => 'Repartidor']);
    $repartidor = User::factory()->create(['role_id' => $repartidorRole->id]);
    crearPedidoAsignadoAUsuario($repartidor, 'repartidor_id', 'Entregado');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$repartidor->id}", [
            'role_id' => $this->adminRole->id,
        ])
        ->assertOk();

    expect($repartidor->refresh()->role_id)->toBe($this->adminRole->id);
});

test('no se puede desactivar un Cocinero con un pedido activo asignado', function () {
    $cocineroRole = Role::create(['nombre' => 'Cocinero']);
    $cocinero = User::factory()->create(['role_id' => $cocineroRole->id]);
    $pedido = crearPedidoAsignadoAUsuario($cocinero, 'cocinero_id', 'En preparación');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/users/{$cocinero->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede desactivar al usuario porque tiene pedidos activos asignados. Completa esos pedidos antes de continuar. ');

    expect($cocinero->refresh()->activo)->toBeTrue()
        ->and($cocinero->trashed())->toBeFalse()
        ->and($pedido->fresh()->cocinero_id)->toBe($cocinero->id);
});

test('no se puede desactivar un Repartidor con un pedido activo asignado', function () {
    $repartidorRole = Role::create(['nombre' => 'Repartidor']);
    $repartidor = User::factory()->create(['role_id' => $repartidorRole->id]);
    $pedido = crearPedidoAsignadoAUsuario($repartidor, 'repartidor_id', 'En camino');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/users/{$repartidor->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede desactivar al usuario porque tiene pedidos activos asignados. Completa esos pedidos antes de continuar. ');

    expect($repartidor->refresh()->activo)->toBeTrue()
        ->and($repartidor->trashed())->toBeFalse()
        ->and($pedido->fresh()->repartidor_id)->toBe($repartidor->id);
});

test('se puede desactivar personal que solo conserva pedidos entregados o cancelados', function (string $estadoNombre) {
    $cocineroRole = Role::create(['nombre' => 'Cocinero']);
    $cocinero = User::factory()->create(['role_id' => $cocineroRole->id]);
    crearPedidoAsignadoAUsuario($cocinero, 'cocinero_id', $estadoNombre);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/users/{$cocinero->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Usuario eliminado correctamente.');

    $this->assertSoftDeleted('users', ['id' => $cocinero->id]);
    $this->assertDatabaseHas('users', ['id' => $cocinero->id, 'activo' => false]);
})->with([
    'Entregado',
    'Cancelado',
]);

function crearPedidoAsignadoAUsuario(User $user, string $campoAsignacion, string $estadoNombre): Pedido
{
    $cliente = Cliente::create([
        'username' => 'cliente-'.strtolower($estadoNombre).'-'.$user->id,
        'telefono' => '1234567890',
        'direccion' => 'Calle de prueba 123',
    ]);
    $estado = EstadoPedido::create([
        'nombre' => $estadoNombre,
        'descripcion' => 'Estado para prueba de cambio de rol',
    ]);

    return Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estado->id,
        $campoAsignacion => $user->id,
        'subtotal' => 1000,
        'monto_total' => 1000,
    ]);
}

test('un Administrador no puede asignar el rol SuperAdmin a otra cuenta', function () {
    $superAdminRole = Role::create(['nombre' => 'SuperAdmin']);
    $currentRole = Role::create(['nombre' => 'Cajero']);
    $targetUser = User::factory()->create(['role_id' => $currentRole->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/users/{$targetUser->id}", [
            'role_id' => $superAdminRole->id,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Solo un SuperAdmin puede cambiar asignaciones de ese rol.');

    expect($targetUser->refresh()->role_id)->toBe($currentRole->id);
});

test('un SuperAdmin puede cambiar el rol de otros usuarios', function () {
    $superAdminRole = Role::create(['nombre' => 'SuperAdmin']);
    $otherRole = Role::create(['nombre' => 'Cocinero']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id, 'activo' => true]);
    $targetUser = User::factory()->create(['role_id' => $this->adminRole->id]);

    $this->actingAs($superAdmin, 'sanctum')
        ->putJson("/api/users/{$targetUser->id}", [
            'role_id' => $otherRole->id,
        ])
        ->assertOk();

    expect($targetUser->refresh()->role_id)->toBe($otherRole->id);
});

test('un Administrador no puede modificar ni desactivar el rol asignado a su cuenta', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/roles/{$this->adminRole->id}", [
            'nombre' => 'Administrador General',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'No puedes modificar el rol asignado a tu cuenta.');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/roles/{$this->adminRole->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'No se puede desactivar un rol base del sistema.');

    $this->adminRole->refresh();
    expect($this->adminRole->nombre)->toBe('Administrador');
});
