<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado. Inicia sesión para continuar.',
            ], 401);
        }

        if (! $user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo. No tiene permisos para operar en el sistema.',
            ], 403);
        }

        $userRoleName = $user->role?->nombre;

        // Bypass maestro: SuperAdmin siempre tiene acceso
        if ($userRoleName && strcasecmp($userRoleName, 'superadmin') === 0) {
            return $next($request);
        }

        // Si se especificaron roles permitidos, verificar coincidencia (case-insensitive)
        if (! empty($roles)) {
            $allowedRoles = array_map('strtolower', $roles);
            if (! $userRoleName || ! in_array(strtolower($userRoleName), $allowedRoles, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para acceder a este recurso.',
                ], 403);
            }
        }

        return $next($request);
    }
}
