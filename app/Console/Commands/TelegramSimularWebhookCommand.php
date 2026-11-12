<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Request;

class TelegramSimularWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:simular-webhook 
                            {pedido : ID del pedido a confirmar} 
                            {--chat-id=123456789 : ID numérico del chat de Telegram} 
                            {--username=cliente_prueba : Usuario de Telegram sin @}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simula un mensaje entrante de Telegram al webhook para confirmar un pedido';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $pedidoId = (int) $this->argument('pedido');
        $chatId = (int) $this->option('chat-id');
        $username = (string) $this->option('username');

        $this->info("Simulando webhook de Telegram para el Pedido #{$pedidoId}...");

        $payload = [
            'update_id' => random_int(10000, 99999),
            'message' => [
                'message_id' => random_int(1, 1000),
                'from' => [
                    'id' => $chatId,
                    'is_bot' => false,
                    'first_name' => 'Cliente',
                    'username' => $username,
                ],
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'text' => "/start {$pedidoId}",
            ],
        ];

        $request = Request::create('/api/telegram/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($payload));

        $response = app()->handle($request);
        $content = json_decode($response->getContent(), true);

        if ($response->getStatusCode() === 200 && ($content['status'] ?? '') === 'confirmed') {
            $this->info("✅ Webhook simulado con éxito. Pedido #{$pedidoId} confirmado y transicionado a Sin Asignar.");
            $this->line("Chat ID vinculado: {$chatId} | Username: @{$username}");

            return Command::SUCCESS;
        }

        $this->error('⚠️ Respuesta del webhook: '.json_encode($content));

        return Command::FAILURE;
    }
}

