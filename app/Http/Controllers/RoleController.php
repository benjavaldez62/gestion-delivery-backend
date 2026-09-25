<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    #[OA\Get(
        path: '/api/roles',
        summary: 'Listar todos los roles',
        tags: ['Roles'],
        responses: [
            new OA\Response(response: 200, description: 'Roles obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay roles disponibles.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function index()
    {
        //
        try {

            $roles = Role::select(
                'id',
                'nombre',
                'descripcion',
            )->get();

            if ($roles->isEmpty()) {
                return response()->json([
                    'message' => 'No hay roles disponibles.'
                ], 404);
            }

            return response()->json([
                'message' => 'Roles obtenidos correctamente.',
                'roles' => $roles
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al obtener los roles.'
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    #[OA\Post(
        path: '/api/roles',
        summary: 'Crear un nuevo rol',
        tags: ['Roles'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 30, example: 'Administrador'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'Rol con todos los permisos')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Rol creado correctamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el rol.')
        ]
    )]
    public function store(Request $request)
    {
        //
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:30|unique:roles,nombre',
                'descripcion' => 'nullable|string|max:255',
            ], [
                'nombre.required' => 'El nombre del rol es obligatorio.',
                'nombre.string' => 'El nombre del rol debe ser una cadena de texto.',
                'nombre.max' => 'El nombre del rol no debe exceder los 30 caracteres.',
                'nombre.unique' => 'Ya existe un rol con ese nombre.',
                'descripcion.string' => 'La descripción del rol debe ser una cadena de texto.',
                'descripcion.max' => 'La descripción del rol no debe exceder los 255 caracteres.',
            ]);

            // Crear Rol
            $role = new Role();
            $role->nombre = $validated['nombre'];
            $role->descripcion = $validated['descripcion'];
            $role->save();

            $role->refresh();

            return response()->json([
                'message' => 'Rol creado correctamente',
                'rol' => [
                    'nombre' => $role->nombre,
                    'descripcion' => $role->descripcion,
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            //Captura errores de validación    
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Captura cualquier otro error
            return response()->json([
                'message' => 'Error interno al crear el rol.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/roles/{id}',
        summary: 'Obtener un rol por ID',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del rol',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rol obtenido exitosamente.'),
            new OA\Response(response: 404, description: 'Rol no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el rol.')
        ]
    )]
    public function show($id)
    {
        //
        try {
            $roles = Role::findOrFail($id);

            return response()->json([
                'nombre' => $roles->nombre,
                'descripcion' => $roles->descripcion
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Rol no encontrado.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el rol',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        //
    }

    #[OA\Put(
        path: '/api/roles/{id}',
        summary: 'Actualizar un rol por ID',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del rol a actualizar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 30, example: 'Cajero'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'Rol para gestión de cobros')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Rol actualizado correctamente.'),
            new OA\Response(response: 404, description: 'Rol no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el rol.')
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            // Buscar el rol por su ID
            $role = Role::findOrFail($id);

            // Validar los datos
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'max:30',
                    'unique:roles,nombre,' . $role->id,
                    Rule::unique('roles', 'nombre')
                        ->ignore($role->id)
                ],
                'descripcion' => 'nullable|string|max:255',
            ], [
                'nombre.required' => 'El nombre del rol es obligatorio.',
                'nombre.string' => 'El nombre del rol debe ser una cadena de texto.',
                'nombre.max' => 'El nombre del rol no debe exceder los 30 caracteres.',
                'nombre.unique' => 'Ya existe un rol con ese nombre.',
                'descripcion.string' => 'La descripción del rol debe ser una cadena de texto.',
                'descripcion.max' => 'La descripción del rol no debe exceder los 255 caracteres.',
            ]);

            // Actualizar el rol
            $role->nombre = $validated['nombre'];
            $role->descripcion = $validated['descripcion'];

            $role->save();

            // Respuesta JSON
            return response()->json([
                'message' => 'Rol actualizado correctamente.',
                'rol' => [
                    'nombre' => $role->nombre,
                    'descripcion' => $role->descripcion
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Rol no encontrado.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Captura cualquier otro error
            return response()->json([
                'message' => 'Error interno al actualizar el rol.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/roles/{id}',
        summary: 'Eliminar un rol por ID',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del rol a eliminar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rol eliminado correctamente.'),
            new OA\Response(response: 404, description: 'Rol no encontrado.'),
            new OA\Response(response: 409, description: 'No se puede eliminar el rol porque está siendo utilizado por otros registros.'),
            new OA\Response(response: 422, description: 'El ID del rol no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el rol.')
        ]
    )]
    public function destroy($id)
    {
        try {
            // Verificar que el ID sea válido
            if (!is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del rol no es válido.'
                ], 422);
            }

            // Buscar el rol
            $role = Role::findOrFail($id);

            //Verificar si el rol existe antes de eliminarlo
            if (!$role) {
                return response()->json([
                    'message' => 'Rol no encontrado.'
                ], 404);
            }

            // Eliminar el rol
            $role->delete();

            // Respuesta exitosa
            return response()->json([
                'message' => 'Rol eliminado correctamente.'
            ], 200);


        } catch (\Illuminate\Database\QueryException $e) {
            // Error relacionado con la base de datos
            return response()->json([
                'message' => 'Error - No se puede eliminar el rol porque está siendo utilizado por otros registros.',
        ], 409);

        } catch (\Exception $e) {
            // Error general
            return response()->json([
                'message' => 'Error interno al eliminar el rol.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}