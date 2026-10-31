<?php

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\MetodoPago;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('obtiene la coleccion de pedidos asociados a traves de la relacion pedidos()', function () {
    $cliente = Cliente::create([
        'username' => 'cliente-mp-test',
        'telefono' => '1199887766',
        'direccion' => 'Calle Principal 456',
    ]);

    $estadoPedido = EstadoPedido::create([
        'nombre' => 'Pendiente',
        'descripcion' => 'Pedido en espera',
    ]);

    $metodoPago = MetodoPago::create([
        'nombre' => 'Efectivo contra entrega',
        'descripcion' => 'Abono en efectivo al recibir el pedido',
        'activo' => true,
    ]);

    $pedido1 = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estadoPedido->id,
        'metodo_pago_id' => $metodoPago->id,
        'subtotal' => 1200.00,
        'monto_total' => 1200.00,
    ]);

    $pedido2 = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estadoPedido->id,
        'metodo_pago_id' => $metodoPago->id,
        'subtotal' => 3400.00,
        'monto_total' => 3400.00,
    ]);

    expect($metodoPago->pedidos)->toHaveCount(2);
    expect($metodoPago->pedidos->pluck('id')->all())->toContain($pedido1->id, $pedido2->id);
    expect($pedido1->metodoPago->id)->toBe($metodoPago->id);
});

it('permite cargar la relacion pedidos con eager loading sin errores', function () {
    $metodoPago = MetodoPago::create([
        'nombre' => 'Tarjeta de Débito',
        'descripcion' => 'Pago electrónico',
        'activo' => true,
    ]);

    $cargado = MetodoPago::with('pedidos')->find($metodoPago->id);

    expect($cargado->relationLoaded('pedidos'))->toBeTrue();
    expect($cargado->pedidos)->toBeEmpty();
});
