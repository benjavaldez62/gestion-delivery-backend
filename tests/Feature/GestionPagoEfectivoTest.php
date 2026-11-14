<?php

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\EstadoPagos;
use App\Models\EstadoPedido;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EstadoPagoSeeder;
use Database\Seeders\EstadoPedidoSeeder;
use Database\Seeders\MetodoPagoSeeder;
use Database\Seeders\PagoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function contextoGestionPago(): array
{
    $role = Role::create(['nombre' => 'Administrador']);
    $admin = User::factory()->create(['role_id' => $role->id, 'activo' => true]);
    $cliente = Cliente::create(['username' => 'prueba-gestion']);
    $estado = EstadoPedido::create(['nombre' => 'Sin Asignar']);
    $metodo = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true]);
    $pedido = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estado->id,
        'metodo_pago_id' => $metodo->id,
        'monto_total' => 1250,
    ]);

    return compact('admin', 'pedido', 'metodo');
}

it('resume solamente pagos cobrados del día y mes según fecha de pago', function () {
    $this->travelTo(Carbon::parse('2026-03-15 12:00:00', 'UTC'));
    $ctx = contextoGestionPago();
    $pagado = EstadoPagos::create(['nombre' => 'Pagado']);
    $pendiente = EstadoPagos::create(['nombre' => 'Pendiente']);
    $datos = ['pedido_id' => $ctx['pedido']->id, 'metodo_pago_id' => $ctx['metodo']->id];
    Pago::create([...$datos, 'estado_pago_id' => $pagado->id, 'monto' => 100.25, 'fecha_pago' => '2026-03-15']);
    Pago::create([...$datos, 'estado_pago_id' => $pagado->id, 'monto' => 200.50, 'fecha_pago' => '2026-03-01']);
    Pago::create([...$datos, 'estado_pago_id' => $pagado->id, 'monto' => 900, 'fecha_pago' => '2026-02-28']);
    Pago::create([...$datos, 'estado_pago_id' => $pagado->id, 'monto' => 800, 'fecha_pago' => '2026-03-16']);
    Pago::create([...$datos, 'estado_pago_id' => $pendiente->id, 'monto' => 700, 'fecha_pago' => '2026-03-15']);
    Pago::create([...$datos, 'estado_pago_id' => $pagado->id, 'monto' => 600, 'fecha_pago' => '2026-03-15'])->delete();

    $this->actingAs($ctx['admin'], 'sanctum')->getJson('/api/pagos/resumen')
        ->assertOk()->assertJsonPath('fecha', '2026-03-15')
        ->assertJsonPath('zona_horaria', config('app.pagos_timezone'))
        ->assertJsonPath('ingreso_diario', 100.25)
        ->assertJsonPath('ingreso_mensual', 300.75);
    $repartidor = User::factory()->create(['role_id' => Role::create(['nombre' => 'Repartidor'])->id, 'activo' => true]);
    $this->actingAs($repartidor, 'sanctum')->getJson('/api/pagos/resumen')->assertForbidden();
});

it('devuelve ingresos en cero si todavía no hay pagos cobrados', function () {
    $ctx = contextoGestionPago();
    $this->actingAs($ctx['admin'], 'sanctum')->getJson('/api/pagos/resumen')
        ->assertOk()->assertJsonPath('ingreso_diario', 0)->assertJsonPath('ingreso_mensual', 0);
});

it('usa la fecha de Argentina para el día y mes de los resúmenes', function () {
    $this->travelTo(Carbon::parse('2026-04-01 02:30:00', 'UTC'));
    config(['app.pagos_timezone' => 'America/Argentina/Buenos_Aires']);
    $ctx = contextoGestionPago();
    $pagado = EstadoPagos::create(['nombre' => 'Pagado']);
    $datos = ['pedido_id' => $ctx['pedido']->id, 'metodo_pago_id' => $ctx['metodo']->id, 'estado_pago_id' => $pagado->id];
    Pago::create([...$datos, 'monto' => 100, 'fecha_pago' => '2026-03-31']);
    Pago::create([...$datos, 'monto' => 200, 'fecha_pago' => '2026-03-01']);
    Pago::create([...$datos, 'monto' => 300, 'fecha_pago' => '2026-04-01']);
    $this->actingAs($ctx['admin'], 'sanctum')->getJson('/api/pagos/resumen')
        ->assertOk()->assertJsonPath('fecha', '2026-03-31')
        ->assertJsonPath('zona_horaria', 'America/Argentina/Buenos_Aires')
        ->assertJsonPath('ingreso_diario', 100)->assertJsonPath('ingreso_mensual', 300);
    $this->getJson('/api/pagos/envios')->assertOk()->assertJsonPath('fecha', '2026-03-31')
        ->assertJsonPath('zona_horaria', 'America/Argentina/Buenos_Aires');
    $this->travel(1)->hours();
    $this->getJson('/api/pagos/resumen')->assertOk()->assertJsonPath('fecha', '2026-04-01')
        ->assertJsonPath('ingreso_diario', 300)->assertJsonPath('ingreso_mensual', 300);
});

it('resume envíos cobrados por repartidor y señala pedidos que requieren revisión', function () {
    $this->travelTo(Carbon::parse('2026-03-15 12:00:00', 'UTC'));
    $ctx = contextoGestionPago();
    $entregado = EstadoPedido::create(['nombre' => 'Entregado']);
    $pagado = EstadoPagos::create(['nombre' => 'Pagado']);
    $pendiente = EstadoPagos::create(['nombre' => 'Pendiente']);
    $fallido = EstadoPagos::create(['nombre' => 'Fallido']);
    $role = Role::create(['nombre' => 'Repartidor']);
    $ana = User::factory()->create(['name' => 'Ana', 'role_id' => $role->id]);
    $beto = User::factory()->create(['name' => 'Beto', 'role_id' => $role->id]);
    $crear = function (array $atributos = [], ?EstadoPagos $estado = null, string $fecha = '2026-03-15') use ($ctx, $entregado, $pagado, $ana) {
        $pedido = Pedido::create(array_merge([
            'cliente_id' => $ctx['pedido']->cliente_id, 'estado_pedidos_id' => $entregado->id,
            'metodo_pago_id' => $ctx['metodo']->id, 'repartidor_id' => $ana->id,
            'subtotal' => 1000, 'monto_total' => 1200.25,
        ], $atributos));
        Pago::create([
            'pedido_id' => $pedido->id, 'metodo_pago_id' => $ctx['metodo']->id,
            'estado_pago_id' => ($estado ?? $pagado)->id, 'monto' => $pedido->monto_total, 'fecha_pago' => $fecha,
        ]);

        return $pedido;
    };
    $primero = $crear();
    $sinCargo = $crear(['monto_total' => 1000]);
    $otro = $crear(['repartidor_id' => $beto->id, 'monto_total' => 1100.50]);
    $beto->delete();
    $crear([], $pendiente);
    $crear([], $fallido);
    $anterior = $crear([], null, '2026-03-14');
    $sinRepartidor = $crear(['repartidor_id' => null]);
    $sinSubtotal = $crear(['subtotal' => null]);
    $importeInvalido = $crear(['subtotal' => 1300]);
    $importeNegativo = $crear(['subtotal' => -10]);
    $noEntregado = $crear(['estado_pedidos_id' => $ctx['pedido']->estado_pedidos_id]);
    $duplicado = $crear();
    Pago::create([
        'pedido_id' => $duplicado->id, 'metodo_pago_id' => $ctx['metodo']->id,
        'estado_pago_id' => $pagado->id, 'monto' => $duplicado->monto_total, 'fecha_pago' => '2026-03-14',
    ]);
    $distinto = $crear();
    Pago::where('pedido_id', $distinto->id)->update(['monto' => 1]);
    $borrado = $crear();
    Pago::where('pedido_id', $borrado->id)->firstOrFail()->delete();

    $this->getJson('/api/pagos/envios')->assertUnauthorized();
    $this->actingAs($ana, 'sanctum')->getJson('/api/pagos/envios')->assertForbidden();
    $this->actingAs($ctx['admin'], 'sanctum');
    $this->getJson('/api/pagos/envios?fecha=incorrecta')->assertUnprocessable();
    $respuesta = $this->getJson('/api/pagos/envios')->assertOk()
        ->assertJsonPath('fecha', '2026-03-15')
        ->assertJsonPath('zona_horaria', config('app.pagos_timezone'))
        ->assertJsonPath('total_envios', 300.75)
        ->assertJsonCount(2, 'repartidores')
        ->assertJsonPath('repartidores.0.nombre', 'Ana')
        ->assertJsonPath('repartidores.0.total', 200.25)
        ->assertJsonPath('repartidores.1.nombre', 'Beto')
        ->assertJsonPath('repartidores.1.total', 100.5)
        ->assertJsonCount(7, 'pendientes_revision');
    $datos = $respuesta->json();
    expect(collect($datos['repartidores'][0]['envios'])->pluck('pedido_id')->all())->toEqualCanonicalizing([$primero->id, $sinCargo->id]);
    expect(collect($datos['repartidores'][0]['envios'])->firstWhere('pedido_id', $sinCargo->id)['importe'])->toBe(0);
    expect($datos['repartidores'][1]['envios'][0]['pedido_id'])->toBe($otro->id);
    expect(collect($datos['pendientes_revision'])->pluck('pedido_id')->all())
        ->toEqualCanonicalizing([$sinRepartidor->id, $sinSubtotal->id, $importeInvalido->id, $importeNegativo->id, $noEntregado->id, $duplicado->id, $distinto->id]);

    $this->getJson('/api/pagos/envios?fecha=2026-03-14')->assertOk()
        ->assertJsonPath('total_envios', 200.25)
        ->assertJsonPath('repartidores.0.envios.0.pedido_id', $anterior->id)
        ->assertJsonCount(1, 'pendientes_revision');
    $this->getJson('/api/pagos/envios?fecha=2026-01-01')->assertOk()
        ->assertJsonPath('total_envios', 0)->assertJsonCount(0, 'repartidores')->assertJsonCount(0, 'pendientes_revision');
});

it('registra solo una vez el importe pendiente exacto del pedido y permite filtrarlo', function () {
    $ctx = contextoGestionPago();
    $url = "/api/pedidos/{$ctx['pedido']->id}/pagos";
    $body = ['metodo_pago_id' => $ctx['metodo']->id, 'monto' => 1250, 'referencia' => 'prueba'];
    $this->actingAs($ctx['admin'], 'sanctum');
    $this->postJson($url, [...$body, 'monto' => 100])->assertUnprocessable();
    $this->postJson($url, [...$body, 'fecha_pago' => '2026-01-01'])->assertUnprocessable();
    $this->postJson($url, $body)->assertCreated()
        ->assertJsonPath('pago.estado_pago.nombre', 'Pendiente')
        ->assertJsonPath('pago.fecha_pago', null);
    $this->postJson($url, $body)->assertUnprocessable();
    expect(Pago::count())->toBe(1);

    $pago = Pago::firstOrFail();
    $this->getJson("/api/pagos?pedido_id={$ctx['pedido']->id}&estado_pago_id={$pago->estado_pago_id}")
        ->assertOk()->assertJsonCount(1, 'pagos')->assertJsonPath('pagos.0.pedido.id', $ctx['pedido']->id);
    $this->getJson("/api/pagos/{$pago->id}")->assertOk()
        ->assertJsonPath('pago.metodo_pago.nombre', 'Efectivo');
});

it('busca pagos por identificador, nombre o teléfono y combina los filtros', function () {
    $ctx = contextoGestionPago();
    $ctx['pedido']->cliente->update(['username' => 'web_3511234567', 'telefono' => '(351) 123-4567']);
    $ctx['pedido']->update(['nombre_cliente' => 'Ana López']);
    $pendiente = EstadoPagos::create(['nombre' => 'Pendiente']);
    $pagado = EstadoPagos::create(['nombre' => 'Pagado']);
    $primero = Pago::create([
        'pedido_id' => $ctx['pedido']->id, 'metodo_pago_id' => $ctx['metodo']->id,
        'estado_pago_id' => $pendiente->id, 'monto' => 1250,
    ]);
    $otroCliente = Cliente::create(['username' => 'web_999999', 'telefono' => '999999']);
    $otroPedido = Pedido::create([
        'cliente_id' => $otroCliente->id, 'nombre_cliente' => 'Juan Pérez',
        'estado_pedidos_id' => $ctx['pedido']->estado_pedidos_id, 'metodo_pago_id' => $ctx['metodo']->id,
        'monto_total' => 1250,
    ]);
    $segundo = Pago::create([
        'pedido_id' => $otroPedido->id, 'metodo_pago_id' => $ctx['metodo']->id,
        'estado_pago_id' => $pagado->id, 'monto' => 1250, 'fecha_pago' => '2026-03-15',
    ]);
    $this->getJson('/api/pagos?busqueda=Ana')->assertUnauthorized();
    $repartidor = User::factory()->create(['role_id' => Role::create(['nombre' => 'Repartidor'])->id, 'activo' => true]);
    $this->actingAs($repartidor, 'sanctum')->getJson('/api/pagos?busqueda=Ana')->assertForbidden();
    $this->actingAs($ctx['admin'], 'sanctum');
    foreach (['ANA', 'ópez', '3511234567', '123-4567'] as $termino) {
        $this->getJson('/api/pagos?'.http_build_query(['busqueda' => $termino]))
            ->assertOk()->assertJsonCount(1, 'pagos')
            ->assertJsonPath('pagos.0.id', $primero->id)
            ->assertJsonPath('pagos.0.pedido.nombre_cliente', 'Ana López')
            ->assertJsonPath('pagos.0.pedido.telefono_cliente', '(351) 123-4567');
    }
    foreach ([$primero->id, $ctx['pedido']->id, $ctx['pedido']->cliente_id] as $numero) {
        $this->getJson('/api/pagos?'.http_build_query(['busqueda' => (string) $numero]))
            ->assertOk()->assertJsonFragment(['id' => $primero->id, 'pedido_id' => $ctx['pedido']->id]);
    }
    $this->getJson('/api/pagos?'.http_build_query([
        'busqueda' => 'ana', 'estado_pago_id' => $pendiente->id, 'metodo_pago_id' => $ctx['metodo']->id,
    ]))->assertOk()->assertJsonCount(1, 'pagos')->assertJsonPath('pagos.0.id', $primero->id);
    $this->getJson('/api/pagos?'.http_build_query(['busqueda' => 'ana', 'estado_pago_id' => $pagado->id]))->assertNotFound();
    $this->getJson('/api/pagos?pedido_id='.$otroPedido->id)->assertOk()->assertJsonPath('pagos.0.id', $segundo->id);
    $this->getJson("/api/pagos/{$primero->id}")->assertOk()
        ->assertJsonPath('pago.pedido.nombre_cliente', 'Ana López')
        ->assertJsonPath('pago.pedido.telefono_cliente', '(351) 123-4567');
    $this->getJson('/api/pagos?'.http_build_query(['busqueda' => '%']))->assertNotFound();
    $this->getJson('/api/pagos?'.http_build_query(['busqueda' => str_repeat('a', 101)]))->assertUnprocessable();
    $ctx['pedido']->cliente->delete();
    $ctx['pedido']->delete();
    $this->getJson('/api/pagos?busqueda=3511234567')->assertOk()
        ->assertJsonPath('pagos.0.pedido.nombre_cliente', 'Ana López')
        ->assertJsonPath('pagos.0.pedido.telefono_cliente', '(351) 123-4567');
});

it('no permite a usuarios sin rol de administrador registrar pagos', function () {
    $ctx = contextoGestionPago();
    $role = Role::create(['nombre' => 'Repartidor']);
    $repartidor = User::factory()->create(['role_id' => $role->id, 'activo' => true]);
    $this->actingAs($repartidor, 'sanctum')->postJson("/api/pedidos/{$ctx['pedido']->id}/pagos", [
        'metodo_pago_id' => $ctx['metodo']->id, 'monto' => 1250,
    ])->assertForbidden();
    expect(Pago::count())->toBe(0);
});

it('impide modificar o anular pagos confirmados o facturados', function () {
    $ctx = contextoGestionPago();
    $estado = EstadoPagos::create(['nombre' => 'Pagado']);
    $pago = Pago::create([
        'pedido_id' => $ctx['pedido']->id, 'metodo_pago_id' => $ctx['metodo']->id,
        'monto' => 1250, 'estado_pago_id' => $estado->id, 'fecha_pago' => now()->toDateString(),
    ]);
    Comprobante::create([
        'pago_id' => $pago->id, 'tipo_comprobante' => 'Ticket no fiscal',
        'numero_comprobante' => 'T-ficticio', 'titular' => 'Cliente',
    ]);

    $this->actingAs($ctx['admin'], 'sanctum');
    $this->deleteJson("/api/pagos/{$pago->id}")->assertUnprocessable();
    $comprobante = Comprobante::firstOrFail();
    $this->deleteJson("/api/comprobantes/{$comprobante->id}")->assertUnprocessable();
    $this->postJson('/api/comprobantes', [
        'pago_id' => $pago->id, 'tipo_comprobante' => 'Ticket no fiscal',
        'numero_comprobante' => 'T-falso', 'titular' => 'Cliente',
    ])->assertUnprocessable();
    $this->putJson("/api/pagos/{$pago->id}", [
        'pedido_id' => $ctx['pedido']->id, 'metodo_pago_id' => $ctx['metodo']->id,
        'monto' => 1250, 'estado_pago_id' => $estado->id, 'fecha_pago' => null,
    ])->assertUnprocessable();
    expect($pago->fresh())->not->toBeNull();
});

it('permite cambiar el estado pendiente y anularlo sin marcarlo como cobrado', function () {
    $ctx = contextoGestionPago();
    $this->actingAs($ctx['admin'], 'sanctum');
    $this->postJson("/api/pedidos/{$ctx['pedido']->id}/pagos", [
        'metodo_pago_id' => $ctx['metodo']->id, 'monto' => 1250, 'referencia' => 'conservar',
    ])->assertCreated();
    $pago = Pago::firstOrFail();
    $pendiente = $pago->estado_pago_id;
    $fallido = EstadoPagos::create(['nombre' => 'Fallido']);
    $pagado = EstadoPagos::create(['nombre' => 'Pagado']);

    $this->putJson("/api/pagos/{$pago->id}", ['estado_pago_id' => $pagado->id])->assertUnprocessable();
    $this->putJson("/api/pagos/{$pago->id}", ['estado_pago_id' => $fallido->id])
        ->assertOk()->assertJsonPath('pago.referencia', 'conservar');
    $this->putJson("/api/pagos/{$pago->id}", ['estado_pago_id' => $pendiente])->assertOk();
    $this->deleteJson("/api/pagos/{$pago->id}")->assertOk();
    $this->putJson("/api/pagos/{$pago->id}/restore")->assertOk();
    expect(Pago::count())->toBe(1);
});

it('protege estados y métodos que ya están asociados a pagos', function () {
    $ctx = contextoGestionPago();
    $this->actingAs($ctx['admin'], 'sanctum');
    $this->postJson("/api/pedidos/{$ctx['pedido']->id}/pagos", [
        'metodo_pago_id' => $ctx['metodo']->id, 'monto' => 1250,
    ])->assertCreated();
    $pago = Pago::firstOrFail();
    $this->putJson("/api/estado-pagos/{$pago->estado_pago_id}", ['nombre' => 'Otro estado'])->assertUnprocessable();
    $this->deleteJson("/api/estado-pagos/{$pago->estado_pago_id}")->assertStatus(409);
    $this->putJson("/api/metodos-pago/{$ctx['metodo']->id}", ['nombre' => 'Otro medio', 'activo' => true])->assertUnprocessable();
    $this->deleteJson("/api/metodos-pago/{$ctx['metodo']->id}")->assertStatus(409);
});

it('no restaura un pago anulado si el pedido ya tiene otro pago activo', function () {
    $ctx = contextoGestionPago();
    $this->actingAs($ctx['admin'], 'sanctum');
    $body = ['metodo_pago_id' => $ctx['metodo']->id, 'monto' => 1250];
    $url = "/api/pedidos/{$ctx['pedido']->id}/pagos";
    $this->postJson($url, $body)->assertCreated();
    $anulado = Pago::firstOrFail();
    $this->deleteJson("/api/pagos/{$anulado->id}")->assertOk();
    $this->postJson($url, $body)->assertCreated();
    $this->putJson("/api/pagos/{$anulado->id}/restore")->assertUnprocessable();
    expect(Pago::where('pedido_id', $ctx['pedido']->id)->count())->toBe(1);
});

it('siembra pagos de prueba con relaciones válidas y permite consultarlos', function () {
    $this->seed([EstadoPedidoSeeder::class, MetodoPagoSeeder::class, EstadoPagoSeeder::class, PagoSeeder::class]);
    $role = Role::create(['nombre' => 'Administrador']);
    $admin = User::factory()->create(['role_id' => $role->id, 'activo' => true]);
    $pago = Pago::firstOrFail();
    expect($pago->pedido)->not->toBeNull()
        ->and($pago->metodoPago->nombre)->toBe('Efectivo')
        ->and($pago->estadoPago->nombre)->toBe('Pendiente')
        ->and((float) $pago->monto)->toBe((float) $pago->pedido->monto_total);
    $this->actingAs($admin, 'sanctum')->getJson("/api/pagos?pedido_id={$pago->pedido_id}")
        ->assertOk()->assertJsonPath('pagos.0.id', $pago->id);
});

it('incluye el seeder de pagos de prueba en DatabaseSeeder', function () {
    $this->seed(DatabaseSeeder::class);
    $cliente = Cliente::where('username', 'like', 'pago_test_%')->firstOrFail();
    $pedido = Pedido::where('cliente_id', $cliente->id)->firstOrFail();
    expect($pedido->pagos()->count())->toBe(1)
        ->and($pedido->pagos()->first()->estadoPago->nombre)->toBe('Pendiente');
});

it('conserva el envío y actualiza el pago pendiente al editar un ítem', function () {
    $ctx = contextoGestionPago();
    $ctx['pedido']->update(['subtotal' => 1000, 'monto_total' => 2500]);
    $categoria = Categoria::create(['nombre' => 'Comidas', 'activo' => true]);
    $producto = Producto::create(['categoria_id' => $categoria->id, 'nombre' => 'Pizza', 'precio' => 1000, 'activo' => true]);
    $item = PedidoItem::create([
        'pedido_id' => $ctx['pedido']->id, 'producto_id' => $producto->id,
        'cantidad' => 1, 'precio_unitario' => 1000, 'subtotal' => 1000,
    ]);
    $this->actingAs($ctx['admin'], 'sanctum');
    $this->postJson("/api/pedidos/{$ctx['pedido']->id}/pagos", [
        'metodo_pago_id' => $ctx['metodo']->id, 'monto' => 2500,
    ])->assertCreated();
    $this->putJson("/api/pedidos/{$ctx['pedido']->id}", ['subtotal' => 500])->assertUnprocessable();
    $this->putJson("/api/pedido-items/{$item->id}", ['cantidad' => 2])->assertOk();
    expect((float) $ctx['pedido']->fresh()->subtotal)->toBe(2000.0)
        ->and((float) $ctx['pedido']->fresh()->monto_total)->toBe(3500.0)
        ->and((float) Pago::firstOrFail()->monto)->toBe(3500.0);
    $agregado = $this->postJson("/api/pedidos/{$ctx['pedido']->id}/items", [
        'producto_id' => $producto->id, 'cantidad' => 1,
    ])->assertCreated()->json('item.id');
    expect((float) Pago::firstOrFail()->monto)->toBe(4500.0);
    $this->deleteJson("/api/pedido-items/{$agregado}")->assertOk();
    expect((float) Pago::firstOrFail()->monto)->toBe(3500.0)
        ->and((float) $ctx['pedido']->fresh()->monto_total)->toBe(3500.0);
    $pagado = EstadoPagos::create(['nombre' => 'Pagado']);
    Pago::firstOrFail()->update(['estado_pago_id' => $pagado->id, 'fecha_pago' => now()->toDateString()]);
    $this->putJson("/api/pedido-items/{$item->id}", ['cantidad' => 3])->assertUnprocessable();
    expect((float) $ctx['pedido']->fresh()->monto_total)->toBe(3500.0);
});

it('anula lógicamente el pago pendiente al cancelar el pedido', function () {
    $ctx = contextoGestionPago();
    $cancelado = EstadoPedido::create(['nombre' => 'Cancelado']);
    $this->actingAs($ctx['admin'], 'sanctum');
    $this->postJson("/api/pedidos/{$ctx['pedido']->id}/pagos", [
        'metodo_pago_id' => $ctx['metodo']->id, 'monto' => 1250,
    ])->assertCreated();
    $pago = Pago::firstOrFail();
    $this->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $cancelado->id])->assertOk();
    expect(Pago::find($pago->id))->toBeNull()->and(Pago::withTrashed()->findOrFail($pago->id)->trashed())->toBeTrue();
    $this->putJson("/api/pagos/{$pago->id}/restore")->assertUnprocessable();
});

it('no duplica métodos y estados al ejecutar sus seeders dos veces', function () {
    $this->seed([MetodoPagoSeeder::class, EstadoPagoSeeder::class, MetodoPagoSeeder::class, EstadoPagoSeeder::class]);
    expect(MetodoPago::count())->toBe(2)
        ->and(EstadoPagos::count())->toBe(3)
        ->and((bool) MetodoPago::where('nombre', 'Transferencia bancaria')->firstOrFail()->activo)->toBeFalse();
});
