<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pedido_id' => ['sometimes', 'required', 'integer', 'exists:pedidos,id'],
            'metodo_pago_id' => ['sometimes', 'required', 'integer', 'exists:metodos_pago,id'],
            'monto' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,2'],
            'fecha_pago' => ['prohibited'],
            'estado_pago_id' => ['required', 'integer', 'exists:estado_pagos,id'],
            'referencia' => ['nullable', 'string', 'max:255'],
        ];
    }
}
