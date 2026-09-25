<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
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
            'subtotal' => $this->subtotal ? (float) $this->subtotal : null,
            'monto_total' => (float) $this->monto_total,
            'cliente' => new ClienteResource($this->whenLoaded('cliente')),
            'cocinero' => new UserResource($this->whenLoaded('cocinero')),
            'repartidor' => new UserResource($this->whenLoaded('repartidor')),
            'estado' => $this->whenLoaded('estadoPedido', function () {
                return [
                    'id' => $this->estadoPedido->id,
                    'nombre' => $this->estadoPedido->nombre,
                ];
            }),
            'items' => PedidoItemResource::collection($this->whenLoaded('items')),
            'pagos' => PagoResource::collection($this->whenLoaded('pagos')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
