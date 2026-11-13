<?php

namespace App\Services;

use App\Models\Pedido;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?string $botToken;

    public function __construct(?string $botToken = null)
    {
        $this->botToken = $botToken ?? config('services.telegram.bot_token');
    }

    /**
     * Enviar mensaje de texto a un chat específico de Telegram, con soporte para botones inline o reply markup.
     */
    public function enviarMensaje(int $chatId, string $texto, ?array $replyMarkup = null): bool
    {
        if (empty($this->botToken)) {
            Log::warning('[TelegramService] No se configuró TELEGRAM_BOT_TOKEN en el entorno.');

            return false;
        }

        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $texto,
                'parse_mode' => 'HTML',
            ];

            if ($replyMarkup) {
                $payload['reply_markup'] = $replyMarkup;
            }

            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $payload);

            if (! $response->successful()) {
                Log::warning('[TelegramService] Telegram rechazó el envío.', ['status' => $response->status()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('[TelegramService] Error al enviar mensaje.', ['exception_type' => $e::class]);

            return false;
        }
    }

    /**
     * Notificar al cliente cuando su pedido cambia de estado.
     */
    public function notificarCambioEstado(Pedido $pedido): bool
    {
        if (! $pedido->telegram_chat_id) {
            return false;
        }

        $estadoNombre = $pedido->estadoPedido?->nombre ?? '';
        $mensaje = match (mb_strtolower(trim($estadoNombre))) {
            'sin asignar' => "✅ <b>¡Tu Pedido #{$pedido->id} fue confirmado!</b>\nEstamos asignando a nuestro equipo para iniciar su preparación.",
            'pendiente' => "👨‍🍳 <b>Pedido #{$pedido->id}:</b> Personal asignado. En breve comenzará su preparación.",
            'en preparación', 'en preparacion' => "🔥 <b>¡Tu Pedido #{$pedido->id} ya se está cocinando!</b>\nNuestros cocineros lo están preparando con dedicación.",
            'listo para entregar' => "📦 <b>Pedido #{$pedido->id} listo:</b> Ya está empacado esperando al repartidor.",
            'en camino' => "🛵 <b>¡Tu Pedido #{$pedido->id} va en camino!</b>\nEl repartidor se dirige a tu domicilio.",
            'entregado' => "🎉 <b>¡Pedido #{$pedido->id} entregado!</b>\n¡Que lo disfrutes mucho! Gracias por elegir La Posta.",
            'cancelado' => "❌ <b>Pedido #{$pedido->id} cancelado:</b> Tu pedido fue cancelado. Por favor comunicate con el restaurante si tenés dudas.",
            default => "ℹ️ <b>Pedido #{$pedido->id}:</b> El estado se actualizó a '{$estadoNombre}'."
        };

        return $this->enviarMensaje((int) $pedido->telegram_chat_id, $mensaje);
    }
}
