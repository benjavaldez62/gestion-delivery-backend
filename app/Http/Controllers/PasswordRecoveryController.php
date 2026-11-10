<?php

namespace App\Http\Controllers;

use App\Models\User;
use Closure;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class PasswordRecoveryController extends Controller
{
    public function solicitar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        Password::broker('users')->sendResetLink($validated, function (User $user, string $token) {
            try {
                $user->sendPasswordResetNotification($token);
            } catch (Throwable $exception) {
                Log::error('No se pudo procesar el correo de recuperación.', [
                    'exception_type' => $exception::class,
                ]);

                return;
            }

            event(new PasswordResetLinkSent($user));
        });

        return response()->json([
            'message' => 'Si el correo está registrado y se puede procesar la solicitud, recibirás un enlace de recuperación.',
        ])->header('Cache-Control', 'no-store');
    }

    public function restablecer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('La contraseña debe tener como máximo 72 bytes y no puede contener caracteres nulos.');
                }
            }],
        ]);

        $status = DB::transaction(function () use ($validated) {
            DB::table(config('auth.passwords.users.table'))
                ->where('email', $validated['email'])
                ->lockForUpdate()
                ->first();

            return Password::broker('users')->reset($validated, function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            });
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'El enlace de recuperación no es válido o venció. Solicitá uno nuevo.',
            ], 422)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'message' => 'Tu contraseña fue actualizada. Ya podés iniciar sesión.',
        ])->header('Cache-Control', 'no-store');
    }
}
