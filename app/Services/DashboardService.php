<?php

namespace App\Services;

use App\Models\EstadoPedido;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de consultas agregadas y métricas operativas para el panel de administración (Dashboard).
 */
class DashboardService
{
    /**
     * Obtiene el resumen completo de indicadores, distribuciones, personal y pedidos pendientes de asignación.
     *
     * @param  string  $periodo  Periodo de consulta ('hoy', '7d', '30d', 'historico').
     * @return array<string, mixed>
     */
    public function obtenerResumen(string $periodo = 'historico'): array
    {
        $estados = EstadoPedido::all();

        $idsPrePedido = $estados
            ->filter(fn (EstadoPedido $e) => in_array(mb_strtolower(trim($e->nombre)), ['pre-pedido', 'pre pedido', 'pre_pedido'], true))
            ->pluck('id')
            ->values()
            ->all();

        $estadosOperativos = $estados
            ->reject(fn (EstadoPedido $e) => in_array($e->id, $idsPrePedido, true))
            ->values();

        $idsCancelado = $estadosOperativos
            ->filter(fn (EstadoPedido $e) => mb_strtolower(trim($e->nombre)) === 'cancelado')
            ->pluck('id')
            ->values()
            ->all();

        $idsEntregado = $estadosOperativos
            ->filter(fn (EstadoPedido $e) => mb_strtolower(trim($e->nombre)) === 'entregado')
            ->pluck('id')
            ->values()
            ->all();

        $idsSinAsignar = $estadosOperativos
            ->filter(fn (EstadoPedido $e) => mb_strtolower(trim($e->nombre)) === 'sin asignar')
            ->pluck('id')
            ->values()
            ->all();

        $idsFinalizados = array_values(array_unique(array_merge($idsCancelado, $idsEntregado)));

        // Consultas base dentro del periodo seleccionado (excluyendo pre-pedidos sin confirmar)
        $basePeriodo = $this->aplicarFiltroPeriodo($this->excluirPrePedidos(Pedido::query(), $idsPrePedido), $periodo);

        $totalPedidos = (int) (clone $basePeriodo)->count();

        $pedidosValidosQuery = (clone $basePeriodo);
        if (! empty($idsCancelado)) {
            $pedidosValidosQuery->whereNotIn('estado_pedidos_id', $idsCancelado);
        }

        $pedidosValidos = (int) (clone $pedidosValidosQuery)->count();
        $ingresosTotales = round((float) ((clone $pedidosValidosQuery)->sum('monto_total') ?? 0), 2);
        $ticketPromedio = $pedidosValidos > 0
            ? round((float) ((clone $pedidosValidosQuery)->avg('monto_total') ?? 0), 2)
            : 0.0;

        $pedidosEntregados = ! empty($idsEntregado)
            ? (int) (clone $basePeriodo)->whereIn('estado_pedidos_id', $idsEntregado)->count()
            : 0;

        $pedidosCancelados = ! empty($idsCancelado)
            ? (int) (clone $basePeriodo)->whereIn('estado_pedidos_id', $idsCancelado)->count()
            : 0;

        $tasaEntrega = $pedidosValidos > 0
            ? round(($pedidosEntregados / $pedidosValidos) * 100, 1)
            : 0.0;

        // Consultas operativas sobre pedidos actualmente activos (no entregados, ni cancelados, ni pre-pedidos)
        $pedidosActivosBase = $this->excluirPrePedidos(Pedido::query(), $idsPrePedido);
        if (! empty($idsFinalizados)) {
            $pedidosActivosBase->whereNotIn('estado_pedidos_id', $idsFinalizados);
        }

        $pedidosEnCurso = (int) (clone $pedidosActivosBase)->count();

        $pedidosParaAsignarModels = (clone $pedidosActivosBase)
            ->with(['cliente', 'estadoPedido', 'metodoPago', 'cocinero', 'repartidor'])
            ->where(function (Builder $query) use ($idsSinAsignar) {
                $query->whereNull('cocinero_id')
                    ->orWhereNull('repartidor_id');

                if (! empty($idsSinAsignar)) {
                    $query->orWhereIn('estado_pedidos_id', $idsSinAsignar);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $pedidosSinAsignar = $pedidosParaAsignarModels->count();

        // Distribución por estado de pedido en el periodo (excluyendo el estado Pre-pedido)
        $agrupadosPorEstado = $this->aplicarFiltroPeriodo($this->excluirPrePedidos(Pedido::query(), $idsPrePedido), $periodo)
            ->select(
                'estado_pedidos_id',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('COALESCE(SUM(monto_total), 0) as monto_total')
            )
            ->groupBy('estado_pedidos_id')
            ->get()
            ->keyBy('estado_pedidos_id');

        $porEstado = $estadosOperativos->map(function (EstadoPedido $estado) use ($agrupadosPorEstado, $totalPedidos) {
            $fila = $agrupadosPorEstado->get($estado->id);
            $cantidad = $fila ? (int) $fila->cantidad : 0;
            $montoTotal = $fila ? round((float) $fila->monto_total, 2) : 0.0;

            return [
                'id' => (int) $estado->id,
                'nombre' => $estado->nombre,
                'cantidad' => $cantidad,
                'monto_total' => $montoTotal,
                'porcentaje' => $totalPedidos > 0 ? round(($cantidad / $totalPedidos) * 100, 1) : 0.0,
            ];
        })->values()->all();

        // Distribución por método de pago (solo pedidos válidos no cancelados ni pre-pedidos en el periodo)
        $metodosPago = MetodoPago::where('activo', true)->get();

        $agrupadosPorMetodoQuery = $this->aplicarFiltroPeriodo($this->excluirPrePedidos(Pedido::query(), $idsPrePedido), $periodo);
        if (! empty($idsCancelado)) {
            $agrupadosPorMetodoQuery->whereNotIn('estado_pedidos_id', $idsCancelado);
        }

        $agrupadosPorMetodo = $agrupadosPorMetodoQuery
            ->whereNotNull('metodo_pago_id')
            ->select(
                'metodo_pago_id',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('COALESCE(SUM(monto_total), 0) as monto_total')
            )
            ->groupBy('metodo_pago_id')
            ->get()
            ->keyBy('metodo_pago_id');

        // Ordenar por monto recaudado descendente para presentar primero el medio de pago principal
        $porMetodoPago = $metodosPago->map(function (MetodoPago $metodo) use ($agrupadosPorMetodo, $ingresosTotales) {
            $fila = $agrupadosPorMetodo->get($metodo->id);
            $cantidad = $fila ? (int) $fila->cantidad : 0;
            $montoTotal = $fila ? round((float) $fila->monto_total, 2) : 0.0;

            return [
                'id' => (int) $metodo->id,
                'nombre' => $metodo->nombre,
                'cantidad' => $cantidad,
                'monto_total' => $montoTotal,
                'porcentaje' => $ingresosTotales > 0 ? round(($montoTotal / $ingresosTotales) * 100, 1) : 0.0,
            ];
        })->sortByDesc('monto_total')->values()->all();

        // Estado operativo del personal activo (Cocineros y Repartidores)
        $cocineros = User::with('role')
            ->where('activo', true)
            ->whereHas('role', function (Builder $query) {
                $query->whereRaw('LOWER(nombre) = ?', ['cocinero']);
            })
            ->get();

        $repartidores = User::with('role')
            ->where('activo', true)
            ->whereHas('role', function (Builder $query) {
                $query->whereRaw('LOWER(nombre) = ?', ['repartidor']);
            })
            ->get();

        $staffCocineros = $cocineros->map(function (User $user) use ($idsFinalizados, $idsEntregado, $idsPrePedido, $periodo) {
            return $this->construirResumenStaff($user, 'cocinero_id', $idsFinalizados, $idsEntregado, $idsPrePedido, $periodo);
        })->values()->all();

        $staffRepartidores = $repartidores->map(function (User $user) use ($idsFinalizados, $idsEntregado, $idsPrePedido, $periodo) {
            return $this->construirResumenStaff($user, 'repartidor_id', $idsFinalizados, $idsEntregado, $idsPrePedido, $periodo);
        })->values()->all();

        // Lista detallada de pedidos pendientes de asignación
        $pedidosParaAsignar = $pedidosParaAsignarModels->map(function (Pedido $p) {
            return [
                'id' => (int) $p->id,
                'codigo' => $p->codigo,
                'monto_total' => (float) $p->monto_total,
                'created_at' => $p->created_at?->toISOString(),
                'cliente' => $p->cliente ? [
                    'id' => (int) $p->cliente->id,
                    'username' => $p->cliente->username,
                ] : null,
                'estado' => $p->estadoPedido ? [
                    'id' => (int) $p->estadoPedido->id,
                    'nombre' => $p->estadoPedido->nombre,
                ] : null,
                'metodo_pago' => $p->metodoPago?->nombre,
                'cocinero' => $p->cocinero ? [
                    'id' => (int) $p->cocinero->id,
                    'name' => $p->cocinero->name,
                ] : null,
                'repartidor' => $p->repartidor ? [
                    'id' => (int) $p->repartidor->id,
                    'name' => $p->repartidor->name,
                ] : null,
            ];
        })->values()->all();

        return [
            'periodo' => $periodo,
            'kpis' => [
                'ingresos_totales' => $ingresosTotales,
                'total_pedidos' => $totalPedidos,
                'pedidos_validos' => $pedidosValidos,
                'pedidos_entregados' => $pedidosEntregados,
                'pedidos_cancelados' => $pedidosCancelados,
                'pedidos_en_curso' => $pedidosEnCurso,
                'pedidos_sin_asignar' => $pedidosSinAsignar,
                'ticket_promedio' => $ticketPromedio,
                'tasa_entrega' => $tasaEntrega,
            ],
            'por_estado' => $porEstado,
            'por_metodo_pago' => $porMetodoPago,
            'staff' => [
                'cocineros' => $staffCocineros,
                'repartidores' => $staffRepartidores,
            ],
            'pedidos_para_asignar' => $pedidosParaAsignar,
        ];
    }

    /**
     * Aplica el filtro de rango temporal sobre la consulta de pedidos según el periodo indicado.
     *
     * @param  Builder<Pedido>  $query
     * @return Builder<Pedido>
     */
    private function aplicarFiltroPeriodo(Builder $query, string $periodo): Builder
    {
        return match ($periodo) {
            'hoy' => $query->whereBetween('created_at', [
                Carbon::today(),
                Carbon::now()->endOfDay(),
            ]),
            '7d' => $query->whereBetween('created_at', [
                Carbon::now()->subDays(6)->startOfDay(),
                Carbon::now()->endOfDay(),
            ]),
            '30d' => $query->whereBetween('created_at', [
                Carbon::now()->subDays(29)->startOfDay(),
                Carbon::now()->endOfDay(),
            ]),
            default => $query,
        };
    }

    /**
     * Excluye los pedidos en estado 'Pre-pedido' de las consultas operativas del dashboard.
     *
     * @param  Builder<Pedido>  $query
     * @param  array<int, int>  $idsPrePedido
     * @return Builder<Pedido>
     */
    private function excluirPrePedidos(Builder $query, array $idsPrePedido): Builder
    {
        if (! empty($idsPrePedido)) {
            $query->whereNotIn('estado_pedidos_id', $idsPrePedido);
        }

        return $query->whereHas('estadoPedido', function (Builder $q) {
            $q->whereRaw("LOWER(TRIM(nombre)) != 'pre-pedido'");
        });
    }

    /**
     * Construye el resumen de carga operativa y desempeño para un miembro del personal.
     *
     * @param  array<int, int>  $idsFinalizados
     * @param  array<int, int>  $idsEntregado
     * @param  array<int, int>  $idsPrePedido
     * @return array<string, mixed>
     */
    private function construirResumenStaff(
        User $user,
        string $columnaAsignacion,
        array $idsFinalizados,
        array $idsEntregado,
        array $idsPrePedido,
        string $periodo
    ): array {
        $activosQuery = $this->excluirPrePedidos(
            Pedido::with(['estadoPedido', 'cliente'])->where($columnaAsignacion, $user->id),
            $idsPrePedido
        );

        if ($columnaAsignacion === 'cocinero_id') {
            $activosQuery->whereHas('estadoPedido', function (Builder $query) {
                $query->whereIn(DB::raw('LOWER(TRIM(nombre))'), ['sin asignar', 'pendiente', 'en preparación', 'en preparacion']);
            });
        } elseif (! empty($idsFinalizados)) {
            $activosQuery->whereNotIn('estado_pedidos_id', $idsFinalizados);
        }

        $pedidosActivosModels = $activosQuery->orderBy('created_at', 'desc')->get();

        $pedidosActivos = $pedidosActivosModels->map(function (Pedido $p) {
            return [
                'id' => (int) $p->id,
                'codigo' => $p->codigo,
                'estado' => $p->estadoPedido?->nombre,
                'cliente' => $p->cliente?->username,
                'monto_total' => (float) $p->monto_total,
            ];
        })->values()->all();

        $completadosCount = 0;
        if (! empty($idsEntregado)) {
            $completadosCount = $this->aplicarFiltroPeriodo($this->excluirPrePedidos(Pedido::query(), $idsPrePedido), $periodo)
                ->where($columnaAsignacion, $user->id)
                ->whereIn('estado_pedidos_id', $idsEntregado)
                ->count();
        }

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rol' => $user->role?->nombre,
            'disponible' => count($pedidosActivos) === 0,
            'pedidos_activos_count' => count($pedidosActivos),
            'pedidos_completados_periodo' => (int) $completadosCount,
            'pedidos_activos' => $pedidosActivos,
        ];
    }
}
