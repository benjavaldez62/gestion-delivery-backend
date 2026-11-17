<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestablecerContrasenaRequest;
use App\Http\Requests\SolicitarRecuperacionRequest;
use App\Models\User;
use App\Notifications\RecuperarContrasena;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

class PasswordRecoveryController extends Controller
{
    #[OA\Post(
        path: '/api/forgot-password',
        summary: 'Solicitar recuperación de contraseña local',
        description: 'Las direcciones del dominio exacto test.com deben contactar al administrador. Las respuestas distinguen cuentas inexistentes e inactivas.',
        tags: ['Autenticación'],
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email'],
            properties: [new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255)]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Correo aceptado por SMTP (action: enlace_enviado) o cuenta de prueba (action: contactar_administrador).'),
            new OA\Response(response: 403, description: 'Cuenta inactiva.'),
            new OA\Response(response: 404, description: 'Correo no registrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos o solicitud no procesada.'),
            new OA\Response(response: 429, description: 'Límite de solicitudes alcanzado.'),
            new OA\Response(response: 503, description: 'Envío de correo no disponible o fallido.'),
        ]
    )]
    public function solicitar(SolicitarRecuperacionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = $validated['email'];

        if ($this->esCuentaDePrueba($email)) {
            return response()->json([
                'message' => 'Para cambiar la contraseña de esta cuenta, contactate con el administrador.',
                'action' => 'contactar_administrador',
            ])->header('Cache-Control', 'no-store');
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return response()->json([
                'message' => 'Email no ingresado al sistema.',
            ], 404)->header('Cache-Control', 'no-store');
        }
        if (! $user->activo) {
            return response()->json([
                'message' => 'El email ingresado pertenece a un usuario inactivo.',
            ], 403)->header('Cache-Control', 'no-store');
        }

        try {
            if (! Mail::mailer()->getSymfonyTransport() instanceof SmtpTransport) {
                throw new RuntimeException('El transporte de recuperación debe ser SMTP.');
            }

            $status = Password::broker('users')->sendResetLink(
                ['email' => $email, 'activo' => true],
                function (User $user, string $token) {
                    try {
                        if (! app(MailChannel::class)->send($user, new RecuperarContrasena($token))) {
                            throw new RuntimeException('El mensaje de recuperación no fue enviado.');
                        }
                    } catch (Throwable $exception) {
                        DB::transaction(function () use ($user, $token) {
                            $row = DB::table(config('auth.passwords.users.table'))
                                ->where('email', $user->getEmailForPasswordReset())
                                ->lockForUpdate()
                                ->first();
                            if ($row && Hash::check($token, $row->token)) {
                                Password::broker('users')->deleteToken($user);
                            }
                        });
                        throw $exception;
                    }

                    event(new PasswordResetLinkSent($user));
                }
            );
        } catch (Throwable $exception) {
            Log::error('No se pudo procesar el correo de recuperación.', [
                'exception_type' => $exception::class,
            ]);

            return response()->json([
                'message' => 'No se pudo enviar el correo. Intentá nuevamente más tarde.',
            ], 503)->header('Cache-Control', 'no-store');
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Esperá un momento antes de solicitar otro enlace.',
            ], 429)->header('Cache-Control', 'no-store');
        }
        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('No se pudo iniciar la recuperación de contraseña.', ['status' => $status]);

            return response()->json([
                'message' => 'No se pudo procesar la solicitud. Intentá nuevamente.',
            ], 422)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'message' => 'Se ha enviado un enlace de recuperación a tu correo.',
            'action' => 'enlace_enviado',
        ])->header('Cache-Control', 'no-store');
    }

    #[OA\Post(
        path: '/api/reset-password',
        summary: 'Restablecer contraseña local',
        description: 'Requiere cuenta activa y token vigente de un solo uso. Revoca los tokens Sanctum; no modifica la contraseña de Google.',
        tags: ['Autenticación'],
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'token', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Contraseña actualizada.'),
            new OA\Response(response: 403, description: 'Cuenta de prueba: contactar al administrador.'),
            new OA\Response(response: 422, description: 'Datos inválidos o enlace inválido, vencido o usado.'),
            new OA\Response(response: 429, description: 'Límite de solicitudes alcanzado.'),
        ]
    )]
    public function restablecer(RestablecerContrasenaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($this->esCuentaDePrueba($validated['email'])) {
            return response()->json([
                'message' => 'Para cambiar la contraseña de esta cuenta, contactate con el administrador.',
            ], 403)->header('Cache-Control', 'no-store');
        }

        $status = DB::transaction(function () use ($validated) {
            $user = User::where('email', $validated['email'])->lockForUpdate()->first();
            if (! $user || ! $user->activo) {
                return Password::INVALID_USER;
            }

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

    private function esCuentaDePrueba(string $email): bool
    {
        return strtolower(substr(strrchr($email, '@'), 1)) === 'test.com';
    }
}
