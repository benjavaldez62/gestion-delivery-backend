<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\EstadoPagos;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PagoEfectivoService
{
    public function registrarPendiente(Pedido $pedido, bool $permitirCrear = true): Pago
    {
        $pedido->loadMissing('metodoPago');
        if (! $pedido->metodoPago || mb_strtolower(trim($pedido->metodoPago->nombre)) !== 'efectivo') {
            throw ValidationException::withMessages(['metodo_pago' => 'Solo está habilitado el pago en efectivo.']);
        }
        if ((float) $pedido->monto_total <= 0) {
            throw ValidationException::withMessages(['monto' => 'El total del pedido debe ser mayor a cero.']);
        }

        $pagos = Pago::where('pedido_id', $pedido->id)->lockForUpdate()->get();
        if ($pagos->count() > 1) {
            throw ValidationException::withMessages(['pago' => 'El pedido tiene más de un pago y requiere revisión.']);
        }
        if ($pagos->isNotEmpty()) {
            $pago = $pagos->first();
            if ((int) $pago->metodo_pago_id !== (int) $pedido->metodo_pago_id ||
                (int) round((float) $pago->monto * 100) !== (int) round((float) $pedido->monto_total * 100)) {
                throw ValidationException::withMessages(['pago' => 'El pago no coincide con el método o total del pedido.']);
            }
            if ($permitirCrear && mb_strtolower(trim($pago->estadoPago?->nombre ?? '')) !== 'pendiente') {
                throw ValidationException::withMessages(['pago' => 'El pago del pedido no está pendiente.']);
            }

            return $pago;
        }

        if (! $permitirCrear) {
            throw ValidationException::withMessages(['pago' => 'El pedido no tiene un pago pendiente.']);
        }

        $estado = EstadoPagos::whereRaw('LOWER(TRIM(nombre)) = ?', ['pendiente'])->first()
            ?? EstadoPagos::create(['nombre' => 'Pendiente', 'descripcion' => 'El pago está pendiente de ser realizado']);

        return Pago::create([
            'pedido_id' => $pedido->id,
            'metodo_pago_id' => $pedido->metodo_pago_id,
            'monto' => $pedido->monto_total,
            'fecha_pago' => null,
            'estado_pago_id' => $estado->id,
        ]);
    }

    public function confirmarCobro(Pedido $pedido): Comprobante
    {
        $pago = $this->registrarPendiente($pedido, false);
        $pago->load('estadoPago');
        $estadoActual = mb_strtolower(trim($pago->estadoPago?->nombre ?? ''));
        if (! in_array($estadoActual, ['pendiente', 'pagado'], true)) {
            throw ValidationException::withMessages(['pago' => 'El estado del pago requiere revisión antes de completar la entrega.']);
        }
        if ($estadoActual === 'pagado' && ! $pago->fecha_pago) {
            throw ValidationException::withMessages(['pago' => 'El pago confirmado no tiene fecha y requiere revisión.']);
        }
        $fechaActual = now(config('app.pagos_timezone'));
        if ($estadoActual === 'pendiente') {
            $pagado = EstadoPagos::whereRaw('LOWER(TRIM(nombre)) = ?', ['pagado'])->first()
                ?? EstadoPagos::create(['nombre' => 'Pagado', 'descripcion' => 'El pago ha sido completado exitosamente']);
            $pago->update(['estado_pago_id' => $pagado->id, 'fecha_pago' => $fechaActual->toDateString()]);
        }

        $numero = 'T-'.Str::uuid();
        $comprobante = Comprobante::firstOrCreate(
            ['pago_id' => $pago->id, 'tipo_comprobante' => 'Ticket no fiscal'],
            [
                'numero_comprobante' => $numero,
                'titular' => $pedido->nombre_cliente ?? $pedido->cliente?->username ?? 'Cliente',
                'contenido_ticket' => app(TicketService::class)->construir(
                    $pedido,
                    $numero,
                    $fechaActual,
                ),
            ],
        );
        if (! $comprobante->contenido_ticket) {
            throw ValidationException::withMessages(['pago' => 'El ticket existente no tiene contenido y requiere revisión.']);
        }

        return $comprobante;
    }
}
