<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * Controlador para consultar indicadores clave, métricas agregadas y estado operativo del dashboard.
 */
class DashboardController extends Controller
{
    #[OA\Get(
        path: '/api/dashboard',
        summary: 'Obtener resumen y métricas agregadas del dashboard administrativo',
        tags: ['Dashboard'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'periodo',
                in: 'query',
                required: false,
                description: 'Rango temporal de consulta: hoy, 7d, 30d o historico (por defecto: historico)',
                schema: new OA\Schema(
                    type: 'string',
                    default: 'historico',
                    enum: ['hoy', '7d', '30d', 'historico']
                )
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Resumen del dashboard obtenido correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No autorizado.'),
            new OA\Response(response: 422, description: 'El periodo seleccionado no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el resumen del dashboard.'),
        ]
    )]
    public function resumen(Request $request, DashboardService $dashboardService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'periodo' => ['nullable', 'string', Rule::in(['hoy', '7d', '30d', 'historico'])],
            ], [
                'periodo.in' => 'El periodo seleccionado no es válido. Valores permitidos: hoy, 7d, 30d, historico.',
            ]);

            $periodo = $validated['periodo'] ?? 'historico';
            $resumen = $dashboardService->obtenerResumen($periodo);
            $resumen['admin_nombre'] = $request->user()?->name;

            return response()->json([
                'message' => 'Resumen del dashboard obtenido correctamente.',
                'dashboard' => $resumen,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el resumen del dashboard.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
