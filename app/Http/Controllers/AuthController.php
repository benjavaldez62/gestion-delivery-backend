<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Throwable;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/login',
        summary: 'Iniciar sesión',
        description: 'Valida las credenciales de un usuario activo y devuelve un token de acceso de Sanctum.',
        tags: ['Autenticación'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'andreasanchez@test.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: '12345678'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login exitoso.'),
            new OA\Response(response: 401, description: 'Las credenciales proporcionadas son incorrectas.'),
            new OA\Response(response: 403, description: 'El usuario se encuentra inactivo.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email|max:255',
                'password' => 'required|string',
            ], [
                'email.required' => 'El campo correo electrónico es obligatorio.',
                'email.string' => 'El correo electrónico debe ser texto.',
                'email.email' => 'El correo electrónico debe ser una dirección válida.',
                'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
                'password.required' => 'El campo contraseña es obligatorio.',
                'password.string' => 'La contraseña debe ser texto.',
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (! $user || ! Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Las credenciales proporcionadas son incorrectas.',
                ], 401);
            }

            if (! $user->activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario se encuentra inactivo.',
                ], 403);
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
            Log::error('Error inesperado durante el login', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error inesperado.',
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Cerrar sesión',
        description: 'Elimina el token de Sanctum utilizado actualmente.',
        tags: ['Autenticación'],
        security: [
            ['sanctum' => []],
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado o token inválido.'),
        ]
    )]
    public function logout(Request $request)
    {
        // traemos del contexto al usuario
        $user = $request->user();

        // eliminamos el token actual del usuario
        $user->currentAccessToken()->delete();

        // mandamos respuesta de cierre de sesión exitoso
        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ], 200);

    }
}
