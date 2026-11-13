<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ConfiguracionPublicaController extends Controller
{
    #[OA\Get(
        path: '/api/configuracion-publica',
        summary: 'Obtener configuración pública de Telegram',
        tags: ['Telegram'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Nombre público del bot configurado.',
                content: new OA\JsonContent(
                    required: ['telegram_bot_username'],
                    properties: [new OA\Property(property: 'telegram_bot_username', type: 'string')]
                )
            ),
            new OA\Response(response: 503, description: 'Configuración de Telegram no disponible.'),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        $username = config('services.telegram.bot_username');

        if (! is_string($username) || ! preg_match('/\A[A-Za-z0-9_]{5,32}\z/', $username)) {
            Log::warning('El nombre del bot de Telegram no está configurado o es inválido.');

            return response()->json([
                'message' => 'La configuración de Telegram no está disponible.',
            ], 503)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'telegram_bot_username' => $username,
        ])->header('Cache-Control', 'no-store');
    }
}
