<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AsignarPersonalPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cocinero_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'repartidor_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cocinero_id.required' => 'Debe seleccionar un cocinero.',
            'cocinero_id.integer' => 'El cocinero seleccionado no es válido.',
            'cocinero_id.exists' => 'El cocinero seleccionado no existe.',
            'repartidor_id.required' => 'Debe seleccionar un repartidor.',
            'repartidor_id.integer' => 'El repartidor seleccionado no es válido.',
            'repartidor_id.exists' => 'El repartidor seleccionado no existe.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fields = [
                'cocinero_id' => 'Cocinero',
                'repartidor_id' => 'Repartidor',
            ];

            if (! $this->hasAny(array_keys($fields))) {
                $validator->errors()->add('asignacion', 'Debe indicar un cocinero o un repartidor.');

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($fields as $field => $roleName) {
                if (! $this->has($field)) {
                    continue;
                }

                $isActiveStaffMember = User::query()
                    ->whereKey($this->input($field))
                    ->where('activo', true)
                    ->whereHas('role', fn ($query) => $query->whereRaw('LOWER(nombre) = ?', [mb_strtolower($roleName)]))
                    ->exists();

                if (! $isActiveStaffMember) {
                    $validator->errors()->add($field, "El {$roleName} seleccionado debe estar activo y tener el rol correspondiente.");
                }
            }
        });
    }
}
