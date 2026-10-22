<?php

namespace App\Http\Requests;

use App\Models\PedidoItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePedidoItemRequest extends FormRequest
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
            'cantidad' => [
                'required',
                'integer',
                'min:1',
            ],
            'producto_id' => [
                'sometimes',
                'integer',
                Rule::exists('productos', 'id')->where(function ($query) {
                    $query->where('activo', true)
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'producto_id.integer' => 'El ID del producto debe ser un número entero.',
            'producto_id.exists' => 'El producto seleccionado no existe o no se encuentra activo.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $pedidoItemParam = $this->route('pedidoItem');
            $item = $pedidoItemParam instanceof PedidoItem
                ? $pedidoItemParam
                : PedidoItem::with('producto')->find($pedidoItemParam);

            if ($item && $item->producto && (! $item->producto->activo || $item->producto->trashed())) {
                $validator->errors()->add(
                    'producto_id',
                    'No se puede modificar un ítem cuyo producto se encuentra inactivo.'
                );
            }
        });
    }
}
