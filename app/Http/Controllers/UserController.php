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
use Symfony\Contracts\Service\Attribute\Required;

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
        } catch (\Exception $e) {
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
            $validated = $request->validate(
                [
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
                ]
            );

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
                'user' => $user
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
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
    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'Actualizar un usuario por ID',
        tags: ['Usuarios'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del usuario a actualizar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'role_id', 'activo', 'telefono', 'direccion'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 150, minLength: 8, example: 'Marta Duglas'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'martaduglas@ejemplo.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: '12345678'),
                    new OA\Property(property: 'role_id', type: 'integer', example: 2),
                    new OA\Property(property: 'telefono', type: 'string', minLength: 7, maxLength: 10, example: '+5493455124468'),
                    new OA\Property(property: 'direccion', type: 'string', maxLength: 255, minLength: 5, example: 'SM de Oro 79'),
                    new OA\Property(property: 'activo', type: 'boolean', example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuario actualizado correctamente.'),
            //new OA\Response(response: 401, description: 'No está autenticado para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
            new OA\Response(response: 500, description: 'Error al intentar actualizar un usuario.'),
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            // Buscamos el usuario a actualizar
            $user = User::findOrFail($id);

            // validamos 
            $validated = $request->validate(
                [
                    'name' => [
                        'sometimes',
                        'required',
                        'string',
                        'min:8',
                        'max:150'
                    ],
                    'email' => [
                        'sometimes',
                        'required',
                        'string',
                        'email',
                        'max:255',
                        Rule::unique('users', 'email')->ignore($user->id)
                    ],
                    'password' => [
                        'sometimes',
                        'required',
                        'string',
                        'min:8'
                    ],
                    'role_id' => [
                        'sometimes',
                        'required',
                        'integer',
                        'exists:roles,id'
                    ],
                    'telefono' => [
                        'sometimes',
                        'required',
                        'string',
                        'min:10',
                        'max:15'
                    ],
                    'direccion' => [
                        'sometimes',
                        'required',
                        'string',
                        'min:5',
                        'max:255'
                    ],
                    'activo' => [
                        'sometimes',
                        'required',
                        'boolean'
                    ],
                ],
                //mensajes de validación
                [
                    'name.required' => 'Es obligatiorio el campo name.',
                    'name.string' => 'El nombre debe ser una cadena de texto.',
                    'name.min' => 'El nombre completo debe contener al menos 8 caracteres',
                    'name.max' => 'El nombre completo no debe superar los 150 caracteres.',
                    'email.required' => 'El campo de email es obligatorio.',
                    'email.string' => 'El correo electrónico debe ser una cadena de caracteres.',
                    'email.email' => 'El correo electrónico debe ser válido.',
                    'email.max' => 'El correo electrónico no debe superar los 255 caracteres.',
                    'email.unique' => 'Ya existe un usuario con ese mismo correo.',
                    'password.required' => 'El campo de contraseña es obligatorio.',
                    'password.string' => 'La contraseña debe ser una cadena de caracteres.',
                    'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                    'role_id.required' => 'Es obligatorio asignar al usuario un ROL.',
                    'role_id.integer' => 'El ID del rol del usuario debe ser un número entero.',
                    'role_id.exists' => 'No existe ningún rol con ese ID.',
                    'telefono.required' => 'Es obligatorio el campo telefono.',
                    'telefono.string' => 'El telefono debe ser una cadena de texto.',
                    'telefono.min' => 'El teléfono debe tener al menos 10 dígitos.',
                    'telefono.max' => 'El teléfono no debe superar los 15 dígitos.',
                    'direccion.required' => 'El campo de dirección es obligatorio.',
                    'direccion.string' => 'La dirección debe ser una caden de caracteres.',
                    'direccion.min' => 'La dirección debe tener al menos 5 caracteres.',
                    'direccion.max' => 'La dirección no debe superar los 255 caracteres.',
                    'activo.required' => 'El campo activo es obligatorio.',
                    'activo.boolean' => 'El campo activo debe ser booleano.'
                ]
            );

            // Actualizamos el usuario
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->password = $validated['password'];
            $user->role_id = $validated['role_id'];
            $user->telefono = $validated['telefono'];
            $user->direccion = $validated['direccion'];
            $user->activo = $validated['activo'];

            $user->save();

            // Retornamos respuesta exitosa
            return response()->json([
                'message' => 'Usuario actualizado correctamente.',
                'user' => new UserResource($user)
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
                'error' => $e->getMessage()
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al intentar actualizar el usuario',
                'error' => $e->getMessage()
            ]);
        }
    }

    /* =============
    *   ELIMINAR
    *  ============= */
    #[OA\Delete(
        path: '/api/users/{id}',
        summary: 'Elimina un usuario por ID',
        tags: ['Usuarios'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del usuario a eliminar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario eliminado correctamente.'),
            //new OA\Response(response: 401, description: 'No autenticado - No tiene permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 409, description: 'No se puede eliminar el usuario porque está siendo utilizado en otros registros.'),
            new OA\Response(response: 422, description: 'El ID del usuario no es válido.'),
            new OA\Response(response: 500, description: 'Error al intentar eliminar al usuario.'),
        ]
    )]
    public function destroy($id)
    {
        try {
            // Verificar que el ID sea válido
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del usuario no es válido.',
                ], 422);
            }

            // Buscamos el usuario a eliminar
            $user = User::findOrFail($id);

            // Verificamos si el usuario existe antes de eliminarlo
            if (! $user) {
                return response()->json([
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }

            // Eliminamos el usuario -desactivación lógica-
            $user->delete();

            // Mostramos respuesta exitosa
            return response()->json([
                'message' => 'Usuario eliminado correctamente.'
            ], 200);
            
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'No se puede eliminar el usuario porque está siendo utilizado en otros registros.',
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al intentar eliminar al usuario.',
                'error' => $e->getMessage()
            ]);
        }
    }

    /* =============
    *   RESTAURAR
    *  ============= */
    public function restore(string $id)
    {
        //
    }
}
