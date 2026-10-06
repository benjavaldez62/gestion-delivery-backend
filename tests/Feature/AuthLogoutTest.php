<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('revoca el token actual al cerrar sesión', function () {
    $user = User::factory()->create(['activo' => true]);

    $token = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->json('data.access_token');

    $this->withToken($token)
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('rechaza el logout sin autenticación', function () {
    $this->postJson('/api/logout')->assertUnauthorized();
});
