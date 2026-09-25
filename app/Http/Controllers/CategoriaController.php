<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class CategoriaController extends Controller
{
    #[OA\Get(
        path: '/api/categorias',
        summary: 'Listar todas las categorías',
        tags: ['Categorías'],
        responses: [
            new OA\Response(response: 200, description: 'Categorías obtenidas correctamente.'),
            new OA\Response(response: 404, description: 'No hay categorías disponibles.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function index()
    {
        try {
            $categorias = Categoria::all();

            if ($categorias->isEmpty()) {
                return response()->json([
                    'message' => 'No hay categorías disponibles.'
                ], 404);
            }

            return response()->json([
                'message' => 'Categorías obtenidas correctamente.',
                'categorias' => CategoriaResource::collection($categorias)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener las categorías.'
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/categorias',
        summary: 'Crear una nueva categoría',
        tags: ['Categorías'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 50, example: 'Hamburguesas'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'Categoría de hamburguesas caseras')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Categoría creada correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos o error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear la categoría.')
        ]
    )]
    public function store(Request $request)
    {
        try {
            // Validación
            $validated = $request->validate([
                'nombre' => 'required|string|max:50|unique:categorias,nombre',
                'descripcion' => 'nullable|string|max:255',
            ], [
                'nombre.required' => 'El nombre de la categoría es obligatorio.',
                'nombre.string' => 'El nombre de la categoría debe ser una cadena de texto.',
                'nombre.max' => 'El nombre de la categoría no debe exceder los 50 caracteres.',
                'nombre.unique' => 'Ya existe una categoría con ese nombre.',
                'descripcion.string' => 'La descripción de la categoría debe ser una cadena de texto.',
                'descripcion.max' => 'La descripción de la categoría no debe exceder los 255 caracteres.',
            ]);
            // Crear categoría
            $categoria = new Categoria();
            $categoria->nombre = $validated['nombre'];
            $categoria->descripcion = $validated['descripcion'];
            $categoria->save();

            return response()->json([
                'message' => 'Categoría creada correctamente',
                'categoria' => new CategoriaResource($categoria)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Captura errores de validación
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Captura cualquier otro error
            return response()->json([
                'message' => 'Error interno al crear la categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/categorias/{id}',
        summary: 'Obtener una categoría por ID',
        tags: ['Categorías'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la categoría',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Categoría obtenida correctamente.'),
            new OA\Response(response: 404, description: 'Categoría no encontrada.'),
            new OA\Response(response: 500, description: 'Error interno al obtener la categoría.')
        ]
    )]
    public function show($id)
    {
        try {
            $categoria = Categoria::findOrFail($id);

            return response()->json([
                'categoria' => new CategoriaResource($categoria)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Categoría no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener la categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/categorias/{id}',
        summary: 'Actualizar una categoría por ID',
        tags: ['Categorías'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la categoría a actualizar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre', 'estado'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', minLength: 2, maxLength: 50, example: 'Bebidas'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'Gaseosas y aguas'),
                    new OA\Property(property: 'estado', type: 'integer', enum: [0, 1], example: 1, description: '1: Activo, 0: Inactivo')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Categoría actualizada correctamente.'),
            new OA\Response(response: 404, description: 'Categoría no encontrada.'),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar la categoría.')
        ]
    )]
    public function update(Request $request, $id)
    {
        try {

            // Buscar la categoría
            $categoria = Categoria::findOrFail($id);

            // Validar datos
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'min:2',
                    'max:50',
                    Rule::unique('categorias', 'nombre')
                        ->ignore($categoria->id),
                ],
                'descripcion' => 'nullable|string|max:255',
                'estado' => [
                    'required',
                    Rule::in([0, 1]),
                ],
            ], [
                'nombre.required' => 'El nombre de la categoría es obligatorio.',
                'nombre.string' => 'El nombre de la categoría debe ser una cadena de texto.',
                'nombre.min' => 'El nombre de la categoría debe tener al menos 2 caracteres.',
                'nombre.max' => 'El nombre de la categoría no debe exceder los 50 caracteres.',
                'nombre.unique' => 'Ya existe una categoría con ese nombre.',
                'descripcion.string' => 'La descripción de la categoría debe ser una cadena de texto.',
                'descripcion.max' => 'La descripción de la categoría no debe exceder los 255 caracteres.',
                'estado.required' => 'El estado de la categoría es obligatorio.',
                'estado.in' => 'El estado debe ser 1 (Activo) o 0 (Inactivo).',
            ]);

            // Actualizar categoría
            $categoria->nombre = $validated['nombre'];
            $categoria->descripcion = $validated['descripcion'] ?? null;
            $categoria->estado = $validated['estado'];

            $categoria->save();

            return response()->json([
                'message' => 'Categoría actualizada correctamente.',
                'categoria' => new CategoriaResource($categoria)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Categoría no encontrada.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al actualizar la categoría.'
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/categorias/{id}',
        summary: 'Eliminar una categoría por ID',
        tags: ['Categorías'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la categoría a eliminar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Categoría eliminada correctamente.'),
            new OA\Response(response: 404, description: 'Categoría no encontrada.'),
            new OA\Response(response: 409, description: 'No se puede eliminar la categoría porque está siendo utilizada por otros registros.'),
            new OA\Response(response: 422, description: 'ID no válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar la categoría.')
        ]
    )]
    public function destroy($id)
    {
        try {

            // Verificar que el ID sea válido
            if (!is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID de la categoría no es válido.'
                ], 422);
            }

            // Buscar la categoría
            $categoria = Categoria::find($id);

            // Verificar si existe
            if (!$categoria) {
                return response()->json([
                    'message' => 'Categoría no encontrada.'
                ], 404);
            }

            // Eliminar categoría
            $categoria->delete();

            // Respuesta exitosa
            return response()->json([
                'message' => 'Categoría eliminada correctamente.'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {

            // Error relacionado con la base de datos
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque está siendo utilizada por otros registros.'
            ], 409);
        } catch (\Exception $e) {

            // Error general
            return response()->json([
                'message' => 'Error interno al eliminar la categoría.'
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/categorias/{id}/restore',
        summary: 'Restaurar una categoría eliminada',
        tags: ['Categorías'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la categoría a restaurar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Categoría restaurada correctamente.'),
            new OA\Response(response: 404, description: 'Categoría no encontrada.'),
            new OA\Response(response: 409, description: 'La categoría no está eliminada.'),
            new OA\Response(response: 422, description: 'El ID no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar la categoría.')
        ]
    )]
    public function restore($id)
    {
        try {

            if (!is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID de la categoría no es válido.'
                ], 422);
            }

            $categoria = Categoria::withTrashed()->find($id);

            if (!$categoria) {
                return response()->json([
                    'message' => 'Categoría no encontrada.'
                ], 404);
            }

            // Verificar que realmente esté eliminada
            if (!$categoria->trashed()) {
                return response()->json([
                    'message' => 'La categoría no está eliminada.'
                ], 409);
            }

            $categoria->restore();

            return response()->json([
                'message' => 'Categoría restaurada correctamente.',
                'categoria' => [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'descripcion' => $categoria->descripcion,
                    'estado' => $categoria->estado
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al restaurar la categoría.'
            ], 500);
        }
    }
}
