<?php

use App\Models\Adicional;
use App\Models\BorradorCheckout;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function datosCheckout(): array
{
    $categoria = Categoria::create(['nombre' => 'Comidas', 'activo' => true]);
    $producto = Producto::create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Hamburguesa',
        'precio' => 1000,
        'activo' => true,
    ]);
    $adicional = Adicional::create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Queso',
        'precio' => 200,
        'activo' => true,
    ]);

    return [
        'nombre_cliente' => 'Nombre Apellido',
        'direccion' => 'Calle 123',
        'telefono' => '3454000000',
        'notas' => 'Timbre 2',
        'metodo_pago' => 'efectivo',
        'items' => [[
            'producto_id' => $producto->id,
            'cantidad' => 2,
            'adicionales' => [$adicional->id],
        ]],
    ];
}

it('guarda un borrador publico y crea un Pedido en estado Pre-pedido con sus items y adicionales (SD-339)', function () {
    $datos = datosCheckout();
    $datos['items'][0]['precio'] = 1;
    $datos['monto_total'] = 1;

    $response = $this->postJson('/api/checkout/borradores', $datos);

    $response->assertCreated()
        ->assertJsonPath('estado', 'Pre-pedido')
        ->assertJsonPath('subtotal', 2400)
        ->assertJsonPath('costo_envio', 1500)
        ->assertJsonPath('monto_total', 3900)
        ->assertJsonMissingPath('datos');

    $pedidoId = $response->json('pedido_id');
    expect($pedidoId)->toBeInt()->toBeGreaterThan(0);

    $borrador = BorradorCheckout::findOrFail($response->json('id'));
    expect($borrador->datos['direccion'])->toBe('Calle 123')
        ->and($borrador->datos['telefono'])->toBe('3454000000')
        ->and($borrador->datos['pedido_id'])->toBe($pedidoId)
        ->and($borrador->datos['estado'])->toBe('Pre-pedido')
        ->and($borrador->datos['items'][0]['adicionales'][0]['nombre'])->toBe('Queso')
        ->and(Pedido::count())->toBe(1);

    $pedido = Pedido::with(['estadoPedido', 'cliente', 'metodoPago', 'items.adicionales'])->findOrFail($pedidoId);
    expect($pedido->estadoPedido->nombre)->toBe('Pre-pedido')
        ->and($pedido->nombre_cliente)->toBe('Nombre Apellido')
        ->and($pedido->cliente->telefono)->toBe('3454000000')
        ->and($pedido->cliente->direccion)->toBe('Calle 123')
        ->and((float) $pedido->subtotal)->toBe(2400.0)
        ->and((float) $pedido->monto_total)->toBe(3900.0)
        ->and($pedido->items)->toHaveCount(1)
        ->and($pedido->items->first()->cantidad)->toBe(2)
        ->and((float) $pedido->items->first()->precio_unitario)->toBe(1000.0)
        ->and((float) $pedido->items->first()->subtotal)->toBe(2400.0)
        ->and($pedido->items->first()->adicionales)->toHaveCount(1)
        ->and((float) $pedido->items->first()->adicionales->first()->pivot->precio_adicional)->toBe(200.0);

    $this->postJson('/api/pedidos', [])->assertUnauthorized();
});

it('requiere el nombre completo del cliente', function () {
    $datos = datosCheckout();
    $datos['nombre_cliente'] = '   ';
    $this->postJson('/api/checkout/borradores', $datos)->assertUnprocessable()
        ->assertJsonValidationErrors(['nombre_cliente']);
    expect(Pedido::count())->toBe(0);
});

it('rechaza datos invalidos o un carrito vacio sin guardar', function () {
    $datos = datosCheckout();
    $datos['direccion'] = '   ';
    $datos['telefono'] = 'abc';
    $datos['items'] = [];

    $this->postJson('/api/checkout/borradores', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['direccion', 'telefono', 'items']);
    expect(BorradorCheckout::count())->toBe(0);
});

it('rechaza productos inactivos y adicionales repetidos', function () {
    $datos = datosCheckout();
    Producto::findOrFail($datos['items'][0]['producto_id'])->update(['activo' => false]);
    $this->postJson('/api/checkout/borradores', $datos)->assertUnprocessable();

    Producto::findOrFail($datos['items'][0]['producto_id'])->update(['activo' => true]);
    $datos['items'][0]['adicionales'][] = $datos['items'][0]['adicionales'][0];
    $this->postJson('/api/checkout/borradores', $datos)->assertUnprocessable();
    expect(BorradorCheckout::count())->toBe(0);
});

it('rechaza teléfonos sin dígitos, con letras o con más de 25 dígitos', function ($telefono) {
    $datos = datosCheckout();
    $datos['telefono'] = $telefono;

    $this->postJson('/api/checkout/borradores', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['telefono']);
    expect(BorradorCheckout::count())->toBe(0);
})->with(['+', str_repeat('1', 26), '+'.str_repeat('1', 26), '345 4000000', '345400000a', '12+34']);

it('acepta los límites de los datos de entrega y un teléfono con prefijo', function () {
    $datos = datosCheckout();
    $datos['nombre_cliente'] = str_repeat('N', 25);
    $datos['direccion'] = str_repeat('D', 25);
    $datos['telefono'] = '+'.str_repeat('1', 25);
    $datos['notas'] = str_repeat('A', 255);

    $response = $this->postJson('/api/checkout/borradores', $datos)->assertCreated();
    $pedido = Pedido::with('cliente')->findOrFail($response->json('pedido_id'));
    expect($pedido->cliente->telefono)->toBe($datos['telefono']);
});

it('rechaza datos de entrega que excedan los límites', function () {
    $datos = datosCheckout();
    $datos['nombre_cliente'] = str_repeat('N', 26);
    $datos['direccion'] = str_repeat('D', 26);
    $datos['notas'] = str_repeat('A', 256);

    $this->postJson('/api/checkout/borradores', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nombre_cliente', 'direccion', 'notas']);
    expect(Pedido::count())->toBe(0);
});

it('rechaza medios de pago no habilitados sin guardar el pedido', function ($metodo) {
    $datos = datosCheckout();
    $datos['metodo_pago'] = $metodo;

    $this->postJson('/api/checkout/borradores', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metodo_pago']);
    expect(BorradorCheckout::count())->toBe(0)
        ->and(Pedido::count())->toBe(0);
})->with(['tarjeta', 'transferencia']);

it('valida el monto recibido contra el total calculado por el servidor', function () {
    $datos = datosCheckout();
    $datos['monto_recibido'] = 3899;
    $this->postJson('/api/checkout/borradores', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['monto_recibido']);

    $datos['monto_recibido'] = 4000;
    $response = $this->postJson('/api/checkout/borradores', $datos)->assertCreated();
    expect(BorradorCheckout::findOrFail($response->json('id'))->datos['monto_recibido'])->toBe(4000);

    $datos['metodo_pago'] = 'tarjeta';
    $this->postJson('/api/checkout/borradores', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['monto_recibido']);
});

it('permite el mismo adicional en distintos items y rechaza uno de otra categoria', function () {
    $datos = datosCheckout();
    $datos['items'][] = $datos['items'][0];
    $this->postJson('/api/checkout/borradores', $datos)->assertCreated();

    $otraCategoria = Categoria::create(['nombre' => 'Bebidas', 'activo' => true]);
    Adicional::findOrFail($datos['items'][0]['adicionales'][0])->update(['categoria_id' => $otraCategoria->id]);
    $this->postJson('/api/checkout/borradores', $datos)->assertUnprocessable();
    expect(BorradorCheckout::count())->toBe(1);
});

it('excluye pedidos en estado Pre-pedido de GET /api/pedidos y permite incluirlos con incluir_pre_pedidos=1 (SD-338)', function () {
    $adminRole = Role::create(['nombre' => 'Administrador', 'descripcion' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

    $datos = datosCheckout();
    $checkoutResponse = $this->postJson('/api/checkout/borradores', $datos)->assertCreated();
    $prePedidoId = $checkoutResponse->json('pedido_id');

    // Mientras solo hay un Pre-pedido, la lista operativa devuelve 404 (no hay pedidos operativos disponibles)
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/pedidos')
        ->assertNotFound();

    // Si se solicita explícitamente incluir_pre_pedidos=1, sí se incluye
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/pedidos?incluir_pre_pedidos=1')
        ->assertOk()
        ->assertJsonCount(1, 'pedidos')
        ->assertJsonPath('pedidos.0.id', $prePedidoId)
        ->assertJsonPath('pedidos.0.estado.nombre', 'Pre-pedido');
});

it('confirma un Pre-pedido desde Telegram pasandolo a Sin Asignar, actualiza datos del cliente y lo hace visible en pedidos y dashboard (SD-340)', function () {
    $adminRole = Role::create(['nombre' => 'Administrador', 'descripcion' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

    $datos = datosCheckout();
    $checkoutResponse = $this->postJson('/api/checkout/borradores', $datos)->assertCreated();
    $pedidoId = $checkoutResponse->json('pedido_id');

    // Confirmar desde Telegram enviando datos del cliente
    $confirmResponse = $this->postJson("/api/pedidos/{$pedidoId}/confirmar-telegram", [
        'nombre' => 'Juan Pérez',
        'username_telegram' => '@juan_telegram',
        'telefono' => '3454998877',
        'direccion' => 'Av. San Lorenzo 555',
    ]);

    $confirmResponse->assertOk()
        ->assertJsonPath('message', 'Pedido confirmado correctamente. Estado actualizado a Sin Asignar.')
        ->assertJsonPath('pedido.id', $pedidoId)
        ->assertJsonPath('pedido.estado.nombre', 'Sin Asignar')
        ->assertJsonPath('pedido.cliente.username', 'juan_telegram')
        ->assertJsonPath('pedido.cliente.telefono', '3454998877')
        ->assertJsonPath('pedido.cliente.direccion', 'Av. San Lorenzo 555');

    // Ahora sí aparece en GET /api/pedidos operativo
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/pedidos')
        ->assertOk()
        ->assertJsonCount(1, 'pedidos')
        ->assertJsonPath('pedidos.0.id', $pedidoId)
        ->assertJsonPath('pedidos.0.estado.nombre', 'Sin Asignar');

    // Y aparece en GET /api/dashboard
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('dashboard.kpis.total_pedidos', 1)
        ->assertJsonPath('dashboard.kpis.pedidos_sin_asignar', 1)
        ->assertJsonPath('dashboard.pedidos_para_asignar.0.id', $pedidoId);

    // Si se intenta volver a confirmar un pedido que ya no está en Pre-pedido -> 422
    $this->postJson("/api/pedidos/{$pedidoId}/confirmar-telegram")
        ->assertStatus(422)
        ->assertJsonPath('message', 'El pedido no se encuentra en estado Pre-pedido o ya fue confirmado.');

    // Si el pedido no existe -> 404
    $this->postJson('/api/pedidos/999999/confirmar-telegram')
        ->assertNotFound()
        ->assertJsonPath('message', 'Pedido no encontrado.');
});
