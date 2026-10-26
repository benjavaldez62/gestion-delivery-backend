<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de intercambio de recursos de origen cruzado (CORS).
    | Determina qué operaciones de origen cruzado pueden ejecutarse en navegadores web.
    | Específicamente configurado para clientes SPA Angular (localhost:4200 / 127.0.0.1:4200).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique(array_filter([
        'http://localhost:4200',
        'http://127.0.0.1:4200',
        env('FRONTEND_URL', 'http://localhost:4200'),
    ]))),

    'allowed_origins_patterns' => [
        '#^http://localhost:\d+$#',
        '#^http://127\.0\.0\.1:\d+$#',
    ],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'Origin',
    ],

    'exposed_headers' => [
        'Authorization',
    ],

    'max_age' => 86400,

    'supports_credentials' => true,

];
