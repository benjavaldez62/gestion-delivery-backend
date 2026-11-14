<?php

namespace App\Services;

use App\Models\Pedido;
use Carbon\CarbonInterface;

class TicketService
{
    public function construir(Pedido $pedido, string $numero, CarbonInterface $fechaEmision): string
    {
        $pedido->loadMissing(['items.producto', 'items.adicionales']);
        $lineas = [
            '🎉 <b>Pedido entregado y cobrado en efectivo</b>',
            '<b>Ticket no fiscal</b>',
            'Número: '.e($numero),
            'Pedido #'.$pedido->id,
            'Cliente: '.e($pedido->nombre_cliente ?? $pedido->cliente?->username ?? 'Cliente'),
            'Fecha y hora de emisión: '.e($fechaEmision->format('d/m/Y H:i:s')),
            '',
        ];
        foreach ($pedido->items as $item) {
            $nombre = $item->producto?->nombre ?? 'Producto no disponible';
            $lineas[] = $item->cantidad.' x '.e($nombre).' - $'.number_format((float) $item->subtotal, 2, ',', '.');
            foreach ($item->adicionales as $adicional) {
                $lineas[] = '  + '.e($adicional->nombre);
            }
        }
        $lineas[] = '';
        if ($pedido->subtotal !== null) {
            $lineas[] = 'Subtotal: $'.number_format((float) $pedido->subtotal, 2, ',', '.');
            $lineas[] = 'Envío: $'.number_format((float) $pedido->monto_total - (float) $pedido->subtotal, 2, ',', '.');
        }
        $lineas[] = '<b>Total cobrado: $'.number_format((float) $pedido->monto_total, 2, ',', '.').'</b>';
        $lineas[] = 'Medio de pago: efectivo';

        return implode("\n", $lineas);
    }
}
