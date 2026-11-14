<?php

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.telegram.bot_token' => 'token-de-prueba']);
});

function crearPrePedidoParaTelegram(): int
{
    $cat = Categoria::create(['nombre' => 'Pizzas', 'activo' => true]);
    $prod = Producto::create([
        'categoria_id' => $cat->id,
        'nombre' => 'Muzzarella',
        'descripcion' => 'Pizza clásica',
        'precio' => 5000,
        'activo' => true,
    ]);

    $checkoutResponse = test()->postJson('/api/checkout/borradores', [
        'nombre_cliente' => 'Nombre Apellido',
        'direccion' => 'Calle Falsa 123',
        'telefono' => '3454123456',
        'metodo_pago' => 'efectivo',
        'items' => [
            [
                'producto_id' => $prod->id,
                'cantidad' => 1,
                'adicionales' => [],
            ],
        ],
    ])->assertCreated();

    return (int) $checkoutResponse->json('pedido_id');
}

it('procesa webhook de Telegram con comando /start <id> y confirma el pedido con botones inline', function () {
    Http::fake();

    $pedidoId = crearPrePedidoParaTelegram();

    $payload = [
        'update_id' => 10001,
        'message' => [
            'message_id' => 1,
            'from' => [
                'id' => 987654321,
                'first_name' => 'Carlos',
                'last_name' => 'Gómez',
                'username' => 'carlos_gomez',
            ],
            'chat' => [
                'id' => 987654321,
                'type' => 'private',
            ],
            'text' => "/start {$pedidoId}",
        ],
    ];

    $response = $this->postJson('/api/telegram/webhook', $payload)
        ->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('pedido_id', $pedidoId)
        ->assertJsonPath('estado', 'Sin Asignar');

    $pedido = Pedido::find($pedidoId);
    expect($pedido->telegram_chat_id)->toBe(987654321)
        ->and($pedido->telegram_username)->toBe('carlos_gomez')
        ->and($pedido->estadoPedido->nombre)->toBe('Sin Asignar')
        ->and($pedido->pagos()->count())->toBe(1)
        ->and($pedido->pagos()->first()->estadoPago->nombre)->toBe('Pendiente')
        ->and($pedido->pagos()->first()->fecha_pago)->toBeNull();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === 987654321
            && str_contains($request['text'], 'confirmado con éxito')
            && isset($request['reply_markup']['inline_keyboard']);
    });
});

it('confirma sin sobrescribir otro cliente que ya usa el username de Telegram', function () {
    Http::fake();
    Cliente::create(['username' => 'usuario_repetido']);
    $pedidoId = crearPrePedidoParaTelegram();
    $cliente = Pedido::findOrFail($pedidoId)->cliente;
    $usernameOriginal = $cliente->username;

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 987654321],
            'from' => ['username' => 'usuario_repetido'],
            'text' => "Pedido #{$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');
    expect($cliente->fresh()->username)->toBe($usernameOriginal)
        ->and(Pedido::findOrFail($pedidoId)->pagos()->count())->toBe(1);
});

it('aplica anti-spam en saludo inicial cuando el usuario no envia numero de orden', function () {
    Http::fake();

    $payload = [
        'message' => [
            'chat' => ['id' => 777888],
            'text' => 'Hola bot!',
        ],
    ];

    // Primer mensaje: recibe bienvenida completa y teclado rápido
    $this->postJson('/api/telegram/webhook', $payload)
        ->assertOk()
        ->assertJsonPath('status', 'info_sent');

    Http::assertSent(function ($request) {
        return $request['chat_id'] === 777888
            && str_contains($request['text'], 'Bienvenido a <b>La Posta Delivery</b>');
    });

    // Segundo mensaje consecutivo: recibe recordatorio corto (anti-spam activo)
    $this->postJson('/api/telegram/webhook', $payload)
        ->assertOk()
        ->assertJsonPath('status', 'info_sent');

    Http::assertSent(function ($request) {
        return $request['chat_id'] === 777888
            && str_contains($request['text'], 'Para confirmar una orden envianos');
    });
});

it('responde estado en tiempo real al enviar comando /estado o boton de consulta', function () {
    Http::fake();

    $pedidoId = crearPrePedidoParaTelegram();

    // Confirmar orden vinculando el chat
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 555666],
            'text' => "Pedido #{$pedidoId}",
        ],
    ])->assertOk();

    // Consultar estado con /estado
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 555666],
            'text' => '/estado',
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'order_status_sent')
        ->assertJsonPath('pedido_id', $pedidoId)
        ->assertJsonPath('estado', 'Sin Asignar');

    Http::assertSent(function ($request) {
        return $request['chat_id'] === 555666
            && str_contains($request['text'], 'Estado de tu Pedido')
            && str_contains($request['text'], 'Sin Asignar');
    });
});

it('avisa si el pedido ya fue confirmado previamente', function () {
    Http::fake();

    $pedidoId = crearPrePedidoParaTelegram();

    // Primera confirmación
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 123456],
            'text' => "Pedido #{$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    // Segundo intento con el mismo pedido
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 123456],
            'text' => "Pedido #{$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'already_confirmed');
});

it('TelegramService notifica al cliente con telegram_chat_id cuando cambia de estado', function () {
    Http::fake();

    $pedidoId = crearPrePedidoParaTelegram();
    $pedido = Pedido::find($pedidoId);
    $pedido->telegram_chat_id = 999888;
    $pedido->save();

    $telegramService = app(TelegramService::class);

    $estadoPreparacion = EstadoPedido::whereRaw("LOWER(TRIM(nombre)) LIKE '%preparaci%'")->first()
        ?? EstadoPedido::create(['nombre' => 'En preparación', 'descripcion' => 'En cocina']);

    $pedido->estado_pedidos_id = $estadoPreparacion->id;
    $pedido->save();

    $enviado = $telegramService->notificarCambioEstado($pedido);
    expect($enviado)->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === 999888
            && str_contains($request['text'], 'cocinando');
    });
});
