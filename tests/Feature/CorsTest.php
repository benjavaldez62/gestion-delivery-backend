<?php

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
