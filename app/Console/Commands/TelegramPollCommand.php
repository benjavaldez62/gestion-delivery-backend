<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramPollCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:poll {--timeout=20 : Tiempo de espera en segundos para Long Polling}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inicia un worker de Long Polling para escuchar mensajes de Telegram en tiempo real sin túneles ni webhooks externos';

    /**
     * Execute the console command.
     */
    public function handle(TelegramWebhookController $webhookController): int
    {
        $botToken = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));

        if (empty($botToken)) {
            $this->error('❌ No se encontró TELEGRAM_BOT_TOKEN en el archivo .env.');

            return Command::FAILURE;
        }

        // Si hubiera un webhook registrado previamente en Telegram, lo eliminamos para permitir getUpdates
        $this->info('Eliminando webhook previo en Telegram para habilitar Long Polling...');
        try {
            Http::get("https://api.telegram.org/bot{$botToken}/deleteWebhook");
        } catch (\Throwable $e) {
            // Ignorar error de red puntual
        }

        $timeout = (int) $this->option('timeout');
        $this->info("🤖 Bot de Telegram escuchando eventos en vivo (Long Polling timeout: {$timeout}s)...");
        $this->comment('Presioná CTRL+C para detener el proceso en cualquier momento.');

        $offset = 0;

        while (true) {
            try {
                $response = Http::timeout($timeout + 10)->get("https://api.telegram.org/bot{$botToken}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => $timeout,
                    'allowed_updates' => json_encode(['message']),
                ]);

                if (! $response->successful()) {
                    $this->warn('Error al consultar Telegram: '.$response->body());
                    sleep(2);

                    continue;
                }

                $data = $response->json();
                $updates = $data['result'] ?? [];

                foreach ($updates as $update) {
                    $offset = ($update['update_id'] ?? $offset) + 1;

                    if (! isset($update['message'])) {
                        continue;
                    }

                    $msg = $update['message'];
                    $chatId = $msg['chat']['id'] ?? 'desconocido';
                    $text = $msg['text'] ?? '';
                    $from = $msg['from']['username'] ?? ($msg['from']['first_name'] ?? 'Usuario');

                    $this->line("<fg=cyan>[".now()->format('H:i:s')."]</> Mensaje de <fg=yellow>@{$from}</> (chat: {$chatId}): \"{$text}\"");

                    // Delegar el procesamiento al mismo controlador que maneja el webhook
                    $request = Request::create('/api/telegram/webhook', 'POST', [], [], [], [
                        'CONTENT_TYPE' => 'application/json',
                    ], json_encode($update));

                    $res = $webhookController->handle($request);
                    $result = json_decode($res->getContent(), true);

                    $this->info('   ↳ Resultado: '.($result['status'] ?? 'procesado').' | '.json_encode($result, JSON_UNESCAPED_UNICODE));
                }
            } catch (\Throwable $e) {
                $this->error('Excepción en polling: '.$e->getMessage());
                sleep(2);
            }
        }

        return Command::SUCCESS;
    }
}
