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
    config([
        'services.telegram.bot_token' => 'token-de-prueba',
        'app.frontend_url' => 'http://localhost:4200',
    ]);
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

it('procesa webhook de Telegram con comando /start <id> y confirma el pedido sin inline_keyboard local', function () {
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

    $this->postJson('/api/telegram/webhook', $payload)
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
        $payloadJson = json_encode($request->data());

        return str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === 987654321
            && str_contains($request['text'], 'En unos instantes se confirmará su pedido')
            && ! str_contains($payloadJson, 'lvh.me')
            && ! isset($request['reply_markup']['inline_keyboard'])
            && isset($request['reply_markup']['keyboard']);
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

it('evita lvh.me, localhost en inline_keyboard y callback_data sin manejador en entorno local', function () {
    Http::fake();
    config(['app.frontend_url' => 'http://localhost:4200']);

    $pedidoId = crearPrePedidoParaTelegram();
    $chatId = 445566;

    // 1. Confirmar con /start <id>
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    // 2. Reenviar "Pedido #N" sobre un pedido ya confirmado
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "Pedido #{$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'already_confirmed');

    // 3. Solicitar "🌐 Ver Menú"
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '🌐 Ver Menú',
        ],
    ])->assertOk()->assertJsonPath('status', 'menu_sent');

    // 4. Consultar /estado (con pedido vinculado) y para un chat sin pedidos
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '/estado',
        ],
    ])->assertOk()->assertJsonPath('status', 'order_status_sent');

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 991122],
            'text' => '/estado',
        ],
    ])->assertOk()->assertJsonPath('status', 'no_orders_found');

    // 5. Consultar un pedido inexistente "Pedido #999"
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => 'Pedido #999',
        ],
    ])->assertOk()->assertJsonPath('status', 'not_found');

    Http::assertSentCount(6);

    Http::assertSent(function ($request) use ($chatId) {
        return $request['chat_id'] === $chatId
            && str_contains($request['text'], '<code>http://localhost:4200/menu</code>');
    });

    Http::assertNotSent(function ($request) {
        $payloadJson = json_encode($request->data());
        $inlineKeyboardJson = json_encode($request['reply_markup']['inline_keyboard'] ?? []);

        return str_contains($payloadJson, 'lvh.me')
            || str_contains($payloadJson, 'callback_data')
            || str_contains($inlineKeyboardJson, 'localhost')
            || isset($request['reply_markup']['inline_keyboard']);
    });
});

it('adjunta inline_keyboard con URL publica HTTPS cuando FRONTEND_URL es publica', function () {
    Http::fake();
    config(['app.frontend_url' => 'https://laposta.example.com']);

    $pedidoId = crearPrePedidoParaTelegram();
    $chatId = 667788;

    // Confirmación inicial -> /carrito
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    // Reenvío ya confirmado -> raíz pública sin callback_data
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "Pedido #{$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'already_confirmed');

    // Ver Menú -> enlace HTML y botón inline hacia /menu
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '🌐 Ver Menú',
        ],
    ])->assertOk()->assertJsonPath('status', 'menu_sent');

    // Consultar estado -> botón inline público
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '/estado',
        ],
    ])->assertOk()->assertJsonPath('status', 'order_status_sent');

    // Pedido no encontrado -> botón inline público
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => 'Pedido #999',
        ],
    ])->assertOk()->assertJsonPath('status', 'not_found');

    Http::assertSentCount(5);

    Http::assertSent(function ($request) {
        return str_contains($request['text'], 'En unos instantes se confirmará su pedido')
            && ($request['reply_markup']['inline_keyboard'][0][0]['url'] ?? null) === 'https://laposta.example.com/carrito';
    });

    Http::assertSent(function ($request) {
        return str_contains($request['text'], 'ya se encuentra registrado con estado')
            && ($request['reply_markup']['inline_keyboard'][0][0]['url'] ?? null) === 'https://laposta.example.com'
            && ! str_contains(json_encode($request->data()), 'callback_data');
    });

    Http::assertSent(function ($request) {
        return str_contains($request['text'], '<a href="https://laposta.example.com/menu">https://laposta.example.com/menu</a>')
            && ($request['reply_markup']['inline_keyboard'][0][0]['url'] ?? null) === 'https://laposta.example.com/menu';
    });

    Http::assertSent(function ($request) {
        return str_contains($request['text'], 'Estado de tu Pedido')
            && ($request['reply_markup']['inline_keyboard'][0][0]['url'] ?? null) === 'https://laposta.example.com';
    });

    Http::assertSent(function ($request) {
        return str_contains($request['text'], 'No pudimos encontrar el <b>Pedido #999</b>')
            && ($request['reply_markup']['inline_keyboard'][0][0]['url'] ?? null) === 'https://laposta.example.com';
    });
});
