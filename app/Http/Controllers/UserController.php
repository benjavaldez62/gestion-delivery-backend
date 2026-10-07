<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    /* ==============
    *   MOSTRAR TODO
    *  ============== */
    #[OA\Get(
        path: '/api/users',
        summary: "Listar todos los usuarios",
        tags: ['Usuarios'],
        responses: [
            new OA\Response(response: 200, description: "Usuarios obtenidos correctamente."),
            new OA\Response(response: 401, description: "No autenticado."),
            new OA\Response(response: 404, description: "No hay usuarios disponibles para mostrar."),
            new OA\Response(response: 500, description: "Error al obtener los usuarios.")
        ]
    )]
    public function index()
    {
        try {
            $users = User::with('role')->get();

            // Caso NO hay usuarios
            if ($users->isEmpty()) {
                return response()->json([
                    'message' => 'No hay usuarios disponibles para mostrar.'
                ], 404);
            }

            // Caso SÍ hay usuarios para mostrar
            return response()->json([
                'message' => 'Usuarios obtenidos correctamente.',
                'usuarios' => UserResource::collection($users)
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los usuarios.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* ===========================
    *   MOSTRAR UNO EN PARTICULAR
    *  =========================== */
    public function show(string $id)
    {
        //
    }

    /* ===================================
    *   FRONTEND - FORMULARIO PARA CREAR
    *  =================================== */
    public function create()
    {
        //
    }

    /* =============
    *   CREAR
    *  ============= */
    public function store(Request $request)
    {
        //
    }

    /* ===================================
    *   FRONTEND - FORMULARIO PARA EDITAR
    *  =================================== */
    public function edit(string $id)
    {
        //
    }

    /* =============
    *   ACTUALIZAR
    *  ============= */
    public function update(Request $request, string $id)
    {
        //
    }

    /* =============
    *   ELIMINAR
    *  ============= */
    public function destroy(string $id)
    {
        //
    }

    /* =============
    *   RESTAURAR
    *  ============= */
    public function restore(string $id)
    {
        //
    }
}
