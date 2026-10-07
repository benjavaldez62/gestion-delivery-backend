<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
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
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Obtener un usuario por ID',
        tags: ['Usuarios'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del usuario',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario obtenido correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 500, description: 'Error al obtener el usuario.'),
        ]
    )]
    public function show($id)
    {
        try {
            // Traemos el usuario solicitado por id
            $user = User::with('role')->findOrFail($id);

            // CASO 200: Mostramos el usuario obtenido
            return response()->json([
                'message' => 'Usuario obtenido correctamente.',
                'usuario' => new UserResource($user)
            ], 200);

        } catch (ModelNotFoundException $e) {
            // CASO 404: No encontrado
            return response()->json([
                'message' => 'Usuario no encontrado.'
            ], 404);
        } catch (\Exception $e) {
            // CASO 500: cualquier otro error
            return response()->json([
                'message' => 'Error al obtener el usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
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
    #[OA\Post(
        path: '/api/users',
        summary: 'Crear un nuevo usuario',
        tags: ['Usuarios'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'role_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Celeste Ferrari'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'celesteferrari@gmail.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: '12345678'),
                    new OA\Property(property: 'role_id', type: 'integer', example: 2, description: 'ID del rol del usuario: 1=Admin, 2=Cocinero, 3=Repartidor.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario creado correctamente.'),
            // 401 va acá? "NO AUTENTICADO"
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
            new OA\Response(response: 500, description: 'Error interno al crear el usuario.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            // Validar los datos recibidos
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'role_id' => ['required', 'integer', 'exists:roles,id']
            ],
            [
                'name.required' => 'El campo nombre es obligatorio.',
                'name.string' => 'El nombre debe ser una cadena de caracteres.',
                'name.max' => 'El nombre no debe superar los 150 caracteres.',
                'email.required' => 'El email es obligatorio.',
                'email.string' => 'El email debe ser una cadena de caracteres.',
                'email.email' => 'El email porporcionado no es válido.',
                'email.max' => 'El email no debe superar los 255 caracteres.',
                'email.unique' => 'El email proporcionado ya está registrado.',
                'password.required' => 'El campo de contraseña es obligatorio.',
                'password.string' => 'La contraseña debe ser una cadena de texto.',
                'password.min' => 'La contraseña debe contener al menos 8 caracteres.',
                'role_id.required' => 'Es obligatorio asignar un rol al usuario.',
                'role_id.integer' => 'El rol debe ser un número entero.',
                'role_id.exists' => 'El rol proporcionado no existe.',
            ]);

            // Creamos el usuario
            $user = new User;
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->password = $validated['password'];
            $user->activo = true; // por defecto, está activo al crearse
            $user->role_id = $validated['role_id'];
            $user->save();
            
            // CASO 201, mandamos mensaje
            return response()->json([
                'message' => 'Usuario creado correctamente.',
                'user' => UserResource::collection($user)
            ], 201); 
        } catch(ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'error' => $e->getMessage()
            ], 422);
        } catch(\Exception $e) {
            return response()->json([
                'message' => 'Error al intentar crear el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
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
