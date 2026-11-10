<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBorradorCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direccion' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'telefono' => ['required', 'string', 'max:30', 'regex:/^(?=(?:[^0-9]*[0-9]){6})[0-9+() \-]{6,30}$/'],
            'notas' => ['nullable', 'string', 'max:500'],
            'metodo_pago' => ['required', Rule::in(['efectivo', 'tarjeta', 'transferencia'])],
            'monto_recibido' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'prohibited_unless:metodo_pago,efectivo'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->where('activo', true)->whereNull('deleted_at')],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.adicionales' => ['present', 'array', 'max:30'],
            'items.*.adicionales.*' => ['integer', Rule::exists('adicionals', 'id')->where('activo', true)->whereNull('deleted_at')],
        ];
    }
}
