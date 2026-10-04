<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('inicia sesion con credenciales validas y devuelve un token', function () {
    $user = User::factory()->create(['activo' => true]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonMissingPath('data.user.password');

    expect($response->json('data.access_token'))->toBeString()->not->toBeEmpty();
});

it('el token devuelto autentica las rutas protegidas', function () {
    $user = User::factory()->create();

    $token = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

it('rechaza una contrasena incorrecta', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'incorrecta',
    ])->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonMissingPath('data.access_token');
});

it('rechaza un email inexistente con el mismo mensaje', function () {
    $user = User::factory()->create();

    $mensajeContrasenaIncorrecta = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'incorrecta',
    ])->json('message');

    $this->postJson('/api/login', [
        'email' => 'noexiste@test.com',
        'password' => 'password',
    ])->assertUnauthorized()
        ->assertJsonPath('message', $mensajeContrasenaIncorrecta);
});

it('rechaza a un usuario inactivo', function () {
    $user = User::factory()->create(['activo' => false]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonMissingPath('data.access_token');
});

it('no permite iniciar sesion a un usuario eliminado', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertUnauthorized();
});

it('guarda la contrasena hasheada y no en texto plano', function () {
    $user = User::factory()->create();

    expect($user->fresh()->password)
        ->not->toBe('password')
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('valida que email y password sean obligatorios', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['email', 'password'], 'errors');
});

it('valida el formato del email', function () {
    $this->postJson('/api/login', [
        'email' => 'no-es-un-email',
        'password' => 'password',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'El correo electrónico debe ser una dirección válida.');
});
