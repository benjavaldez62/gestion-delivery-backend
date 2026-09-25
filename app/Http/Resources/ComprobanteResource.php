<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComprobanteResource extends JsonResource
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
            'pago_id' => $this->pago_id,
            'tipo_comprobante' => $this->tipo_comprobante,
            'numero_comprobante' => $this->numero_comprobante,
            'titular' => $this->titular,
            'url_pdf' => $this->url_pdf,
            'created_at' => $this->created_at,
        ];
    }
}
