<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Google\Auth\AccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'idToken' => 'required|string',
            ], [
                'idToken.required' => 'El token de Google es obligatorio.',
                'idToken.string' => 'El token de Google debe ser texto.',
            ]);

            try {
                $payload = (new AccessToken())->verify($validated['idToken'], [
                    'audience' => config('services.google.client_id'),
                ]);
            } catch (Throwable $e) {
                Log::warning('Token de Google inválido', ['message' => $e->getMessage()]);
                $payload = false;
            }

            if (! $payload || empty($payload['email_verified']) || empty($payload['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo validar la cuenta de Google.',
                ], 401);
            }

            $user = User::where('google_id', $payload['sub'])
                ->orWhere('email', $payload['email'])
                ->first();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe una cuenta asociada a este correo de Google.',
                ], 401);
            }

            if (! $user->activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario se encuentra inactivo.',
                ], 403);
            }

            // Primer login con Google: vincula la cuenta
            if (! $user->google_id) {
                $user->google_id = $payload['sub'];
                $user->save();
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login exitoso.',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => new UserResource($user->load('role')),
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Error inesperado durante el login con Google', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error inesperado.',
            ], 500);
        }
    }
}
