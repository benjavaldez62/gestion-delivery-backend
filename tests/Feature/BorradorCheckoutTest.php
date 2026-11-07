<?php

use App\Models\Adicional;
use App\Models\BorradorCheckout;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Producto;
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
        'direccion' => 'Calle 123',
        'telefono' => '123456789',
        'notas' => 'Timbre 2',
        'metodo_pago' => 'efectivo',
        'items' => [[
            'producto_id' => $producto->id,
            'cantidad' => 2,
            'adicionales' => [$adicional->id],
        ]],
    ];
}

it('guarda un borrador publico con precios calculados por el servidor sin crear un pedido', function () {
    $datos = datosCheckout();
    $datos['items'][0]['precio'] = 1;
    $datos['monto_total'] = 1;

    $response = $this->postJson('/api/checkout/borradores', $datos);

    $response->assertCreated()
        ->assertJsonPath('subtotal', 2400)
        ->assertJsonPath('costo_envio', 1500)
        ->assertJsonPath('monto_total', 3900)
        ->assertJsonMissingPath('datos');

    $borrador = BorradorCheckout::findOrFail($response->json('id'));
    expect($borrador->datos['direccion'])->toBe('Calle 123')
        ->and($borrador->datos['telefono'])->toBe('123456789')
        ->and($borrador->datos['items'][0]['adicionales'][0]['nombre'])->toBe('Queso')
        ->and(Pedido::count())->toBe(0);
    $this->postJson('/api/pedidos', [])->assertUnauthorized();
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

it('requiere al menos seis digitos en el telefono', function () {
    $datos = datosCheckout();
    $datos['telefono'] = '+---1-';

    $this->postJson('/api/checkout/borradores', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['telefono']);
    expect(BorradorCheckout::count())->toBe(0);
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
