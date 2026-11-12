<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramTestMessageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:test-message {chat_id : El chat_id numérico de Telegram} {--texto= : Texto del mensaje de prueba}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía un mensaje de prueba al chat de Telegram especificado para validar el token del bot';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $chatId = (int) $this->argument('chat_id');
        $texto = $this->option('texto') ?: '👋 ¡Hola! Mensaje de prueba enviado exitosamente desde <b>La Posta Delivery Backend</b>.';

        $this->info("Enviando mensaje al chat {$chatId}...");

        $enviado = $telegramService->enviarMensaje($chatId, $texto);

        if ($enviado) {
            $this->info('✅ Mensaje enviado con éxito a Telegram.');

            return Command::SUCCESS;
        }

        $this->error('❌ No se pudo enviar el mensaje. Verificá que TELEGRAM_BOT_TOKEN esté configurado en el .env y que el usuario haya iniciado conversación con el bot.');

        return Command::FAILURE;
    }
}

