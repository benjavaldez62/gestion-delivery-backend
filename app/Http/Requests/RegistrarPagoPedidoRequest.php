<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarPagoPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pedido_id' => [$this->route('pedido') ? 'prohibited' : 'required', 'integer', 'exists:pedidos,id'],
            'metodo_pago_id' => ['required', 'integer', 'exists:metodos_pago,id'],
            'monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'fecha_pago' => ['prohibited'],
            'estado_pago_id' => ['prohibited'],
        ];
    }
}
