<?php

use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Configura los roles base y retorna un mapa con Administrador, Cocinero y Repartidor.
 *
 * @return array<string, Role>
 */
function crearRolesBaseDashboard(): array
{
    return [
        'admin' => Role::create(['nombre' => 'Administrador', 'descripcion' => 'Acceso completo']),
        'cocinero' => Role::create(['nombre' => 'Cocinero', 'descripcion' => 'Cocina']),
        'repartidor' => Role::create(['nombre' => 'Repartidor', 'descripcion' => 'Reparto']),
    ];
}

it('rechaza con 401 a usuarios no autenticados en el endpoint del dashboard', function () {
    $this->getJson('/api/dashboard')->assertUnauthorized();
});

it('rechaza con 403 a usuarios con rol Cocinero o Repartidor en el endpoint del dashboard', function () {
    $roles = crearRolesBaseDashboard();

    $cocinero = User::factory()->create([
        'role_id' => $roles['cocinero']->id,
        'activo' => true,
    ]);

    $repartidor = User::factory()->create([
        'role_id' => $roles['repartidor']->id,
        'activo' => true,
    ]);

    $this->actingAs($cocinero, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertForbidden();

    $this->actingAs($repartidor, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertForbidden();
});

it('retorna 200 al Administrador con KPIs, distribuciones, disponibilidad de staff y pedidos sin asignar', function () {
    $roles = crearRolesBaseDashboard();

    $admin = User::factory()->create([
        'name' => 'Admin Test',
        'role_id' => $roles['admin']->id,
        'activo' => true,
    ]);

    $cocineroOcupado = User::factory()->create([
        'name' => 'Cocinero Ocupado',
        'email' => 'cocinero.ocupado@test.com',
        'role_id' => $roles['cocinero']->id,
        'activo' => true,
    ]);

    $cocineroLibre = User::factory()->create([
        'name' => 'Cocinero Libre',
        'email' => 'cocinero.libre@test.com',
        'role_id' => $roles['cocinero']->id,
        'activo' => true,
    ]);

    $repartidorOcupado = User::factory()->create([
        'name' => 'Repartidor Ocupado',
        'email' => 'repartidor.ocupado@test.com',
        'role_id' => $roles['repartidor']->id,
        'activo' => true,
    ]);

    $repartidorLibre = User::factory()->create([
        'name' => 'Repartidor Libre',
        'email' => 'repartidor.libre@test.com',
        'role_id' => $roles['repartidor']->id,
        'activo' => true,
    ]);

    $estadoSinAsignar = EstadoPedido::create(['nombre' => 'Sin asignar', 'descripcion' => 'Sin asignar']);
    $estadoEnPreparacion = EstadoPedido::create(['nombre' => 'En preparación', 'descripcion' => 'En preparación']);
    $estadoEntregado = EstadoPedido::create(['nombre' => 'Entregado', 'descripcion' => 'Entregado']);
    $estadoCancelado = EstadoPedido::create(['nombre' => 'Cancelado', 'descripcion' => 'Cancelado']);

    $metodoEfectivo = MetodoPago::create(['nombre' => 'Efectivo', 'descripcion' => 'Efectivo', 'activo' => true]);
    $metodoTransferencia = MetodoPago::create(['nombre' => 'Transferencia', 'descripcion' => 'Transferencia', 'activo' => true]);

    $cliente = Cliente::create([
        'username' => 'cliente_dashboard',
        'telefono' => '1122334455',
        'direccion' => 'Av. Siempre Viva 742',
    ]);

    // Pedido 1: Entregado ($10,000, Efectivo, cocineroOcupado + repartidorOcupado)
    Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estadoEntregado->id,
        'metodo_pago_id' => $metodoEfectivo->id,
        'cocinero_id' => $cocineroOcupado->id,
        'repartidor_id' => $repartidorOcupado->id,
        'subtotal' => 8500,
        'monto_total' => 10000,
    ]);

    // Pedido 2: En preparación con cocinero y repartidor asignados ($20,000, Transferencia)
    $pedidoActivoAsignado = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estadoEnPreparacion->id,
        'metodo_pago_id' => $metodoTransferencia->id,
        'cocinero_id' => $cocineroOcupado->id,
        'repartidor_id' => $repartidorOcupado->id,
        'subtotal' => 18500,
        'monto_total' => 20000,
    ]);

    // Pedido 3: Sin asignar y sin personal ($15,000, Efectivo) -> debe aparecer en pedidos_para_asignar
    $pedidoSinPersonal = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estadoSinAsignar->id,
        'metodo_pago_id' => $metodoEfectivo->id,
        'cocinero_id' => null,
        'repartidor_id' => null,
        'subtotal' => 13500,
        'monto_total' => 15000,
    ]);

    // Pedido 4: Cancelado ($5,000, Efectivo) -> no suma a ingresos_totales ni pedidos_validos
    Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estadoCancelado->id,
        'metodo_pago_id' => $metodoEfectivo->id,
        'cocinero_id' => null,
        'repartidor_id' => null,
        'subtotal' => 3500,
        'monto_total' => 5000,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonPath('message', 'Resumen del dashboard obtenido correctamente.')
        ->assertJsonPath('dashboard.periodo', 'historico')
        ->assertJsonStructure([
            'message',
            'dashboard' => [
                'periodo',
                'kpis' => [
                    'ingresos_totales',
                    'total_pedidos',
                    'pedidos_validos',
                    'pedidos_entregados',
                    'pedidos_cancelados',
                    'pedidos_en_curso',
                    'pedidos_sin_asignar',
                    'ticket_promedio',
                    'tasa_entrega',
                ],
                'por_estado',
                'por_metodo_pago',
                'staff' => [
                    'cocineros',
                    'repartidores',
                ],
                'pedidos_para_asignar',
            ],
        ]);

    $kpis = $response->json('dashboard.kpis');
    // Válidos: 10000 + 20000 + 15000 = 45000 (3 pedidos válidos de 4 totales)
    expect((float) $kpis['ingresos_totales'])->toBe(45000.0)
        ->and($kpis['total_pedidos'])->toBe(4)
        ->and($kpis['pedidos_validos'])->toBe(3)
        ->and($kpis['pedidos_entregados'])->toBe(1)
        ->and($kpis['pedidos_cancelados'])->toBe(1)
        ->and($kpis['pedidos_en_curso'])->toBe(2)
        ->and($kpis['pedidos_sin_asignar'])->toBe(1)
        ->and((float) $kpis['ticket_promedio'])->toBe(15000.0)
        ->and((float) $kpis['tasa_entrega'])->toBe(33.3);

    // Verificar distribución por método de pago (solo pedidos válidos)
    $porMetodo = collect($response->json('dashboard.por_metodo_pago'))->keyBy('nombre');
    expect($porMetodo['Efectivo']['cantidad'])->toBe(2)
        ->and((float) $porMetodo['Efectivo']['monto_total'])->toBe(25000.0)
        ->and((float) $porMetodo['Efectivo']['porcentaje'])->toBe(55.6)
        ->and($porMetodo['Transferencia']['cantidad'])->toBe(1)
        ->and((float) $porMetodo['Transferencia']['monto_total'])->toBe(20000.0)
        ->and((float) $porMetodo['Transferencia']['porcentaje'])->toBe(44.4)
        ->and($response->json('dashboard.admin_nombre'))->toBe('Admin Test');

    // Verificar disponibilidad de cocineros
    $cocineros = collect($response->json('dashboard.staff.cocineros'))->keyBy('email');
    expect($cocineros['cocinero.ocupado@test.com']['disponible'])->toBeFalse()
        ->and($cocineros['cocinero.ocupado@test.com']['pedidos_activos_count'])->toBe(1)
        ->and($cocineros['cocinero.ocupado@test.com']['pedidos_completados_periodo'])->toBe(1)
        ->and($cocineros['cocinero.ocupado@test.com']['pedidos_activos'][0]['id'])->toBe($pedidoActivoAsignado->id)
        ->and($cocineros['cocinero.libre@test.com']['disponible'])->toBeTrue()
        ->and($cocineros['cocinero.libre@test.com']['pedidos_activos_count'])->toBe(0);

    // Verificar disponibilidad de repartidores
    $repartidores = collect($response->json('dashboard.staff.repartidores'))->keyBy('email');
    expect($repartidores['repartidor.ocupado@test.com']['disponible'])->toBeFalse()
        ->and($repartidores['repartidor.ocupado@test.com']['pedidos_activos_count'])->toBe(1)
        ->and($repartidores['repartidor.ocupado@test.com']['pedidos_completados_periodo'])->toBe(1)
        ->and($repartidores['repartidor.libre@test.com']['disponible'])->toBeTrue();

    // Verificar cola de pedidos para asignar
    $paraAsignar = $response->json('dashboard.pedidos_para_asignar');
    expect($paraAsignar)->toHaveCount(1)
        ->and($paraAsignar[0]['id'])->toBe($pedidoSinPersonal->id)
        ->and($paraAsignar[0]['cliente']['username'])->toBe('cliente_dashboard');
});

it('filtra correctamente por periodo (hoy, 7d, 30d, historico) y rechaza periodos invalidos con 422', function () {
    $roles = crearRolesBaseDashboard();

    $admin = User::factory()->create([
        'role_id' => $roles['admin']->id,
        'activo' => true,
    ]);

    $estadoEntregado = EstadoPedido::create(['nombre' => 'Entregado', 'descripcion' => 'Entregado']);
    $cliente = Cliente::create([
        'username' => 'cliente_periodos',
        'telefono' => '1199887766',
        'direccion' => 'Calle Falsa 123',
    ]);

    $crearPedidoEnFecha = function (Carbon $fecha, float $monto) use ($cliente, $estadoEntregado): Pedido {
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'estado_pedidos_id' => $estadoEntregado->id,
            'subtotal' => $monto,
            'monto_total' => $monto,
        ]);

        $pedido->forceFill([
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ])->saveQuietly();

        return $pedido->fresh();
    };

    // 1 pedido hoy ($1,000)
    $crearPedidoEnFecha(Carbon::now(), 1000);
    // 1 pedido hace 3 días ($2,000) -> entra en 7d, 30d, historico
    $crearPedidoEnFecha(Carbon::now()->subDays(3), 2000);
    // 1 pedido hace 15 días ($4,000) -> entra en 30d, historico
    $crearPedidoEnFecha(Carbon::now()->subDays(15), 4000);
    // 1 pedido hace 45 días ($8,000) -> entra solo en historico
    $crearPedidoEnFecha(Carbon::now()->subDays(45), 8000);

    // Periodo: hoy
    $resHoy = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard?periodo=hoy');
    $resHoy->assertOk()
        ->assertJsonPath('dashboard.periodo', 'hoy')
        ->assertJsonPath('dashboard.kpis.total_pedidos', 1);
    expect((float) $resHoy->json('dashboard.kpis.ingresos_totales'))->toBe(1000.0);

    // Periodo: 7d
    $res7d = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard?periodo=7d');
    $res7d->assertOk()
        ->assertJsonPath('dashboard.periodo', '7d')
        ->assertJsonPath('dashboard.kpis.total_pedidos', 2);
    expect((float) $res7d->json('dashboard.kpis.ingresos_totales'))->toBe(3000.0);

    // Periodo: 30d
    $res30d = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard?periodo=30d');
    $res30d->assertOk()
        ->assertJsonPath('dashboard.periodo', '30d')
        ->assertJsonPath('dashboard.kpis.total_pedidos', 3);
    expect((float) $res30d->json('dashboard.kpis.ingresos_totales'))->toBe(7000.0);

    // Periodo: historico
    $resHistorico = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard?periodo=historico');
    $resHistorico->assertOk()
        ->assertJsonPath('dashboard.periodo', 'historico')
        ->assertJsonPath('dashboard.kpis.total_pedidos', 4);
    expect((float) $resHistorico->json('dashboard.kpis.ingresos_totales'))->toBe(15000.0);

    // Periodo inválido -> 422
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/dashboard?periodo=anual')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['periodo']);
});

it('excluye los pedidos en estado Pre-pedido de las metricas, distribucion de estados y cola de asignacion del dashboard (SD-338)', function () {
    $roles = crearRolesBaseDashboard();

    $admin = User::factory()->create([
        'role_id' => $roles['admin']->id,
        'activo' => true,
    ]);

    $estadoPrePedido = EstadoPedido::create(['nombre' => 'Pre-pedido', 'descripcion' => 'Pre-pedido web']);
    $estadoSinAsignar = EstadoPedido::create(['nombre' => 'Sin Asignar', 'descripcion' => 'Sin asignar']);
    $metodoEfectivo = MetodoPago::create(['nombre' => 'Efectivo', 'descripcion' => 'Efectivo', 'activo' => true]);

    $cliente = Cliente::create([
        'username' => 'cliente_prepedido',
        'telefono' => '3454112233',
        'direccion' => 'Urquiza 100',
    ]);

    // Pedido en Pre-pedido (no debe contabilizarse en el dashboard)
    Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estadoPrePedido->id,
        'metodo_pago_id' => $metodoEfectivo->id,
        'subtotal' => 5000,
        'monto_total' => 6500,
    ]);

    // Pedido confirmado en Sin Asignar (sí debe contabilizarse)
    $pedidoConfirmado = Pedido::create([
        'cliente_id' => $cliente->id,
        'estado_pedidos_id' => $estadoSinAsignar->id,
        'metodo_pago_id' => $metodoEfectivo->id,
        'subtotal' => 10000,
        'monto_total' => 11500,
    ]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonPath('dashboard.kpis.total_pedidos', 1)
        ->assertJsonPath('dashboard.kpis.pedidos_validos', 1)
        ->assertJsonPath('dashboard.kpis.pedidos_sin_asignar', 1)
        ->assertJsonCount(1, 'dashboard.pedidos_para_asignar')
        ->assertJsonPath('dashboard.pedidos_para_asignar.0.id', $pedidoConfirmado->id);

    expect((float) $response->json('dashboard.kpis.ingresos_totales'))->toBe(11500.0);

    $nombresEstados = collect($response->json('dashboard.por_estado'))->pluck('nombre')->all();
    expect($nombresEstados)->not->toContain('Pre-pedido')
        ->and($nombresEstados)->toContain('Sin Asignar');
});
