<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an authenticated user to update only their own profile details', function () {
    $role = Role::create(['nombre' => 'Cocinero']);
    $user = User::factory()->create([
        'name' => 'Cocinero Original',
        'email' => 'cook@example.com',
        'role_id' => $role->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/profile', [
            'name' => 'Cocinero Actualizado',
            'email' => 'cook-new@example.com',
            'telefono' => '+5493455124468',
            'direccion' => 'San Martín 123',
            'role_id' => 999,
            'activo' => false,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Perfil actualizado correctamente.')
        ->assertJsonPath('user.name', 'Cocinero Actualizado')
        ->assertJsonPath('user.email', 'cook-new@example.com')
        ->assertJsonMissingPath('user.password');

    expect($user->fresh()->role_id)->toBe($role->id)
        ->and($user->fresh()->activo)->toBeTrue();
});

it('rejects invalid or already used profile email addresses', function () {
    $role = Role::create(['nombre' => 'Repartidor']);
    $user = User::factory()->create(['role_id' => $role->id]);
    User::factory()->create(['email' => 'used@example.com', 'role_id' => $role->id]);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/profile', [
            'name' => 'Repartidor Nuevo',
            'email' => 'used@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($user->fresh()->email)->not->toBe('used@example.com');
});

it('applies checkout length and character limits to profile fields', function () {
    $role = Role::create(['nombre' => 'Cocinero']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $valid = [
        'name' => str_repeat('N', 25),
        'email' => $user->email,
        'telefono' => '+'.str_repeat('1', 25),
        'direccion' => str_repeat('D', 25),
    ];

    $this->actingAs($user, 'sanctum')->putJson('/api/profile', $valid)->assertOk();

    foreach ([
        ['name' => str_repeat('N', 26)],
        ['name' => '   '],
        ['telefono' => '+'.str_repeat('1', 26)],
        ['telefono' => '123abc'],
        ['telefono' => '12+34'],
        ['direccion' => str_repeat('D', 26)],
        ['email' => 'no-es-correo'],
        ['email' => 'usuario@dominio'],
    ] as $invalid) {
        $this->putJson('/api/profile', array_merge($valid, $invalid))
            ->assertUnprocessable();
    }

    expect($user->fresh()->name)->toBe($valid['name'])
        ->and($user->fresh()->direccion)->toBe($valid['direccion']);
});

it('requires authentication to update a profile', function () {
    $this->putJson('/api/profile', [
        'name' => 'Nombre Nuevo',
        'email' => 'new@example.com',
    ])->assertUnauthorized();
});
