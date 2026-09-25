<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API Sistema de Delivery',
    description: 'API del Sistema de Gestión de Delivery'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Servidor local'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token Sanctum',
    description: 'Token de autenticación generado por Laravel Sanctum.'
)]
class OpenApi
{
    #[OA\Get(
        path: '/api/user',
        summary: 'Obtener datos del usuario autenticado',
        tags: ['Autenticación'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Datos del usuario autenticado'),
            new OA\Response(response: 401, description: 'No autenticado')
        ]
    )]
    public function user()
    {
    }
}