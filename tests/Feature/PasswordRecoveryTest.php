<?php

use App\Models\User;
use App\Notifications\RecuperarContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('envía un enlace seguro a una cuenta activa', function () {
    config(['app.frontend_url' => 'https://frontend.example.test', 'mail.default' => 'smtp']);
    $user = User::factory()->create(['email' => 'persona@example.test']);
    $notification = null;
    $channel = Mockery::mock(MailChannel::class);
    $channel->shouldReceive('send')->once()->andReturnUsing(function ($recipient, $message) use (&$notification, $user) {
        expect($recipient->is($user))->toBeTrue();
        $notification = $message;

        return Mockery::mock(SentMessage::class);
    });
    $this->app->instance(MailChannel::class, $channel);

    $existente = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
    $existente->assertJson(['action' => 'enlace_enviado']);
    $existente->assertHeader('Cache-Control', 'no-store, private');
    $existente->assertJsonMissingPath('token');

    expect($notification)->toBeInstanceOf(RecuperarContrasena::class);
    (function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;
        $parts = parse_url($url);
        $params = json_decode(base64_decode(strtr($parts['fragment'], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        expect($parts['scheme'] === 'https'
            && $parts['host'] === 'frontend.example.test'
            && $parts['path'] === '/restablecer-contrasena'
            && ! isset($parts['query'])
            && $params['email'] === $user->email
            && $stored !== $params['token']
            && Hash::check($params['token'], $stored))->toBeTrue();
    })($notification);
});

it('rechaza un correo inválido sin crear un token', function () {
    $this->postJson('/api/forgot-password', ['email' => 'invalido'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

it('informa correos inexistentes e inactivos sin enviar correos', function () {
    Notification::fake();
    $channel = Mockery::mock(MailChannel::class);
    $channel->shouldNotReceive('send');
    $this->app->instance(MailChannel::class, $channel);
    $user = User::factory()->create(['activo' => false, 'email' => 'inactivo@example.test']);
    $this->postJson('/api/forgot-password', ['email' => 'ausente@example.test'])
        ->assertNotFound()->assertJson(['message' => 'Email no ingresado al sistema.']);
    $this->postJson('/api/forgot-password', ['email' => $user->email])
        ->assertForbidden()->assertJson(['message' => 'El email ingresado pertenece a un usuario inactivo.']);
    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

it('indica contactar al administrador para cualquier dirección del dominio exacto de prueba', function () {
    Notification::fake();
    $channel = Mockery::mock(MailChannel::class);
    $channel->shouldNotReceive('send');
    $this->app->instance(MailChannel::class, $channel);
    User::factory()->create(['email' => 'persona@test.com']);
    foreach (['persona@test.com', 'ausente@TEST.COM'] as $email) {
        $this->postJson('/api/forgot-password', ['email' => $email])
            ->assertOk()->assertJson(['action' => 'contactar_administrador']);
    }
    $this->postJson('/api/forgot-password', ['email' => 'ausente@not-test.com'])->assertNotFound();
    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

it('rechaza una URL que sustituye SMTP por un transporte de registro', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.url' => 'log://localhost']);
    $user = User::factory()->create(['email' => 'persona@example.test']);
    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertStatus(503);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

it('no confirma el envío si el mensaje fue cancelado', function () {
    config(['mail.default' => 'smtp']);
    Event::listen(MessageSending::class, fn () => false);
    $user = User::factory()->create(['email' => 'persona@example.test']);
    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertStatus(503);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

it('rechaza transportes no SMTP sin generar token ni registrar el enlace', function () {
    config(['mail.default' => 'log']);
    $user = User::factory()->create(['email' => 'persona@example.test']);
    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertStatus(503);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

it('informa fallos de envío y registra solamente el tipo de error', function () {
    config(['mail.default' => 'smtp']);
    $channel = Mockery::mock(MailChannel::class);
    $channel->shouldReceive('send')->once()->andThrow(new RuntimeException('detalle sensible'));
    $this->app->instance(MailChannel::class, $channel);
    Log::spy();
    $user = User::factory()->create(['email' => 'persona@example.test']);
    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertStatus(503);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
    Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context) {
        return $message === 'No se pudo procesar el correo de recuperación.'
            && isset($context['exception_type'])
            && count($context) === 1;
    });
});

it('limita solicitudes repetidas por cuenta mediante el broker', function () {
    config(['mail.default' => 'smtp']);
    $channel = Mockery::mock(MailChannel::class);
    $channel->shouldReceive('send')->once()->andReturn(Mockery::mock(SentMessage::class));
    $this->app->instance(MailChannel::class, $channel);
    $user = User::factory()->create(['email' => 'persona@example.test']);
    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertStatus(429);
});

it('rechaza tokens previos para cuentas inactivas, eliminadas o de prueba', function () {
    foreach (['inactiva', 'eliminada', 'prueba'] as $estado) {
        $user = User::factory()->create(['email' => $estado.'@example.test', 'password' => 'anterior123']);
        $token = Password::broker('users')->createToken($user);
        if ($estado === 'inactiva') {
            $user->update(['activo' => false]);
        } elseif ($estado === 'eliminada') {
            $user->delete();
        } else {
            $user->update(['email' => 'persona@test.com']);
            DB::table('password_reset_tokens')->where('email', $estado.'@example.test')->update(['email' => $user->email]);
        }
        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'nueva12345',
            'password_confirmation' => 'nueva12345',
        ])->assertStatus($estado === 'prueba' ? 403 : 422);
        expect(Hash::check('anterior123', $user->fresh()->password))->toBeTrue();
    }
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
    config(['mail.default' => 'smtp']);
    $user = User::factory()->create(['email' => 'persona+test@example.test', 'password' => 'anterior123']);
    $notification = null;
    $channel = Mockery::mock(MailChannel::class);
    $channel->shouldReceive('send')->once()->andReturnUsing(function ($recipient, $message) use (&$notification) {
        $notification = $message;

        return Mockery::mock(SentMessage::class);
    });
    $this->app->instance(MailChannel::class, $channel);
    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
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
        $this->postJson('/api/forgot-password', ['email' => 'ausente@example.test'])->assertNotFound();
    }
    $this->postJson('/api/forgot-password', ['email' => 'ausente@example.test'])->assertStatus(429);
    $this->postJson('/api/reset-password', [])->assertUnprocessable();
});
