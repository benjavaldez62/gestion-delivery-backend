<?php

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\EstadoPagos;
use App\Models\EstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

it('responde estado del pedido al presionar el boton exacto con tilde y emoji sin caer en el fallback anti-spam', function () {
    Http::fake();

    $pedidoId = crearPrePedidoParaTelegram();
    $chatId = 555777;

    // Vincular el pedido al chat y dejar activa la marca anti-spam en caché
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    Cache::put("tg_welcome_sent_{$chatId}", true, now()->addHours(2));

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '📦 ¿Cómo viene mi pedido?',
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'order_status_sent')
        ->assertJsonPath('pedido_id', $pedidoId)
        ->assertJsonPath('estado', 'Sin Asignar');

    Http::assertSent(function ($request) use ($chatId, $pedidoId) {
        return $request['chat_id'] === $chatId
            && str_contains($request['text'], "Estado de tu Pedido #{$pedidoId}")
            && str_contains($request['text'], 'Sin Asignar');
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request['text'] ?? '', 'o tocá <b>¿Cómo viene mi pedido?</b>');
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

it('permite cancelar un pedido desde Telegram en estado Sin Asignar o Pendiente y anula el pago pendiente', function (string $estadoInicial) {
    Http::fake();

    EstadoPedido::firstOrCreate(['nombre' => 'Cancelado'], ['descripcion' => 'El pedido fue cancelado']);
    $pedidoId = crearPrePedidoParaTelegram();
    $chatId = 801001;

    // Confirmar el pre-pedido para vincular el chat y registrar el pago pendiente en efectivo
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    $pedido = Pedido::findOrFail($pedidoId);
    if ($estadoInicial !== 'Sin Asignar') {
        $estado = EstadoPedido::firstOrCreate(['nombre' => $estadoInicial]);
        $pedido->update(['estado_pedidos_id' => $estado->id]);
    }

    $pago = Pago::where('pedido_id', $pedidoId)->firstOrFail();

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '❌ Cancelar mi pedido',
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'cancelled')
        ->assertJsonPath('pedido_id', $pedidoId)
        ->assertJsonPath('estado', 'Cancelado');

    expect($pedido->fresh()->estadoPedido->nombre)->toBe('Cancelado')
        ->and(Pago::find($pago->id))->toBeNull()
        ->and(Pago::withTrashed()->findOrFail($pago->id)->trashed())->toBeTrue();

    Http::assertSent(function ($request) use ($chatId, $pedidoId) {
        return $request['chat_id'] === $chatId
            && str_contains($request['text'], "Pedido #{$pedidoId} cancelado correctamente")
            && isset($request['reply_markup']['keyboard']);
    });

    // Reintentar cancelación informa que ya se encuentra cancelado
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "Cancelar pedido #{$pedidoId}",
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'already_cancelled')
        ->assertJsonPath('pedido_id', $pedidoId);
})->with(['Sin Asignar', 'Pendiente']);

it('rechaza cancelar desde Telegram cuando el pedido ya pasó a En preparación, Listo para entregar o En camino', function (string $estadoBloqueado) {
    Http::fake();

    EstadoPedido::firstOrCreate(['nombre' => 'Cancelado'], ['descripcion' => 'El pedido fue cancelado']);
    $pedidoId = crearPrePedidoParaTelegram();
    $chatId = 802002;

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    $estado = EstadoPedido::firstOrCreate(['nombre' => $estadoBloqueado]);
    $pedido = Pedido::findOrFail($pedidoId);
    $pedido->update(['estado_pedidos_id' => $estado->id]);
    $pago = Pago::where('pedido_id', $pedidoId)->firstOrFail();

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '❌ Cancelar mi pedido',
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'cancel_not_allowed')
        ->assertJsonPath('pedido_id', $pedidoId)
        ->assertJsonPath('estado', $estadoBloqueado);

    expect($pedido->fresh()->estadoPedido->nombre)->toBe($estadoBloqueado)
        ->and(Pago::find($pago->id))->not->toBeNull();

    Http::assertSent(function ($request) use ($chatId, $pedidoId, $estadoBloqueado) {
        return $request['chat_id'] === $chatId
            && str_contains($request['text'], "No se puede cancelar el <b>Pedido #{$pedidoId}</b>")
            && str_contains($request['text'], $estadoBloqueado)
            && str_contains($request['text'], 'antes de que pase a preparación');
    });
})->with(['En preparación', 'Listo para entregar', 'En camino']);

it('impide que otro chat_id cancele un pedido ajeno mediante /cancelar <id>', function () {
    Http::fake();

    EstadoPedido::firstOrCreate(['nombre' => 'Cancelado'], ['descripcion' => 'El pedido fue cancelado']);
    $pedidoId = crearPrePedidoParaTelegram();
    $chatPropietario = 803003;
    $chatIntruso = 803999;

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatPropietario],
            'text' => "/start {$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatIntruso],
            'text' => "/cancelar {$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'no_orders_found');

    expect(Pedido::findOrFail($pedidoId)->estadoPedido->nombre)->toBe('Sin Asignar');
});

it('no cancela desde Telegram un pedido que ya tiene un pago confirmado', function () {
    Http::fake();

    EstadoPedido::firstOrCreate(['nombre' => 'Cancelado'], ['descripcion' => 'El pedido fue cancelado']);
    $pedidoId = crearPrePedidoParaTelegram();
    $chatId = 804004;

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    $estadoPagado = EstadoPagos::firstOrCreate(['nombre' => 'Pagado']);
    $pago = Pago::where('pedido_id', $pedidoId)->firstOrFail();
    $pago->update(['estado_pago_id' => $estadoPagado->id, 'fecha_pago' => now()->toDateString()]);

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '❌ Cancelar mi pedido',
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'cancel_not_allowed')
        ->assertJsonPath('pedido_id', $pedidoId);

    expect(Pedido::findOrFail($pedidoId)->estadoPedido->nombre)->toBe('Sin Asignar')
        ->and(Pago::find($pago->id))->not->toBeNull();
});

it('PUT /api/pedidos/{id} rechaza pasar a Cancelado con HTTP 400 desde En preparación, Listo para entregar o En camino y permite Sin Asignar o Pendiente', function () {
    Http::fake();

    $rolAdmin = Role::firstOrCreate(['nombre' => 'Administrador']);
    $admin = User::factory()->create(['role_id' => $rolAdmin->id, 'activo' => true]);
    $estadoCancelado = EstadoPedido::firstOrCreate(['nombre' => 'Cancelado'], ['descripcion' => 'El pedido fue cancelado']);

    $this->actingAs($admin, 'sanctum');

    foreach (['En preparación', 'Listo para entregar', 'En camino'] as $estadoBloqueado) {
        $pedidoId = crearPrePedidoParaTelegram();
        $estado = EstadoPedido::firstOrCreate(['nombre' => $estadoBloqueado]);
        $pedido = Pedido::findOrFail($pedidoId);
        $pedido->update(['estado_pedidos_id' => $estado->id]);

        $this->putJson("/api/pedidos/{$pedidoId}", [
            'estado_pedidos_id' => $estadoCancelado->id,
        ])->assertStatus(400)
            ->assertJsonPath('message', 'Solo se puede cancelar un pedido antes de que pase a En preparación.');

        expect($pedido->fresh()->estadoPedido->nombre)->toBe($estadoBloqueado);
    }

    foreach (['Sin Asignar', 'Pendiente'] as $estadoPermitido) {
        $pedidoId = crearPrePedidoParaTelegram();
        $estado = EstadoPedido::firstOrCreate(['nombre' => $estadoPermitido]);
        $pedido = Pedido::findOrFail($pedidoId);
        $pedido->update(['estado_pedidos_id' => $estado->id]);

        $this->putJson("/api/pedidos/{$pedidoId}", [
            'estado_pedidos_id' => $estadoCancelado->id,
        ])->assertOk();

        expect($pedido->fresh()->estadoPedido->nombre)->toBe('Cancelado');
    }
});

it('lista comandos clickeables /cancelar_<id> cuando el chat tiene multiples pedidos cancelables', function () {
    Http::fake();

    EstadoPedido::firstOrCreate(['nombre' => 'Cancelado'], ['descripcion' => 'El pedido fue cancelado']);
    $estadoPendiente = EstadoPedido::firstOrCreate(['nombre' => 'Pendiente'], ['descripcion' => 'Pedido asignado pendiente de preparación']);

    $chatId = 805005;
    $pedido1Id = crearPrePedidoParaTelegram();
    $pedido2Id = crearPrePedidoParaTelegram();

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedido1Id}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedido2Id}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    // Dejar un pedido en Pendiente y el otro en Sin Asignar
    Pedido::findOrFail($pedido1Id)->update(['estado_pedidos_id' => $estadoPendiente->id]);

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '❌ Cancelar mi pedido',
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'cancel_selection_required')
        ->assertJsonPath('pedidos_cancelables', [$pedido2Id, $pedido1Id]);

    expect(Pedido::findOrFail($pedido1Id)->estadoPedido->nombre)->toBe('Pendiente')
        ->and(Pedido::findOrFail($pedido2Id)->estadoPedido->nombre)->toBe('Sin Asignar');

    Http::assertSent(function ($request) use ($chatId, $pedido1Id, $pedido2Id) {
        $text = $request['text'] ?? '';

        return $request['chat_id'] === $chatId
            && str_contains($text, 'Tenés más de un pedido disponible para cancelar')
            && str_contains($text, "/cancelar_{$pedido1Id}")
            && str_contains($text, "/cancelar_{$pedido2Id}")
            && ! str_contains($text, "<code>/cancelar_{$pedido1Id}</code>")
            && ! str_contains($text, "<code>/cancelar_{$pedido2Id}</code>")
            && isset($request['reply_markup']['keyboard']);
    });
});

it('cancela unicamente el pedido indicado con /cancelar_<id> y al quedar uno cancelable lo cancela directo aunque haya otro mas reciente cancelado', function () {
    Http::fake();

    EstadoPedido::firstOrCreate(['nombre' => 'Cancelado'], ['descripcion' => 'El pedido fue cancelado']);
    $estadoPendiente = EstadoPedido::firstOrCreate(['nombre' => 'Pendiente'], ['descripcion' => 'Pedido asignado pendiente de preparación']);

    $chatId = 806006;
    $pedido1Id = crearPrePedidoParaTelegram();
    $pedido2Id = crearPrePedidoParaTelegram();

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedido1Id}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/start {$pedido2Id}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    Pedido::findOrFail($pedido1Id)->update(['estado_pedidos_id' => $estadoPendiente->id]);

    $pago1 = Pago::where('pedido_id', $pedido1Id)->firstOrFail();
    $pago2 = Pago::where('pedido_id', $pedido2Id)->firstOrFail();

    // 1. Cancelar únicamente el pedido más reciente mediante /cancelar_<id>
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => "/cancelar_{$pedido2Id}",
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'cancelled')
        ->assertJsonPath('pedido_id', $pedido2Id)
        ->assertJsonPath('estado', 'Cancelado');

    expect(Pedido::findOrFail($pedido2Id)->estadoPedido->nombre)->toBe('Cancelado')
        ->and(Pago::find($pago2->id))->toBeNull()
        ->and(Pago::withTrashed()->findOrFail($pago2->id)->trashed())->toBeTrue()
        ->and(Pedido::findOrFail($pedido1Id)->estadoPedido->nombre)->toBe('Pendiente')
        ->and(Pago::find($pago1->id))->not->toBeNull();

    // 2. Presionar nuevamente "❌ Cancelar mi pedido" cancela directamente el único pedido cancelable restante
    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '❌ Cancelar mi pedido',
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'cancelled')
        ->assertJsonPath('pedido_id', $pedido1Id)
        ->assertJsonPath('estado', 'Cancelado');

    expect(Pedido::findOrFail($pedido1Id)->estadoPedido->nombre)->toBe('Cancelado')
        ->and(Pago::find($pago1->id))->toBeNull()
        ->and(Pago::withTrashed()->findOrFail($pago1->id)->trashed())->toBeTrue();
});
