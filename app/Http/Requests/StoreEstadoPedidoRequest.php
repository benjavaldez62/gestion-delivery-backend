<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEstadoPedidoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:50', 'unique:estado_pedidos,nombre'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del estado de pedido es obligatorio.',
            'nombre.string' => 'El nombre del estado de pedido debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del estado de pedido no debe superar los 50 caracteres.',
            'nombre.unique' => 'Ya existe un estado de pedido con ese nombre.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no debe superar los 255 caracteres.',
        ];
    }
}
