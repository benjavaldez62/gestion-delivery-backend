<?php

namespace App\Http\Controllers;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    #[OA\Post(
        path: '/api/telegram/webhook',
        summary: 'Webhook receptor de actualizaciones de Telegram Bot o n8n',
        tags: ['Telegram'],
        responses: [
            new OA\Response(response: 200, description: 'Mensaje procesado correctamente.'),
            new OA\Response(response: 422, description: 'No se pudo vincular o pedido no válido.'),
        ]
    )]
    public function handle(Request $request): JsonResponse
    {
        $message = $request->input('message') ?? $request->all();

        $chatId = $message['chat']['id'] ?? $request->input('chat_id');
        $text = trim((string) ($message['text'] ?? $request->input('text', '')));
        $username = $message['from']['username'] ?? $request->input('username');

        if (! $chatId || empty($text)) {
            return response()->json(['status' => 'ignored', 'message' => 'No chat_id or text provided'], 200);
        }

        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200')), '/');

        // Comando /estado o /mispedidos: consultar último pedido
        if (preg_match('/^\/?(estado|mispedidos|mis_pedidos)/i', $text)) {
            return $this->consultarUltimoEstado((int) $chatId, $frontendUrl);
        }

        // Buscar si el texto contiene "/start <id>" o "Pedido #<id>" o un número de pedido directo
        $pedidoId = null;
        if (preg_match('/\/start\s+(\d+)/i', $text, $matches)) {
            $pedidoId = (int) $matches[1];
        } elseif (preg_match('/pedido\s*#?\s*(\d+)/i', $text, $matches)) {
            $pedidoId = (int) $matches[1];
        } elseif (is_numeric($text) && (int) $text > 0) {
            $pedidoId = (int) $text;
        }

        if (! $pedidoId) {
            // Anti-spam de saludo con Cache (evita enviar mensaje largo repetidas veces al mismo chat)
            $cacheKey = "tg_welcome_sent_{$chatId}";
            $tecladoPrincipal = [
                'keyboard' => [
                    [['text' => '📦 ¿Cómo viene mi pedido?'], ['text' => '🌐 Ver Menú']],
                ],
                'resize_keyboard' => true,
            ];

            if (preg_match('/men[uú]/i', $text)) {
                $this->telegramService->enviarMensaje(
                    (int) $chatId,
                    "🍕 Podés ver nuestra carta completa y hacer tu pedido en:\n<a href=\"{$frontendUrl}\">{$frontendUrl}</a>",
                    [
                        'inline_keyboard' => [
                            [['text' => '🌐 Abrir Menú Online', 'url' => $frontendUrl]],
                        ],
                    ]
                );

                return response()->json(['status' => 'menu_sent'], 200);
            }

            if (preg_match('/c[oó]mo viene/i', $text)) {
                return $this->consultarUltimoEstado((int) $chatId, $frontendUrl);
            }

            if (! Cache::has($cacheKey)) {
                Cache::put($cacheKey, true, now()->addHours(2));
                $this->telegramService->enviarMensaje(
                    (int) $chatId,
                    "👋 ¡Hola! Bienvenido a <b>La Posta Delivery</b>.\n\nPara confirmar un pedido nuevo, envianos el mensaje con el número de tu orden (por ejemplo: <code>Pedido #12</code> o abrí el enlace de confirmación desde nuestra web).\n\nTambién podés consultar el estado de tu orden actual cuando quieras.",
                    $tecladoPrincipal
                );
            } else {
                $this->telegramService->enviarMensaje(
                    (int) $chatId,
                    "ℹ️ Para confirmar una orden envianos <code>Pedido #número</code>, o tocá <b>¿Cómo viene mi pedido?</b> para ver el estado actual.",
                    $tecladoPrincipal
                );
            }

            return response()->json(['status' => 'info_sent'], 200);
        }

        $pedido = Pedido::with(['estadoPedido', 'cliente'])->find($pedidoId);
        if (! $pedido) {
            $this->telegramService->enviarMensaje(
                (int) $chatId,
                "⚠️ No pudimos encontrar el <b>Pedido #{$pedidoId}</b>. Por favor verificá el número ingresado.",
                [
                    'inline_keyboard' => [
                        [['text' => '🌐 Ir a La Posta Web', 'url' => $frontendUrl]],
                    ],
                ]
            );

            return response()->json(['status' => 'not_found', 'pedido_id' => $pedidoId], 200);
        }

        $estadoActual = mb_strtolower(trim($pedido->estadoPedido?->nombre ?? ''));

        // Si ya está confirmado / en preparación / etc
        if ($estadoActual !== 'pre-pedido') {
            $this->telegramService->enviarMensaje(
                (int) $chatId,
                "ℹ️ El <b>Pedido #{$pedidoId}</b> ya se encuentra registrado con estado: <b>{$pedido->estadoPedido?->nombre}</b>.",
                [
                    'inline_keyboard' => [
                        [['text' => '📦 Consultar Estado Actual', 'callback_data' => "estado_{$pedido->id}"], ['text' => '🌐 Abrir Web', 'url' => $frontendUrl]],
                    ],
                ]
            );

            return response()->json(['status' => 'already_confirmed', 'estado' => $pedido->estadoPedido?->nombre], 200);
        }

        // Transicionar a Sin Asignar y guardar datos de Telegram
        $estadoSinAsignar = EstadoPedido::whereRaw("LOWER(TRIM(nombre)) = 'sin asignar'")->first()
            ?? EstadoPedido::firstOrCreate(
                ['nombre' => 'Sin Asignar'],
                ['descripcion' => 'El pedido fue creado y está pendiente de asignación de un recurso']
            );

        $pedido->estado_pedidos_id = $estadoSinAsignar->id;
        $pedido->telegram_chat_id = (int) $chatId;
        if (! empty($username)) {
            $pedido->telegram_username = ltrim(trim($username), '@');
        }
        $pedido->save();

        if ($pedido->cliente && ! empty($username)) {
            $usernameTruncado = mb_substr(ltrim(trim($username), '@'), 0, 30);
            if ($usernameTruncado !== '') {
                $pedido->cliente->username = $usernameTruncado;
                $pedido->cliente->save();
            }
        }

        // Responder confirmación amigable con Inline Keyboard interactivo
        $botonesConfirmacion = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 Ver en la Web', 'url' => "{$frontendUrl}/carrito"],
                ],
            ],
        ];

        $this->telegramService->enviarMensaje(
            (int) $chatId,
            "🎉 <b>¡Tu Pedido #{$pedido->id} fue confirmado con éxito!</b>\n\n💰 Total: <b>$".number_format((float) $pedido->monto_total, 2, ',', '.')."</b>\n\nTe iremos notificando por acá cada avance (en cocina, empaque y en camino con el repartidor).",
            $botonesConfirmacion
        );

        return response()->json([
            'status' => 'confirmed',
            'pedido_id' => $pedido->id,
            'estado' => 'Sin Asignar',
        ], 200);
    }

    /**
     * Consulta el último pedido vinculado al chat y responde su estado en tiempo real.
     */
    protected function consultarUltimoEstado(int $chatId, string $frontendUrl): JsonResponse
    {
        $ultimoPedido = Pedido::with('estadoPedido')
            ->where('telegram_chat_id', $chatId)
            ->orderBy('id', 'desc')
            ->first();

        if (! $ultimoPedido) {
            $this->telegramService->enviarMensaje(
                $chatId,
                "🔍 No encontramos ningún pedido vinculado a este chat de Telegram.\nSi hiciste un pedido en la web, envianos el mensaje con el número de orden (ej: <code>Pedido #12</code>) para vincularlo.",
                [
                    'inline_keyboard' => [
                        [['text' => '🌐 Ir al Menú Online', 'url' => $frontendUrl]],
                    ],
                ]
            );

            return response()->json(['status' => 'no_orders_found'], 200);
        }

        $estadoNombre = $ultimoPedido->estadoPedido?->nombre ?? 'En proceso';
        $emoji = match (mb_strtolower(trim($estadoNombre))) {
            'sin asignar' => '⏳',
            'pendiente' => '👨‍🍳',
            'en preparación', 'en preparacion' => '🔥',
            'listo para entregar' => '📦',
            'en camino' => '🛵',
            'entregado' => '🎉',
            'cancelado' => '❌',
            default => 'ℹ️',
        };

        $this->telegramService->enviarMensaje(
            $chatId,
            "📦 <b>Estado de tu Pedido #{$ultimoPedido->id}:</b>\n\n{$emoji} Estado actual: <b>{$estadoNombre}</b>\n💰 Total: <b>$".number_format((float) $ultimoPedido->monto_total, 2, ',', '.')."</b>",
            [
                'inline_keyboard' => [
                    [['text' => '🌐 Abrir La Posta Web', 'url' => $frontendUrl]],
                ],
            ]
        );

        return response()->json(['status' => 'order_status_sent', 'pedido_id' => $ultimoPedido->id, 'estado' => $estadoNombre], 200);
    }
}
