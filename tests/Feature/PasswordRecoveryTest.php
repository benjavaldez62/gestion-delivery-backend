<?php

use App\Models\User;
use App\Notifications\RecuperarContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('responde igual para correos existentes e inexistentes y envía un enlace seguro', function () {
    Notification::fake();
    config(['app.frontend_url' => 'https://frontend.example.test']);
    $user = User::factory()->create(['email' => 'persona@example.test']);

    $existente = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
    $inexistente = $this->postJson('/api/forgot-password', ['email' => 'ausente@example.test'])->assertOk();
    expect($existente->json())->toBe($inexistente->json());
    $existente->assertHeader('Cache-Control', 'no-store, private');
    $existente->assertJsonMissingPath('token');

    Notification::assertSentTo($user, RecuperarContrasena::class, function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;
        $parts = parse_url($url);
        $params = json_decode(base64_decode(strtr($parts['fragment'], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        return $parts['scheme'] === 'https'
            && $parts['host'] === 'frontend.example.test'
            && $parts['path'] === '/restablecer-contrasena'
            && ! isset($parts['query'])
            && $params['email'] === $user->email
            && $stored !== $params['token']
            && Hash::check($params['token'], $stored);
    });
});

it('rechaza un correo inválido sin crear un token', function () {
    $this->postJson('/api/forgot-password', ['email' => 'invalido'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

it('registra errores de correo sin revelar si la cuenta existe', function () {
    config(['mail.default' => 'mailer-inexistente']);
    Log::spy();
    $user = User::factory()->create();
    $existente = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
    $inexistente = $this->postJson('/api/forgot-password', ['email' => 'ausente@example.test'])->assertOk();
    expect($existente->json())->toBe($inexistente->json());
    Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context) {
        return $message === 'No se pudo procesar el correo de recuperación.'
            && isset($context['exception_type'])
            && count($context) === 1;
    });
});

it('restablece la contraseña una sola vez e invalida las sesiones existentes', function () {
    $user = User::factory()->create(['password' => 'anterior123']);
    $user->createToken('sesion');
    $token = Password::broker('users')->createToken($user);
    $datos = [
        'email' => $user->email,
        'token' => $token,
        'password' => 'nueva12345',
        'password_confirmation' => 'nueva12345',
    ];

    $this->postJson('/api/reset-password', $datos)->assertOk();
    expect(Hash::check('nueva12345', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('anterior123', $user->fresh()->password))->toBeFalse()
        ->and($user->tokens()->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
    $this->postJson('/api/reset-password', $datos)->assertUnprocessable();
});

it('rechaza tokens vencidos o inválidos sin cambiar la contraseña', function () {
    $user = User::factory()->create(['password' => 'anterior123']);
    $token = Password::broker('users')->createToken($user);
    DB::table('password_reset_tokens')->where('email', $user->email)->update([
        'created_at' => now()->subMinutes(61),
    ]);
    $datos = [
        'email' => $user->email,
        'token' => $token,
        'password' => 'nueva12345',
        'password_confirmation' => 'nueva12345',
    ];

    $this->postJson('/api/reset-password', $datos)->assertUnprocessable();
    $datos['token'] = 'token-incorrecto';
    $this->postJson('/api/reset-password', $datos)->assertUnprocessable();
    expect(Hash::check('anterior123', $user->fresh()->password))->toBeTrue();
});

it('exige contraseña válida y confirmación coincidente', function () {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);
    $this->postJson('/api/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'corta',
        'password_confirmation' => 'otra',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
});

it('rechaza contraseñas que bcrypt truncaría o que contienen caracteres nulos', function () {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);
    foreach ([str_repeat('a', 73), "contrasena\0invalida"] as $password) {
        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }
});

it('permite completar el flujo desde la notificación y acceder con la nueva contraseña', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'persona+test@example.test', 'password' => 'anterior123']);
    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
    $notification = Notification::sent($user, RecuperarContrasena::class)->first();
    $url = $notification->toMail($user)->actionUrl;
    $fragment = parse_url($url, PHP_URL_FRAGMENT);
    $params = json_decode(base64_decode(strtr($fragment, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);

    $this->postJson('/api/reset-password', [
        'email' => $params['email'],
        'token' => $params['token'],
        'password' => 'nNueva12345',
        'password_confirmation' => 'nNueva12345',
    ])->assertOk();
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'anterior123'])->assertUnauthorized();
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nNueva12345'])->assertOk();
});

it('limita solicitudes por IP sin afectar el límite de restablecimiento', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/forgot-password', ['email' => 'ausente@example.test'])->assertOk();
    }
    $this->postJson('/api/forgot-password', ['email' => 'ausente@example.test'])->assertStatus(429);
    $this->postJson('/api/reset-password', [])->assertUnprocessable();
});
