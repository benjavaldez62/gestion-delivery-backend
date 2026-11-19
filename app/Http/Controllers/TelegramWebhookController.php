<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Services\PagoEfectivoService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $usernameNormalizado = $this->normalizarUsernameTelegram(is_string($username) ? $username : null);

        if (! $chatId || empty($text)) {
            return response()->json(['status' => 'ignored', 'message' => 'No chat_id or text provided'], 200);
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200')), '/');

        // Comando /estado, /estado_<id>, /mispedidos: consultar pedido(s) del chat o usuario
        if (preg_match('/^\/?(?:estado(?:@[a-z0-9_]+)?(?:[\s_#]+\d+)?|mispedidos|mis_pedidos)(?:@[a-z0-9_]+)?(?:\s|$)/iu', $text)) {
            return $this->consultarEstadoDesdeTelegram((int) $chatId, $usernameNormalizado, $text, $frontendUrl);
        }

        // Comando o botón de cancelación (antes de extraer números de pedido para evitar confirmar al escribir "Cancelar pedido #N")
        if (
            preg_match('/^\/?cancelar(?:[\s_#]+\d+)?(?:@[a-z0-9_]+)?\b/iu', $text)
            || preg_match('/cancelar\s+(mi\s+)?pedido/iu', $text)
        ) {
            return $this->cancelarPedidoDesdeTelegram((int) $chatId, $text);
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
            $tecladoPrincipal = $this->tecladoPrincipal();

            if (preg_match('/men[uú]/iu', $text)) {
                $urlMenu = "{$frontendUrl}/menu";
                $enlaceMenu = $this->esUrlPublicaParaTelegram($urlMenu)
                    ? "<a href=\"{$urlMenu}\">{$urlMenu}</a>"
                    : "<code>{$urlMenu}</code>";

                $this->telegramService->enviarMensaje(
                    (int) $chatId,
                    "🍕 <b>Menú Online — La Posta Delivery</b>\n\nPodés explorar todos nuestros platos, promociones y hacer tu pedido ingresando a:\n👉 {$enlaceMenu}",
                    $this->construirReplyMarkupWeb('🌐 Abrir Menú Online', $urlMenu)
                );

                return response()->json(['status' => 'menu_sent'], 200);
            }

            if (preg_match('/c[oó]mo\s+(?:viene|est[aá])/iu', $text)) {
                return $this->consultarEstadoDesdeTelegram((int) $chatId, $usernameNormalizado, $text, $frontendUrl);
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
                    'ℹ️ Para confirmar una orden envianos <code>Pedido #número</code>, o tocá <b>¿Cómo viene mi pedido?</b> para ver el estado actual.',
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
                $this->construirReplyMarkupWeb('🌐 Ir a La Posta Web', $frontendUrl)
            );

            return response()->json(['status' => 'not_found', 'pedido_id' => $pedidoId], 200);
        }

        $estadoActual = mb_strtolower(trim($pedido->estadoPedido?->nombre ?? ''));

        // Si ya está confirmado / en preparación / etc: bloquear consulta si no coincide chat o username
        if ($estadoActual !== 'pre-pedido') {
            if (! $this->pedidoPerteneceAChatOUsuario($pedido, (int) $chatId, $usernameNormalizado)) {
                $this->telegramService->enviarMensaje(
                    (int) $chatId,
                    "🔒 No tenés permiso para consultar el estado del <b>Pedido #{$pedidoId}</b>. Solo el chat o usuario de Telegram vinculado al pedido puede acceder a esta información.",
                    $this->tecladoPrincipal()
                );

                return response()->json([
                    'status' => 'unauthorized',
                    'pedido_id' => $pedidoId,
                ], 200);
            }

            $this->telegramService->enviarMensaje(
                (int) $chatId,
                "ℹ️ El <b>Pedido #{$pedidoId}</b> ya se encuentra registrado con estado: <b>{$pedido->estadoPedido?->nombre}</b>.",
                $this->construirReplyMarkupWeb('🌐 Abrir Web', $frontendUrl)
            );

            return response()->json(['status' => 'already_confirmed', 'estado' => $pedido->estadoPedido?->nombre], 200);
        }

        $pedido = DB::transaction(function () use ($pedidoId, $chatId, $username): ?Pedido {
            $pedido = Pedido::with(['estadoPedido', 'cliente'])->lockForUpdate()->findOrFail($pedidoId);
            if (mb_strtolower(trim($pedido->estadoPedido?->nombre ?? '')) !== 'pre-pedido') {
                return null;
            }

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
                $existeOtro = Cliente::withTrashed()->where('username', $usernameTruncado)
                    ->where('id', '!=', $pedido->cliente->id)->exists();
                if ($usernameTruncado !== '' && ! $existeOtro) {
                    $pedido->cliente->update(['username' => $usernameTruncado]);
                }
            }

            app(PagoEfectivoService::class)->registrarPendiente($pedido);

            return $pedido;
        });

        if (! $pedido) {
            $this->telegramService->enviarMensaje(
                (int) $chatId,
                "ℹ️ El <b>Pedido #{$pedidoId}</b> ya se encuentra confirmado.",
                $this->construirReplyMarkupWeb('🌐 Abrir Web', $frontendUrl)
            );

            return response()->json(['status' => 'already_confirmed'], 200);
        }

        $this->telegramService->enviarMensaje(
            (int) $chatId,
            "⏳ <b>Pedido #{$pedido->id}:</b> En unos instantes se confirmará su pedido.\n\n💰 Total: <b>$".number_format((float) $pedido->monto_total, 2, ',', '.')."</b>\n\nTe iremos notificando por acá cada avance (en cocina, empaque y en camino con el repartidor).",
            $this->construirReplyMarkupWeb('🌐 Ver en la Web', "{$frontendUrl}/carrito")
        );

        return response()->json([
            'status' => 'confirmed',
            'pedido_id' => $pedido->id,
            'estado' => 'Sin Asignar',
        ], 200);
    }

    /**
     * Construye el teclado de respuestas rápidas principal del bot.
     */
    protected function tecladoPrincipal(): array
    {
        return [
            'keyboard' => [
                [['text' => '📦 ¿Cómo viene mi pedido?'], ['text' => '🌐 Ver Menú']],
                [['text' => '❌ Cancelar mi pedido']],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * Determina si una URL es pública y válida para ser enviada dentro de un botón inline de Telegram.
     */
    protected function esUrlPublicaParaTelegram(string $url): bool
    {
        $componentes = parse_url($url);
        if (! is_array($componentes)) {
            return false;
        }

        $esquema = strtolower((string) ($componentes['scheme'] ?? ''));
        $host = strtolower(trim((string) ($componentes['host'] ?? ''), '[]'));

        if (! in_array($esquema, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return false;
        }

        return true;
    }

    /**
     * Construye un botón inline con enlace web si la URL es pública; en entornos locales devuelve el teclado principal.
     */
    protected function construirReplyMarkupWeb(string $textoBoton, string $url): array
    {
        if ($this->esUrlPublicaParaTelegram($url)) {
            return [
                'inline_keyboard' => [
                    [['text' => $textoBoton, 'url' => $url]],
                ],
            ];
        }

        return $this->tecladoPrincipal();
    }

    /**
     * Normaliza un username de Telegram removiendo arroba inicial y pasando a minúsculas.
     */
    protected function normalizarUsernameTelegram(?string $username): string
    {
        return mb_strtolower(ltrim(trim((string) $username), '@'));
    }

    /**
     * Verifica si el pedido pertenece al chat actual o al username de Telegram informado.
     */
    protected function pedidoPerteneceAChatOUsuario(Pedido $pedido, int $chatId, string $usernameNormalizado): bool
    {
        if ((int) ($pedido->telegram_chat_id ?? 0) === $chatId) {
            return true;
        }

        $pedidoUsername = $this->normalizarUsernameTelegram($pedido->telegram_username);

        return $usernameNormalizado !== ''
            && $pedidoUsername !== ''
            && $pedidoUsername === $usernameNormalizado;
    }

    /**
     * Restringe la consulta de pedidos al chat actual o al username de Telegram no vacío.
     */
    protected function filtrarPedidosPorChatOUsuario($query, int $chatId, string $usernameNormalizado): void
    {
        $query->where(function ($subQuery) use ($chatId, $usernameNormalizado): void {
            $subQuery->where('telegram_chat_id', $chatId);

            if ($usernameNormalizado !== '') {
                $subQuery->orWhereRaw("LOWER(TRIM(COALESCE(telegram_username, ''))) IN (?, ?)", [
                    $usernameNormalizado,
                    '@'.$usernameNormalizado,
                ]);
            }
        });
    }

    /**
     * Devuelve el emoji representativo para el estado actual del pedido.
     */
    protected function emojiEstadoPedido(string $estadoNombre): string
    {
        return match (mb_strtolower(trim($estadoNombre))) {
            'sin asignar' => '⏳',
            'pendiente' => '👨‍🍳',
            'en preparación', 'en preparacion' => '🔥',
            'listo para entregar' => '📦',
            'en camino' => '🛵',
            'entregado' => '🎉',
            'cancelado' => '❌',
            default => 'ℹ️',
        };
    }

    /**
     * Formatea una colección de pedidos como lista con comandos clickeables de Telegram.
     *
     * @param  Collection<int, Pedido>  $pedidos
     */
    protected function formatearLineasPedidos(Collection $pedidos, string $comandoPrefijo): string
    {
        return $pedidos->map(function (Pedido $pedido) use ($comandoPrefijo): string {
            $estado = $pedido->estadoPedido?->nombre ?? 'En proceso';
            $emoji = $this->emojiEstadoPedido($estado);
            $total = number_format((float) $pedido->monto_total, 2, ',', '.');

            return "• <b>Pedido #{$pedido->id}</b> ({$emoji} {$estado} — \${$total}) ➔ /{$comandoPrefijo}_{$pedido->id}";
        })->implode("\n");
    }

    /**
     * Envía la respuesta formateada con el estado en tiempo real de un pedido autorizado.
     */
    protected function responderEstadoPedido(int $chatId, Pedido $pedido, string $frontendUrl): JsonResponse
    {
        $estadoNombre = $pedido->estadoPedido?->nombre ?? 'En proceso';
        $emoji = $this->emojiEstadoPedido($estadoNombre);

        $this->telegramService->enviarMensaje(
            $chatId,
            "📦 <b>Estado de tu Pedido #{$pedido->id}:</b>\n\n{$emoji} Estado actual: <b>{$estadoNombre}</b>\n💰 Total: <b>$".number_format((float) $pedido->monto_total, 2, ',', '.').'</b>',
            $this->construirReplyMarkupWeb('🌐 Abrir La Posta Web', $frontendUrl)
        );

        return response()->json([
            'status' => 'order_status_sent',
            'pedido_id' => $pedido->id,
            'estado' => $estadoNombre,
        ], 200);
    }

    /**
     * Consulta los pedidos activos vinculados al chat o username; si hay múltiples lista comandos /estado_<id>.
     */
    protected function consultarEstadoDesdeTelegram(int $chatId, string $usernameNormalizado, string $text, string $frontendUrl): JsonResponse
    {
        $pedidoIdSolicitado = null;
        if (preg_match('/estado(?:@[a-z0-9_]+)?[\s_#]+(\d+)/iu', $text, $matches)) {
            $pedidoIdSolicitado = (int) $matches[1];
        }

        if ($pedidoIdSolicitado !== null) {
            $pedido = Pedido::with('estadoPedido')->find($pedidoIdSolicitado);

            if (! $pedido) {
                $this->telegramService->enviarMensaje(
                    $chatId,
                    "⚠️ No pudimos encontrar el <b>Pedido #{$pedidoIdSolicitado}</b>.",
                    $this->tecladoPrincipal()
                );

                return response()->json(['status' => 'no_orders_found'], 200);
            }

            if (! $this->pedidoPerteneceAChatOUsuario($pedido, $chatId, $usernameNormalizado)) {
                $this->telegramService->enviarMensaje(
                    $chatId,
                    "🔒 No tenés permiso para consultar el estado del <b>Pedido #{$pedido->id}</b>. Solo el chat o usuario de Telegram vinculado al pedido puede acceder a esta información.",
                    $this->tecladoPrincipal()
                );

                return response()->json([
                    'status' => 'unauthorized',
                    'pedido_id' => $pedido->id,
                ], 200);
            }

            return $this->responderEstadoPedido($chatId, $pedido, $frontendUrl);
        }

        $pedidosActivos = Pedido::with('estadoPedido')
            ->tap(fn ($query) => $this->filtrarPedidosPorChatOUsuario($query, $chatId, $usernameNormalizado))
            ->whereHas('estadoPedido', function ($query): void {
                $query->whereRaw("LOWER(TRIM(nombre)) NOT IN ('pre-pedido', 'entregado', 'cancelado')");
            })
            ->orderBy('id', 'desc')
            ->get();

        if ($pedidosActivos->count() >= 2) {
            $lineas = $this->formatearLineasPedidos($pedidosActivos, 'estado');

            $this->telegramService->enviarMensaje(
                $chatId,
                "📋 <b>Tenés más de un pedido en curso:</b>\n\n{$lineas}\n\nTocá el comando del pedido que querés consultar.",
                $this->tecladoPrincipal()
            );

            return response()->json([
                'status' => 'status_selection_required',
                'pedidos_activos' => $pedidosActivos->pluck('id')->values()->all(),
            ], 200);
        }

        if ($pedidosActivos->count() === 1) {
            return $this->responderEstadoPedido($chatId, $pedidosActivos->first(), $frontendUrl);
        }

        $pedidosHistoricos = Pedido::with('estadoPedido')
            ->tap(fn ($query) => $this->filtrarPedidosPorChatOUsuario($query, $chatId, $usernameNormalizado))
            ->whereHas('estadoPedido', function ($query): void {
                $query->whereRaw("LOWER(TRIM(nombre)) IN ('entregado', 'cancelado')");
            })
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        if ($pedidosHistoricos->isNotEmpty()) {
            $lineas = $this->formatearLineasPedidos($pedidosHistoricos, 'estado');

            $this->telegramService->enviarMensaje(
                $chatId,
                "ℹ️ No tenés pedidos en curso en este momento.\n\n📋 <b>Tus últimos pedidos:</b>\n\n{$lineas}\n\nTocá el comando de un pedido si querés ver su detalle.",
                $this->tecladoPrincipal()
            );

            return response()->json([
                'status' => 'no_active_orders_history_sent',
                'pedidos_historicos' => $pedidosHistoricos->pluck('id')->values()->all(),
            ], 200);
        }

        $this->telegramService->enviarMensaje(
            $chatId,
            "🔍 No encontramos ningún pedido vinculado a este chat o usuario de Telegram.\nSi hiciste un pedido en la web, envianos el mensaje con el número de orden (ej: <code>Pedido #12</code>) para vincularlo.",
            $this->construirReplyMarkupWeb('🌐 Ir al Menú Online', $frontendUrl)
        );

        return response()->json(['status' => 'no_orders_found'], 200);
    }

    /**
     * Cancela un pedido vinculado al chat actual siempre que aún no haya pasado a preparación.
     */
    protected function cancelarPedidoDesdeTelegram(int $chatId, string $text): JsonResponse
    {
        $pedidoIdSolicitado = null;
        if (preg_match('/(?:cancelar(?:@[a-z0-9_]+)?[\s_#]+|pedido\s*#?\s*)(\d+)/iu', $text, $matches)) {
            $pedidoIdSolicitado = (int) $matches[1];
        }

        if ($pedidoIdSolicitado !== null) {
            $pedidoEncontrado = Pedido::where('id', $pedidoIdSolicitado)
                ->where('telegram_chat_id', $chatId)
                ->first();
        } else {
            $pedidosCancelables = Pedido::with('estadoPedido')
                ->where('telegram_chat_id', $chatId)
                ->whereHas('estadoPedido', function ($query): void {
                    $query->whereRaw("LOWER(TRIM(nombre)) IN ('pre-pedido', 'sin asignar', 'pendiente')");
                })
                ->orderBy('id', 'desc')
                ->get();

            if ($pedidosCancelables->count() >= 2) {
                $lineas = $this->formatearLineasPedidos($pedidosCancelables, 'cancelar');

                $this->telegramService->enviarMensaje(
                    $chatId,
                    "📋 <b>Tenés más de un pedido disponible para cancelar:</b>\n\n{$lineas}\n\nTocá el comando del pedido que querés cancelar.",
                    $this->tecladoPrincipal()
                );

                return response()->json([
                    'status' => 'cancel_selection_required',
                    'pedidos_cancelables' => $pedidosCancelables->pluck('id')->values()->all(),
                ], 200);
            }

            $pedidoEncontrado = $pedidosCancelables->count() === 1
                ? $pedidosCancelables->first()
                : Pedido::where('telegram_chat_id', $chatId)
                    ->orderBy('id', 'desc')
                    ->first();
        }

        if (! $pedidoEncontrado) {
            $mensajeNoEncontrado = $pedidoIdSolicitado !== null
                ? "⚠️ No pudimos encontrar el <b>Pedido #{$pedidoIdSolicitado}</b> vinculado a este chat de Telegram."
                : '🔍 No encontramos ningún pedido vinculado a este chat de Telegram para cancelar.';

            $this->telegramService->enviarMensaje($chatId, $mensajeNoEncontrado, $this->tecladoPrincipal());

            return response()->json(['status' => 'no_orders_found'], 200);
        }

        $resultado = DB::transaction(function () use ($pedidoEncontrado, $chatId): array {
            $pedido = Pedido::with('estadoPedido')
                ->where('id', $pedidoEncontrado->id)
                ->where('telegram_chat_id', $chatId)
                ->lockForUpdate()
                ->first();

            if (! $pedido) {
                return ['status' => 'no_orders_found'];
            }

            $estadoNombre = $pedido->estadoPedido?->nombre ?? 'Desconocido';
            $estadoActual = mb_strtolower(trim($estadoNombre));

            if ($estadoActual === 'cancelado') {
                return [
                    'status' => 'already_cancelled',
                    'pedido_id' => $pedido->id,
                    'estado' => $estadoNombre,
                ];
            }

            if (! in_array($estadoActual, ['pre-pedido', 'sin asignar', 'pendiente'], true)) {
                return [
                    'status' => 'cancel_not_allowed',
                    'pedido_id' => $pedido->id,
                    'estado' => $estadoNombre,
                    'motivo' => 'estado',
                ];
            }

            $pagos = $pedido->pagos()
                ->with(['estadoPago', 'comprobantes'])
                ->lockForUpdate()
                ->get();

            foreach ($pagos as $pago) {
                $estadoPago = mb_strtolower(trim($pago->estadoPago?->nombre ?? ''));
                if (! in_array($estadoPago, ['pendiente', 'fallido'], true) || $pago->comprobantes->isNotEmpty()) {
                    return [
                        'status' => 'cancel_not_allowed',
                        'pedido_id' => $pedido->id,
                        'estado' => $estadoNombre,
                        'motivo' => 'pago',
                    ];
                }
            }

            foreach ($pagos as $pago) {
                $pago->delete();
            }

            $estadoCancelado = EstadoPedido::whereRaw('LOWER(TRIM(nombre)) = ?', ['cancelado'])->firstOrFail();
            $pedido->estado_pedidos_id = $estadoCancelado->id;
            $pedido->save();

            return [
                'status' => 'cancelled',
                'pedido_id' => $pedido->id,
                'estado' => 'Cancelado',
            ];
        });

        if ($resultado['status'] === 'no_orders_found') {
            $this->telegramService->enviarMensaje(
                $chatId,
                '🔍 No encontramos ningún pedido vinculado a este chat de Telegram para cancelar.',
                $this->tecladoPrincipal()
            );

            return response()->json(['status' => 'no_orders_found'], 200);
        }

        if ($resultado['status'] === 'already_cancelled') {
            $this->telegramService->enviarMensaje(
                $chatId,
                "ℹ️ El <b>Pedido #{$resultado['pedido_id']}</b> ya se encuentra cancelado.",
                $this->tecladoPrincipal()
            );

            return response()->json([
                'status' => 'already_cancelled',
                'pedido_id' => $resultado['pedido_id'],
                'estado' => $resultado['estado'],
            ], 200);
        }

        if ($resultado['status'] === 'cancel_not_allowed') {
            $mensajeRechazo = ($resultado['motivo'] ?? 'estado') === 'pago'
                ? "⚠️ No se puede cancelar el <b>Pedido #{$resultado['pedido_id']}</b> porque ya registra un pago confirmado o facturado."
                : "⚠️ No se puede cancelar el <b>Pedido #{$resultado['pedido_id']}</b> porque ya se encuentra en estado <b>{$resultado['estado']}</b>. Solo se puede cancelar antes de que pase a preparación.";

            $this->telegramService->enviarMensaje($chatId, $mensajeRechazo, $this->tecladoPrincipal());

            return response()->json([
                'status' => 'cancel_not_allowed',
                'pedido_id' => $resultado['pedido_id'],
                'estado' => $resultado['estado'],
            ], 200);
        }

        $this->telegramService->enviarMensaje(
            $chatId,
            "❌ <b>Pedido #{$resultado['pedido_id']} cancelado correctamente.</b>\nSi querés hacer un nuevo pedido, tocá <b>🌐 Ver Menú</b>.",
            $this->tecladoPrincipal()
        );

        return response()->json([
            'status' => 'cancelled',
            'pedido_id' => $resultado['pedido_id'],
            'estado' => 'Cancelado',
        ], 200);
    }
}
