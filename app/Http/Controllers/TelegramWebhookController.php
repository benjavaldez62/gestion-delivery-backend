<?php

namespace App\Http\Controllers;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $nombre = trim(($message['from']['first_name'] ?? '').' '.($message['from']['last_name'] ?? '')) ?: $request->input('nombre');

        if (! $chatId || empty($text)) {
            return response()->json(['status' => 'ignored', 'message' => 'No chat_id or text provided'], 200);
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
            $this->telegramService->enviarMensaje((int) $chatId, "👋 ¡Hola! Bienvenido a <b>La Posta Delivery</b>.\nPara confirmar tu pedido, envianos el mensaje con el número de tu orden (por ejemplo: <code>Pedido #12</code> o hacé clic en el enlace desde la web).");

            return response()->json(['status' => 'info_sent'], 200);
        }

        $pedido = Pedido::with(['estadoPedido', 'cliente'])->find($pedidoId);
        if (! $pedido) {
            $this->telegramService->enviarMensaje((int) $chatId, "⚠️ No pudimos encontrar el <b>Pedido #{$pedidoId}</b>. Por favor verificá el número ingresado.");

            return response()->json(['status' => 'not_found', 'pedido_id' => $pedidoId], 200);
        }

        $estadoActual = mb_strtolower(trim($pedido->estadoPedido?->nombre ?? ''));

        // Si ya está confirmado / en preparación / etc
        if ($estadoActual !== 'pre-pedido') {
            $this->telegramService->enviarMensaje((int) $chatId, "ℹ️ El <b>Pedido #{$pedidoId}</b> ya se encuentra registrado con estado: <b>{$pedido->estadoPedido?->nombre}</b>.");

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

        // Responder confirmación amigable
        $this->telegramService->enviarMensaje(
            (int) $chatId,
            "🎉 <b>¡Tu Pedido #{$pedido->id} fue confirmado con éxito!</b>\n\n💰 Total: <b>$".number_format((float) $pedido->monto_total, 2, ',', '.')."</b>\nTe iremos notificando por acá el avance de tu comida (en cocina, empaque y en viaje con el repartidor)."
        );

        return response()->json([
            'status' => 'confirmed',
            'pedido_id' => $pedido->id,
            'estado' => 'Sin Asignar',
        ], 200);
    }
}
