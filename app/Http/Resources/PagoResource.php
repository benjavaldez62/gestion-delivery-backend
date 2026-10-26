<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'monto' => (float) $this->monto,
            'fecha_pago' => $this->fecha_pago,
            'referencia' => $this->referencia,
            'metodo_pago' => $this->whenLoaded('metodoPago', function () {
                return [
                    'id' => $this->metodoPago->id,
                    'nombre' => $this->metodoPago->nombre,
                ];
            }),
            'estado_pago' => $this->whenLoaded('estadoPago', function () {
                return [
                    'id' => $this->estadoPago->id,
                    'nombre' => $this->estadoPago->nombre,
                ];
            }),
            'comprobantes' => ComprobanteResource::collection($this->whenLoaded('comprobantes')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
