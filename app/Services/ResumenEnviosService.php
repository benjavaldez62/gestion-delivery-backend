<?php

namespace App\Services;

use App\Models\Pago;

class ResumenEnviosService
{
    public function calcular(string $fecha): array
    {
        $consultaPagados = Pago::whereHas('estadoPago', fn ($query) => $query->whereRaw('LOWER(TRIM(nombre)) = ?', ['pagado']));
        $pagos = (clone $consultaPagados)->whereDate('fecha_pago', $fecha)
            ->with([
                'pedido' => fn ($query) => $query->withTrashed(),
                'pedido.estadoPedido' => fn ($query) => $query->withTrashed(),
                'pedido.repartidor' => fn ($query) => $query->withTrashed(),
            ])->get();

        $duplicados = (clone $consultaPagados)->whereIn('pedido_id', $pagos->pluck('pedido_id')->unique())
            ->groupBy('pedido_id')->havingRaw('COUNT(*) > 1')->pluck('pedido_id')->all();
        $repartidores = [];
        $revision = [];
        $totalCentavos = 0;

        foreach ($pagos->unique('pedido_id') as $pago) {
            $pedido = $pago->pedido;
            $motivo = null;
            if (in_array($pago->pedido_id, $duplicados)) {
                $motivo = 'El pedido tiene más de un pago cobrado.';
            } elseif (! $pedido || mb_strtolower(trim($pedido->estadoPedido?->nombre ?? '')) !== 'entregado') {
                $motivo = 'El pedido no está registrado como entregado.';
            } elseif (! $pedido->repartidor) {
                $motivo = 'El pedido no tiene un repartidor disponible.';
            } elseif ($pedido->subtotal === null || $pedido->monto_total === null) {
                $motivo = 'El pedido no tiene subtotal o total para calcular el envío.';
            } elseif ((float) $pedido->subtotal < 0 || (float) $pedido->monto_total < 0) {
                $motivo = 'El pedido tiene importes negativos.';
            } elseif ((int) round((float) $pago->monto * 100) !== (int) round((float) $pedido->monto_total * 100)) {
                $motivo = 'El pago no coincide con el total del pedido.';
            } elseif ((float) $pedido->monto_total < (float) $pedido->subtotal) {
                $motivo = 'El total del pedido es menor que su subtotal.';
            }

            if ($motivo) {
                $revision[] = ['pedido_id' => $pago->pedido_id, 'motivo' => $motivo];

                continue;
            }

            $centavos = (int) round((float) $pedido->monto_total * 100) - (int) round((float) $pedido->subtotal * 100);
            $id = $pedido->repartidor->id;
            if (! isset($repartidores[$id])) {
                $repartidores[$id] = [
                    'id' => $id,
                    'nombre' => $pedido->repartidor->name,
                    'total_centavos' => 0,
                    'envios' => [],
                ];
            }
            $repartidores[$id]['total_centavos'] += $centavos;
            $repartidores[$id]['envios'][] = ['pedido_id' => $pedido->id, 'importe' => $centavos / 100];
            $totalCentavos += $centavos;
        }

        $repartidores = array_values(array_map(function (array $repartidor): array {
            $repartidor['total'] = $repartidor['total_centavos'] / 100;
            unset($repartidor['total_centavos']);

            return $repartidor;
        }, $repartidores));
        usort($repartidores, fn ($a, $b) => strcasecmp($a['nombre'], $b['nombre']));

        return [
            'fecha' => $fecha,
            'zona_horaria' => config('app.pagos_timezone'),
            'total_envios' => $totalCentavos / 100,
            'repartidores' => $repartidores,
            'pendientes_revision' => $revision,
        ];
    }
}
