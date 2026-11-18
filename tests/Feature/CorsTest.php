<?php

/**
 * Carga config/cors.php aplicando variables de entorno temporales y restaurando
 * el entorno original al finalizar para evitar fugas entre pruebas.
 *
 * @param  array<string, string|null>  $variables
 * @return array<string, mixed>
 */
function cargarConfiguracionCorsConEntorno(array $variables): array
{
    $estadoAnterior = [];

    foreach ($variables as $clave => $valor) {
        $estadoAnterior[$clave] = [
            'envExiste' => array_key_exists($clave, $_ENV),
            'envValor' => $_ENV[$clave] ?? null,
            'serverExiste' => array_key_exists($clave, $_SERVER),
            'serverValor' => $_SERVER[$clave] ?? null,
            'getenvValor' => getenv($clave),
        ];

        if ($valor === null) {
            unset($_ENV[$clave], $_SERVER[$clave]);
            putenv($clave);
        } else {
            $_ENV[$clave] = $valor;
            $_SERVER[$clave] = $valor;
            putenv("{$clave}={$valor}");
        }
    }

    try {
        return require config_path('cors.php');
    } finally {
        foreach ($estadoAnterior as $clave => $previo) {
            if ($previo['envExiste']) {
                $_ENV[$clave] = $previo['envValor'];
            } else {
                unset($_ENV[$clave]);
            }

            if ($previo['serverExiste']) {
                $_SERVER[$clave] = $previo['serverValor'];
            } else {
                unset($_SERVER[$clave]);
            }

            if ($previo['getenvValor'] === false) {
                putenv($clave);
            } else {
                putenv("{$clave}={$previo['getenvValor']}");
            }
        }
    }
}

it('permite preflight options desde http://localhost:4200 con cabeceras de cors y credenciales (SD-309)', function () {
    $response = $this->withHeaders([
        'Origin' => 'http://localhost:4200',
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'Authorization, Content-Type, Accept, X-Requested-With',
    ])->options('/api/categorias');

    $response->assertStatus(204);
    $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4200');
    $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    expect($response->headers->get('Access-Control-Allow-Methods'))->toContain('GET');
});

it('permite preflight options desde http://127.0.0.1:4200 (SD-309)', function () {
    $response = $this->withHeaders([
        'Origin' => 'http://127.0.0.1:4200',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Authorization, Content-Type, Accept, X-Requested-With',
    ])->options('/api/categorias');

    $response->assertStatus(204);
    $response->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:4200');
    $response->assertHeader('Access-Control-Allow-Credentials', 'true');
});

it('rechaza cabeceras cors para un origen no autorizado (SD-309)', function () {
    $response = $this->withHeaders([
        'Origin' => 'http://sitio-malicioso.com',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/categorias');

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

it('incluye cabecera access-control-allow-origin en peticion get desde origen permitido (SD-309)', function () {
    $response = $this->withHeaders([
        'Origin' => 'http://localhost:4200',
    ])->getJson('/api/categorias');

    $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4200');
    $response->assertHeader('Access-Control-Allow-Credentials', 'true');
});

it('rechaza un origen privado de red local no listado cuando CORS_ALLOW_LAN es false por defecto', function () {
    $corsConfig = cargarConfiguracionCorsConEntorno([
        'FRONTEND_URL' => 'http://localhost:4200',
        'CORS_ALLOWED_ORIGINS' => '',
        'CORS_ALLOW_LAN' => null,
    ]);

    config(['cors' => $corsConfig]);

    $preflight = $this->withHeaders([
        'Origin' => 'http://192.168.1.50:4200',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/categorias');

    expect($preflight->headers->get('Access-Control-Allow-Origin'))->toBeNull();

    $getResponse = $this->withHeaders([
        'Origin' => 'http://192.168.1.50:4200',
    ])->getJson('/api/categorias');

    expect($getResponse->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

it('permite origenes LAN configurados explicitamente en CORS_ALLOWED_ORIGINS manteniendo bloqueo para otros', function () {
    $corsConfig = cargarConfiguracionCorsConEntorno([
        'FRONTEND_URL' => 'http://localhost:4200',
        'CORS_ALLOWED_ORIGINS' => ' http://192.168.1.50:4200 , http://10.0.0.15:4200 , http://localhost:4200 , ',
        'CORS_ALLOW_LAN' => 'false',
    ]);

    expect($corsConfig['allowed_origins'])->toBe([
        'http://localhost:4200',
        'http://127.0.0.1:4200',
        'http://192.168.1.50:4200',
        'http://10.0.0.15:4200',
    ]);

    config(['cors' => $corsConfig]);

    $permitido = $this->withHeaders([
        'Origin' => 'http://192.168.1.50:4200',
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'Authorization, Content-Type',
    ])->options('/api/categorias');

    $permitido->assertStatus(204);
    $permitido->assertHeader('Access-Control-Allow-Origin', 'http://192.168.1.50:4200');
    $permitido->assertHeader('Access-Control-Allow-Credentials', 'true');

    $segundoPermitido = $this->withHeaders([
        'Origin' => 'http://10.0.0.15:4200',
    ])->getJson('/api/categorias');

    $segundoPermitido->assertHeader('Access-Control-Allow-Origin', 'http://10.0.0.15:4200');
    $segundoPermitido->assertHeader('Access-Control-Allow-Credentials', 'true');

    $noListado = $this->withHeaders([
        'Origin' => 'http://192.168.1.99:4200',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/categorias');

    expect($noListado->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

it('permite origenes privados RFC 1918 cuando CORS_ALLOW_LAN es true y rechaza origenes externos', function () {
    $corsConfig = cargarConfiguracionCorsConEntorno([
        'FRONTEND_URL' => 'http://localhost:4200',
        'CORS_ALLOWED_ORIGINS' => '',
        'CORS_ALLOW_LAN' => 'true',
    ]);

    config(['cors' => $corsConfig]);

    foreach ([
        'http://192.168.1.50:4200',
        'http://10.0.0.25:4200',
        'http://172.16.10.5:4200',
    ] as $origenLan) {
        $response = $this->withHeaders([
            'Origin' => $origenLan,
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type',
        ])->options('/api/categorias');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', $origenLan);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    foreach ([
        'http://sitio-malicioso.com',
        'http://172.32.0.10:4200',
    ] as $origenExterno) {
        $response = $this->withHeaders([
            'Origin' => $origenExterno,
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/categorias');

        expect($response->headers->get('Access-Control-Allow-Origin'))->toBeNull();
    }
});
