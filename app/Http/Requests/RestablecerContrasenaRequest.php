<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RestablecerContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('La contraseña debe tener como máximo 72 bytes y no puede contener caracteres nulos.');
                }
            }],
        ];
    }
}
