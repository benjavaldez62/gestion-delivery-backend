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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function contextoEntregaEfectivo(): array
{
    config(['services.telegram.bot_token' => 'token-ficticio']);
    $role = Role::create(['nombre' => 'Repartidor']);
    $repartidor = User::factory()->create(['role_id' => $role->id, 'activo' => true]);
    $cliente = Cliente::create(['username' => 'cliente-prueba']);
    $enCamino = EstadoPedido::create(['nombre' => 'En camino']);
    $entregado = EstadoPedido::create(['nombre' => 'Entregado']);
    $metodo = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true]);
    $pendiente = EstadoPagos::create(['nombre' => 'Pendiente']);
    $pedido = Pedido::create([
        'cliente_id' => $cliente->id,
        'repartidor_id' => $repartidor->id,
        'estado_pedidos_id' => $enCamino->id,
        'metodo_pago_id' => $metodo->id,
        'subtotal' => 1000,
        'monto_total' => 2500,
        'telegram_chat_id' => 12345,
    ]);
    $pago = Pago::create([
        'pedido_id' => $pedido->id,
        'metodo_pago_id' => $metodo->id,
        'estado_pago_id' => $pendiente->id,
        'monto' => 2500,
        'fecha_pago' => null,
    ]);

    return compact('repartidor', 'pedido', 'pago', 'entregado');
}

it('registra el pago pendiente al confirmar y lo cobra al finalizar la entrega', function () {
    config(['services.telegram.bot_token' => 'token-ficticio']);
    Http::fake();
    $role = Role::create(['nombre' => 'Repartidor']);
    $repartidor = User::factory()->create(['role_id' => $role->id, 'activo' => true]);
    $enCamino = EstadoPedido::create(['nombre' => 'En camino']);
    $entregado = EstadoPedido::create(['nombre' => 'Entregado']);
    $categoria = Categoria::create(['nombre' => 'Menú', 'activo' => true]);
    $producto = Producto::create([
        'categoria_id' => $categoria->id, 'nombre' => 'Plato', 'precio' => 1000, 'activo' => true,
    ]);

    $pedidoId = $this->postJson('/api/checkout/borradores', [
        'nombre_cliente' => 'Nombre Apellido', 'direccion' => 'Calle de prueba', 'telefono' => '123456789', 'metodo_pago' => 'efectivo',
        'items' => [['producto_id' => $producto->id, 'cantidad' => 1, 'adicionales' => []]],
    ])->assertCreated()->json('pedido_id');
    expect(Pago::where('pedido_id', $pedidoId)->count())->toBe(0);

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 12345],
            'from' => ['username' => 'usuario_ficticio'],
            'text' => "Pedido #{$pedidoId}",
        ],
    ])->assertOk()->assertJsonPath('status', 'confirmed');
    $pago = Pago::where('pedido_id', $pedidoId)->firstOrFail();
    expect($pago->estadoPago->nombre)->toBe('Pendiente')
        ->and($pago->fecha_pago)->toBeNull()
        ->and((float) $pago->monto)->toBe(2500.0);

    Pedido::findOrFail($pedidoId)->update(['repartidor_id' => $repartidor->id]);
    $this->actingAs($repartidor, 'sanctum')
        ->putJson("/api/pedidos/{$pedidoId}", ['estado_pedidos_id' => $enCamino->id])->assertOk();
    $this->putJson("/api/pedidos/{$pedidoId}", ['estado_pedidos_id' => $entregado->id])
        ->assertOk()->assertJsonPath('comprobante_enviado', true);
    expect($pago->fresh()->estadoPago->nombre)->toBe('Pagado')
        ->and($pago->fresh()->fecha_pago->toDateString())->toBe(now(config('app.pagos_timezone'))->toDateString())
        ->and(Comprobante::where('pago_id', $pago->id)->count())->toBe(1);
    Http::assertSent(fn ($request) => str_contains($request['text'], 'Ticket no fiscal')
        && str_contains($request['text'], 'Nombre Apellido')
        && str_contains($request['text'], 'Plato'));
});

it('registra entrega, pago y ticket no fiscal sin duplicarlos al reintentar', function () {
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $categoria = Categoria::create(['nombre' => 'Comidas', 'activo' => true]);
    $producto = Producto::create([
        'categoria_id' => $categoria->id, 'nombre' => 'Pizza & queso', 'precio' => 1000, 'activo' => true,
    ]);
    PedidoItem::create([
        'pedido_id' => $ctx['pedido']->id, 'producto_id' => $producto->id,
        'cantidad' => 1, 'precio_unitario' => 1000, 'subtotal' => 1000,
    ]);
    $producto->delete();
    $url = "/api/pedidos/{$ctx['pedido']->id}";
    $body = ['estado_pedidos_id' => $ctx['entregado']->id];

    $this->actingAs($ctx['repartidor'], 'sanctum')->putJson($url, $body)
        ->assertOk()->assertJsonPath('comprobante_enviado', true)
        ->assertJsonPath('pedido.pagos.0.estado_pago.nombre', 'Pagado');
    expect($ctx['pago']->fresh()->fecha_pago->toDateString())->toBe(now(config('app.pagos_timezone'))->toDateString())
        ->and(Comprobante::count())->toBe(1)
        ->and(Comprobante::first()->tipo_comprobante)->toBe('Ticket no fiscal');
    Http::assertSent(fn ($request) => $request['chat_id'] === 12345
        && str_contains($request['text'], 'Ticket no fiscal')
        && str_contains($request['text'], 'Pizza &amp; queso')
        && str_contains($request['text'], 'Envío: $1.500,00')
        && str_contains($request['text'], '2.500,00'));

    $ticketOriginal = Comprobante::firstOrFail()->contenido_ticket;
    $producto->update(['nombre' => 'Nombre modificado']);
    $this->postJson("/api/pedidos/{$ctx['pedido']->id}/ticket/enviar")->assertOk();
    $ultimoEnvio = Http::recorded()->last()[0];
    expect($ultimoEnvio['text'])->toBe($ticketOriginal)
        ->toContain('Pizza &amp; queso')->not->toContain('Nombre modificado');

    $this->putJson($url, $body)->assertOk();
    expect(Pago::count())->toBe(1)->and(Comprobante::count())->toBe(1);
});

it('conserva el cobro y permite reintentar si Telegram rechaza el ticket', function () {
    $intentos = 0;
    Http::fake(function () use (&$intentos) {
        $intentos++;

        return Http::response([], $intentos === 1 ? 503 : 200);
    });
    $ctx = contextoEntregaEfectivo();
    $this->actingAs($ctx['repartidor'], 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertOk()->assertJsonPath('comprobante_enviado', false);
    expect($ctx['pedido']->fresh()->estadoPedido->nombre)->toBe('Entregado')
        ->and($ctx['pago']->fresh()->estadoPago->nombre)->toBe('Pagado');

    $this->postJson("/api/pedidos/{$ctx['pedido']->id}/ticket/enviar")->assertOk();
    expect(Comprobante::count())->toBe(1)->and(Pago::count())->toBe(1);
});

it('guarda y muestra la fecha y hora original de emisión en Telegram y administración', function () {
    $this->travelTo(Carbon::parse('2026-03-16 02:23:51', 'UTC'));
    config(['app.pagos_timezone' => 'America/Argentina/Buenos_Aires']);
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $this->actingAs($ctx['repartidor'], 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertOk()->assertJsonPath('comprobante_enviado', true);
    $ticket = Comprobante::firstOrFail();
    $fechaHora = 'Fecha y hora de emisión: 15/03/2026 23:23:51';
    expect($ticket->contenido_ticket)->toContain($fechaHora)->not->toContain('Fecha de cobro:')
        ->not->toContain('America/Argentina/Buenos_Aires');
    Http::assertSent(fn ($request) => str_contains($request['text'], $fechaHora)
        && ! str_contains($request['text'], 'Fecha de cobro:')
        && ! str_contains($request['text'], 'America/Argentina/Buenos_Aires'));
    expect($ctx['pago']->fresh()->fecha_pago->toDateString())->toBe('2026-03-15');

    $admin = User::factory()->create(['role_id' => Role::create(['nombre' => 'Administrador'])->id, 'activo' => true]);
    $this->actingAs($admin, 'sanctum')->getJson('/api/pagos/resumen')
        ->assertOk()->assertJsonPath('fecha', '2026-03-15')->assertJsonPath('ingreso_diario', 2500);
    $this->getJson('/api/pagos/envios')->assertOk()
        ->assertJsonPath('fecha', '2026-03-15')->assertJsonPath('total_envios', 1500);

    $this->travel(2)->hours();
    $this->actingAs($ctx['repartidor'], 'sanctum');
    $this->postJson("/api/pedidos/{$ctx['pedido']->id}/ticket/enviar")->assertOk();
    expect(Http::recorded()->last()[0]['text'])->toBe($ticket->contenido_ticket);
    $this->actingAs($admin, 'sanctum')->getJson("/api/comprobantes/{$ticket->id}")
        ->assertOk()->assertJsonPath('comprobante.contenido_ticket', $ticket->contenido_ticket);
});

it('usa la misma zona horaria configurada para el cobro y el ticket', function () {
    $this->travelTo(Carbon::parse('2026-03-16 02:23:51', 'UTC'));
    config(['app.pagos_timezone' => 'UTC']);
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $this->actingAs($ctx['repartidor'], 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertOk();
    expect(Comprobante::firstOrFail()->contenido_ticket)
        ->toContain('Fecha y hora de emisión: 16/03/2026 02:23:51')
        ->not->toContain('(UTC)');
    expect($ctx['pago']->fresh()->fecha_pago->toDateString())->toBe('2026-03-16');
});

it('conserva sin cambios un ticket anterior que no incluye la hora', function () {
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $this->actingAs($ctx['repartidor'], 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertOk();
    $ticket = Comprobante::firstOrFail();
    $ticket->update(['contenido_ticket' => 'Ticket original sin hora']);

    $this->postJson("/api/pedidos/{$ctx['pedido']->id}/ticket/enviar")->assertOk();
    expect(Http::recorded()->last()[0]['text'])->toBe('Ticket original sin hora')
        ->and($ticket->fresh()->contenido_ticket)->toBe('Ticket original sin hora');
});

it('permite al administrador consultar solo el ticket original guardado', function () {
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $admin = User::factory()->create(['role_id' => Role::create(['nombre' => 'Administrador'])->id, 'activo' => true]);
    $this->getJson('/api/comprobantes/1')->assertUnauthorized();
    $this->actingAs($ctx['repartidor'], 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertOk();
    $comprobante = Comprobante::firstOrFail();
    $this->getJson("/api/comprobantes/{$comprobante->id}")->assertForbidden();

    $this->actingAs($admin, 'sanctum');
    $this->getJson("/api/comprobantes/{$comprobante->id}")
        ->assertOk()->assertJsonPath('comprobante.contenido_ticket', $comprobante->contenido_ticket);
    $this->getJson('/api/pagos')->assertOk()->assertJsonMissingPath('pagos.0.comprobantes.0.contenido_ticket');
    $comprobante->update(['contenido_ticket' => null]);
    $this->getJson("/api/comprobantes/{$comprobante->id}")
        ->assertOk()->assertJsonPath('comprobante.contenido_ticket', null);
    $this->getJson('/api/comprobantes/99999')->assertNotFound();
});

it('no reconstruye un ticket que no tiene contenido guardado', function () {
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $this->actingAs($ctx['repartidor'], 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertOk();
    Comprobante::firstOrFail()->update(['contenido_ticket' => null]);

    $this->postJson("/api/pedidos/{$ctx['pedido']->id}/ticket/enviar")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'El ticket no tiene contenido guardado y requiere revisión.');
    Http::assertSentCount(1);
});

it('divide los tickets extensos sin superar el límite de Telegram', function () {
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $categoria = Categoria::create(['nombre' => 'Comidas extensas', 'activo' => true]);
    for ($i = 1; $i <= 40; $i++) {
        $producto = Producto::create([
            'categoria_id' => $categoria->id, 'nombre' => str_repeat('P', 130).$i, 'precio' => 25, 'activo' => true,
        ]);
        PedidoItem::create([
            'pedido_id' => $ctx['pedido']->id, 'producto_id' => $producto->id,
            'cantidad' => 1, 'precio_unitario' => 25, 'subtotal' => 25,
        ]);
    }

    $this->actingAs($ctx['repartidor'], 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertOk()->assertJsonPath('comprobante_enviado', true);
    $mensajes = Http::recorded()->map(fn ($par) => $par[0]['text']);
    expect($mensajes->count())->toBeGreaterThan(1)
        ->and($mensajes->every(fn ($texto) => strlen(mb_convert_encoding($texto, 'UTF-16LE', 'UTF-8')) / 2 <= 3500))->toBeTrue()
        ->and(implode("\n", $mensajes->all()))->toContain(str_repeat('P', 130).'40')
        ->toContain('Total cobrado');
});

it('rechaza la entrega si el pago no coincide con el total', function () {
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $ctx['pago']->update(['monto' => 1]);
    $this->actingAs($ctx['repartidor'], 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertUnprocessable();
    expect($ctx['pedido']->fresh()->estadoPedido->nombre)->toBe('En camino')
        ->and($ctx['pago']->fresh()->estadoPago->nombre)->toBe('Pendiente')
        ->and(Comprobante::count())->toBe(0);
    Http::assertNothingSent();
});

it('no finaliza una entrega cuyo pago fue anulado', function () {
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $ctx['pago']->delete();
    $this->actingAs($ctx['repartidor'], 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertUnprocessable();
    expect($ctx['pedido']->fresh()->estadoPedido->nombre)->toBe('En camino')
        ->and(Comprobante::count())->toBe(0);
    Http::assertNothingSent();
});

it('rechaza la entrega por un repartidor diferente', function () {
    Http::fake();
    $ctx = contextoEntregaEfectivo();
    $otro = User::factory()->create(['role_id' => $ctx['repartidor']->role_id, 'activo' => true]);
    $this->actingAs($otro, 'sanctum')
        ->putJson("/api/pedidos/{$ctx['pedido']->id}", ['estado_pedidos_id' => $ctx['entregado']->id])
        ->assertForbidden();
    expect($ctx['pedido']->fresh()->estadoPedido->nombre)->toBe('En camino');
    Http::assertNothingSent();
});
