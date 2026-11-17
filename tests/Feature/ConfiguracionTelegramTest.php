<?php

use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;

it('expone solamente el nombre público configurado sin requerir autenticación', function () {
    config([
        'services.telegram.bot_username' => 'BotDePrueba_bot',
        'services.telegram.bot_token' => 'secreto-de-prueba',
    ]);

    $this->getJson('/api/configuracion-publica')
        ->assertOk()
        ->assertExactJson(['telegram_bot_username' => 'BotDePrueba_bot'])
        ->assertDontSee('secreto-de-prueba')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('rechaza un nombre ausente o inválido sin revelar su contenido', function ($username) {
    config(['services.telegram.bot_username' => $username]);

    $this->getJson('/api/configuracion-publica')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'La configuración de Telegram no está disponible.']);
})->with([null, '', '@BotDePrueba_bot', 'otro/bot', 'bot?token=secreto', "botvalido\n", 'abc', str_repeat('a', 33)]);

it('usa el token configurado para enviar notificaciones', function () {
    config(['services.telegram.bot_token' => 'token-de-prueba']);
    Http::fake();

    expect(app(TelegramService::class)->enviarMensaje(123, 'Prueba'))->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/bottoken-de-prueba/sendMessage'));
});

it('no envía mensajes sin token configurado', function () {
    config(['services.telegram.bot_token' => null]);
    Http::fake();

    expect(app(TelegramService::class)->enviarMensaje(123, 'Prueba'))->toBeFalse();
    Http::assertNothingSent();
});

it('detiene el polling si falta el token configurado', function () {
    config(['services.telegram.bot_token' => null]);
    Http::fake();

    $this->artisan('telegram:poll')->assertFailed();
    Http::assertNothingSent();
});
