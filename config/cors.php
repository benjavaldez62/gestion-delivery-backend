<?php

$origenesAdicionales = array_filter(
    array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
);

$permitirOrigenesLan = filter_var(env('CORS_ALLOW_LAN', false), FILTER_VALIDATE_BOOLEAN);

$patronesOrigenesPermitidos = [
    '#^http://localhost:\d+$#',
    '#^http://127\.0\.0\.1:\d+$#',
];

if ($permitirOrigenesLan) {
    $patronesOrigenesPermitidos = array_merge($patronesOrigenesPermitidos, [
        '#^http://192\.168\.\d{1,3}\.\d{1,3}:\d+$#',
        '#^http://10\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+$#',
        '#^http://172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}:\d+$#',
    ]);
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de intercambio de recursos de origen cruzado (CORS).
    | Determina qué operaciones de origen cruzado pueden ejecutarse en navegadores web.
    | Específicamente configurado para clientes SPA Angular (localhost:4200 / 127.0.0.1:4200)
    | y orígenes opcionales en red local (LAN) configurables por variables de entorno.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        [
            'http://localhost:4200',
            'http://127.0.0.1:4200',
            trim((string) env('FRONTEND_URL', 'http://localhost:4200')),
        ],
        $origenesAdicionales
    )))),

    'allowed_origins_patterns' => $patronesOrigenesPermitidos,

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
