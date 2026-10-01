<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ClienteController extends Controller
{
    #[OA\Get(
        path: '/api/clientes',
        summary: 'Listar todos los clientes',
        tags: ['Clientes'],
        responses: [
            new OA\Response(response: 200, description: 'Clientes obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay clientes disponibles.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function index()
    {
        try {
            $clientes = Cliente::all();

            if ($clientes->isEmpty()) {
                return response()->json([
                    'message' => 'No hay clientes disponibles.',
                ], 404);
            }

            return response()->json([
                'message' => 'Clientes obtenidos correctamente.',
                'clientes' => ClienteResource::collection($clientes),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener los clientes.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/clientes',
        summary: 'Crear un nuevo cliente',
        tags: ['Clientes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', minLength: 3, maxLength: 30, example: 'juanperez'),
                    new OA\Property(property: 'telefono', type: 'string', maxLength: 30, nullable: true, example: '+5491123456789'),
                    new OA\Property(property: 'direccion', type: 'string', maxLength: 255, nullable: true, example: 'Av. Siempreviva 742'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Cliente creado correctamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el cliente.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string|min:3|max:30|unique:clientes,username',
                'telefono' => 'nullable|string|max:30',
                'direccion' => 'nullable|string|max:255',
            ], [
                'username.required' => 'El nombre de usuario es obligatorio.',
                'username.string' => 'El nombre de usuario debe ser una cadena de texto.',
                'username.min' => 'El nombre de usuario debe tener al menos 3 caracteres.',
                'username.max' => 'El nombre de usuario no debe exceder los 30 caracteres.',
                'username.unique' => 'Ya existe un cliente con ese nombre de usuario.',
                'telefono.string' => 'El teléfono debe ser una cadena de texto.',
                'telefono.max' => 'El teléfono no debe exceder los 30 caracteres.',
                'direccion.string' => 'La dirección debe ser una cadena de texto.',
                'direccion.max' => 'La dirección no debe exceder los 255 caracteres.',
            ]);

            $cliente = Cliente::create($validated);

            return response()->json([
                'message' => 'Cliente creado correctamente.',
                'cliente' => new ClienteResource($cliente),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al crear el cliente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/clientes/{id}',
        summary: 'Obtener un cliente por ID',
        tags: ['Clientes'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del cliente',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cliente obtenido exitosamente.'),
            new OA\Response(response: 404, description: 'Cliente no encontrado.'),
            new OA\Response(response: 422, description: 'El ID del cliente no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el cliente.'),
        ]
    )]
    public function show($id)
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del cliente no es válido.',
                ], 422);
            }

            $cliente = Cliente::findOrFail($id);

            return response()->json([
                'cliente' => new ClienteResource($cliente),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el cliente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/clientes/{id}',
        summary: 'Actualizar un cliente por ID',
        tags: ['Clientes'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del cliente a actualizar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'username', type: 'string', minLength: 3, maxLength: 30, example: 'juanperez_editado'),
                    new OA\Property(property: 'telefono', type: 'string', maxLength: 30, nullable: true, example: '+5491198765432'),
                    new OA\Property(property: 'direccion', type: 'string', maxLength: 255, nullable: true, example: 'Calle Falsa 123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cliente actualizado correctamente.'),
            new OA\Response(response: 404, description: 'Cliente no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el cliente.'),
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del cliente no es válido.',
                ], 422);
            }

            $cliente = Cliente::findOrFail($id);

            $validated = $request->validate([
                'username' => [
                    'sometimes',
                    'required',
                    'string',
                    'min:3',
                    'max:30',
                    Rule::unique('clientes', 'username')->ignore($cliente->id),
                ],
                'telefono' => 'nullable|string|max:30',
                'direccion' => 'nullable|string|max:255',
            ], [
                'username.required' => 'El nombre de usuario es obligatorio.',
                'username.string' => 'El nombre de usuario debe ser una cadena de texto.',
                'username.min' => 'El nombre de usuario debe tener al menos 3 caracteres.',
                'username.max' => 'El nombre de usuario no debe exceder los 30 caracteres.',
                'username.unique' => 'Ya existe un cliente con ese nombre de usuario.',
                'telefono.string' => 'El teléfono debe ser una cadena de texto.',
                'telefono.max' => 'El teléfono no debe exceder los 30 caracteres.',
                'direccion.string' => 'La dirección debe ser una cadena de texto.',
                'direccion.max' => 'La dirección no debe exceder los 255 caracteres.',
            ]);

            $cliente->update($validated);

            return response()->json([
                'message' => 'Cliente actualizado correctamente.',
                'cliente' => new ClienteResource($cliente),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente no encontrado.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al actualizar el cliente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/clientes/{id}',
        summary: 'Eliminar un cliente por ID',
        tags: ['Clientes'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del cliente a eliminar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cliente eliminado correctamente.'),
            new OA\Response(response: 404, description: 'Cliente no encontrado.'),
            new OA\Response(response: 409, description: 'No se puede eliminar el cliente porque está siendo utilizado por otros registros.'),
            new OA\Response(response: 422, description: 'El ID del cliente no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el cliente.'),
        ]
    )]
    public function destroy($id)
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del cliente no es válido.',
                ], 422);
            }

            $cliente = Cliente::findOrFail($id);
            $cliente->delete();

            return response()->json([
                'message' => 'Cliente eliminado correctamente.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente no encontrado.',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Error - No se puede eliminar el cliente porque está siendo utilizado por otros registros.',
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al eliminar el cliente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/clientes/{id}/restore',
        summary: 'Restaurar un cliente eliminado',
        tags: ['Clientes'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del cliente a restaurar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cliente restaurado correctamente.'),
            new OA\Response(response: 404, description: 'Cliente no encontrado.'),
            new OA\Response(response: 409, description: 'El cliente no está eliminado.'),
            new OA\Response(response: 422, description: 'El ID no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar el cliente.'),
        ]
    )]
    public function restore($id)
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del cliente no es válido.',
                ], 422);
            }

            $cliente = Cliente::withTrashed()->find($id);

            if (! $cliente) {
                return response()->json([
                    'message' => 'Cliente no encontrado.',
                ], 404);
            }

            if (! $cliente->trashed()) {
                return response()->json([
                    'message' => 'El cliente no está eliminado.',
                ], 409);
            }

            $cliente->restore();

            return response()->json([
                'message' => 'Cliente restaurado correctamente.',
                'cliente' => new ClienteResource($cliente),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al restaurar el cliente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
