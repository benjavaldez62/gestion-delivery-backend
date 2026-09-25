<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoItemResource extends JsonResource
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
            'producto' => new ProductoResource($this->whenLoaded('producto')),
            'cantidad' => $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
