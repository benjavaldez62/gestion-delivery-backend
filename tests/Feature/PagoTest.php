<?php

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\EstadoPagos;
use App\Models\EstadoPedido;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPagoTestContext(): array
{
    $cliente = Cliente::create([
        'username' => 'cliente-pago-test',
        'telefono' => '1155443322',
        'direccion' => 'Calle Falsa 123',
    ]);

    $estadoPedido = EstadoPedido::create([
        'nombre' => 'Pendiente',
        'descripcion' => 'Pedido pendiente',
    ]);

    $pedido = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estadoPedido->id,
        'subtotal' => 5000.00,
        'monto_total' => 5000.00,
    ]);

    $metodoPago = MetodoPago::create([
        'nombre' => 'Transferencia Bancaria',
        'descripcion' => 'Transferencia por CBU/CVU',
        'activo' => true,
    ]);

    $estadoPago = EstadoPagos::create([
        'nombre' => 'Aprobado',
        'descripcion' => 'Pago aprobado exitosamente',
    ]);

    $user = User::factory()->create([
        'activo' => true,
    ]);

    return compact('cliente', 'estadoPedido', 'pedido', 'metodoPago', 'estadoPago', 'user');
}

it('permite crear un pago con referencia y persistirla correctamente en base de datos', function () {
    $ctx = createPagoTestContext();

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->postJson('/api/pagos', [
            'pedido_id' => $ctx['pedido']->id,
            'metodo_pago_id' => $ctx['metodoPago']->id,
            'monto' => 5000.00,
            'fecha_pago' => '2026-10-26',
            'estado_pago_id' => $ctx['estadoPago']->id,
            'referencia' => 'TRX-ABC-999888',
        ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Pago creado correctamente')
        ->assertJsonPath('pago.referencia', 'TRX-ABC-999888')
        ->assertJsonPath('pago.monto', 5000);

    $pago = Pago::first();
    expect($pago)->not->toBeNull()
        ->and($pago->referencia)->toBe('TRX-ABC-999888')
        ->and((float) $pago->monto)->toEqual(5000.00);
});

it('permite crear un pago sin referencia opcional quedando en null', function () {
    $ctx = createPagoTestContext();

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->postJson('/api/pagos', [
            'pedido_id' => $ctx['pedido']->id,
            'metodo_pago_id' => $ctx['metodoPago']->id,
            'monto' => 2500.00,
            'fecha_pago' => '2026-10-26',
            'estado_pago_id' => $ctx['estadoPago']->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('pago.referencia', null);

    $pago = Pago::first();
    expect($pago->referencia)->toBeNull();
});

it('permite actualizar un pago modificando el campo referencia', function () {
    $ctx = createPagoTestContext();

    $pago = Pago::create([
        'pedido_id' => $ctx['pedido']->id,
        'metodo_pago_id' => $ctx['metodoPago']->id,
        'monto' => 5000.00,
        'fecha_pago' => '2026-10-26',
        'estado_pago_id' => $ctx['estadoPago']->id,
        'referencia' => 'REF-INICIAL',
    ]);

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->putJson("/api/pagos/{$pago->id}", [
            'pedido_id' => $ctx['pedido']->id,
            'metodo_pago_id' => $ctx['metodoPago']->id,
            'monto' => 5000.00,
            'fecha_pago' => '2026-10-26',
            'estado_pago_id' => $ctx['estadoPago']->id,
            'referencia' => 'REF-ACTUALIZADA-123',
        ]);

    $response->assertOk()
        ->assertJsonPath('pago.referencia', 'REF-ACTUALIZADA-123');

    expect($pago->fresh()->referencia)->toBe('REF-ACTUALIZADA-123');
});

it('serializa referencia y la coleccion de comprobantes en PagoResource al consultar show', function () {
    $ctx = createPagoTestContext();

    $pago = Pago::create([
        'pedido_id' => $ctx['pedido']->id,
        'metodo_pago_id' => $ctx['metodoPago']->id,
        'monto' => 3000.00,
        'fecha_pago' => '2026-10-26',
        'estado_pago_id' => $ctx['estadoPago']->id,
        'referencia' => 'TRX-COMPROBANTE-TEST',
    ]);

    $comprobante = Comprobante::create([
        'pago_id' => $pago->id,
        'tipo_comprobante' => 'Factura B',
        'numero_comprobante' => '0001-00000456',
        'titular' => 'Juan Perez',
        'url_pdf' => 'https://example.com/factura-456.pdf',
    ]);

    $response = $this->actingAs($ctx['user'], 'sanctum')
        ->getJson("/api/pagos/{$pago->id}");

    $response->assertOk()
        ->assertJsonPath('pago.id', $pago->id)
        ->assertJsonPath('pago.referencia', 'TRX-COMPROBANTE-TEST')
        ->assertJsonCount(1, 'pago.comprobantes')
        ->assertJsonPath('pago.comprobantes.0.id', $comprobante->id)
        ->assertJsonPath('pago.comprobantes.0.numero_comprobante', '0001-00000456');
});
