<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdicionalResource;
use App\Models\Adicional;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AdicionalController extends Controller
{
    #[OA\Get(
        path: '/api/adicionales',
        summary: 'Listar todos los adicionales',
        tags: ['Adicionales'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Adicionales obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay adicionales disponibles.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function index()
    {
        try {
            $adicionales = Adicional::all();

            if ($adicionales->isEmpty()) {
                return response()->json([
                    'message' => 'No hay adicionales disponibles.',
                ], 404);
            }

            return response()->json([
                'message' => 'Adicionales obtenidos correctamente.',
                'adicionales' => AdicionalResource::collection($adicionales),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener los adicionales.',
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/adicionales-desactivados',
        summary: 'Listar todos los adicionales desactivados',
        tags: ['Adicionales'],
        responses: [
            new OA\Response(response: 200, description: 'Adicionales desactivados obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay adicionales desactivados.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function indexDesactivados()
    {
        try {
            $adicionalesDesactivados = Adicional::onlyTrashed()->get();

            // Caso para cuando NO hay nada inhabilitado
            if ($adicionalesDesactivados->isEmpty()) {
                return response()->json([
                    'message' => 'No hay adicionales desactivados.',
                ], 404);
            }

            // Caso para cuando HAY algo inhabilitado
            return response()->json([
                'message' => 'Adicionales desactivados obtenidos correctamente.',
                'adicionalesDesactivados' => AdicionalResource::collection($adicionalesDesactivados),
            ], 200);
            // Caso para cuando hay falla por servidor
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener los adicionales desactivados.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // crea un adicional nuevo
    #[OA\Post(
        path: '/api/adicionales',
        summary: 'Crear un nuevo adicional',
        tags: ['Adicionales'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre', 'precio'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 50, example: 'Salsa Extra'),
                    new OA\Property(property: 'descripcion', type: 'string', nullable: true, example: 'Porción extra de salsa cheddar'),
                    new OA\Property(property: 'precio', type: 'number', format: 'float', example: 500.00),
                    new OA\Property(property: 'activo', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Adicional creado correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos o error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el adicional.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            // Validación
            $validated = $request->validate([
                'nombre' => 'required|string|max:50',
                'descripcion' => 'nullable|string',
                'precio' => 'required|numeric|gt:0',
                'activo' => 'boolean',
            ], [
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.max' => 'El nombre no debe exceder los 50 caracteres.',

                'precio.required' => 'El precio es obligatorio.',
                'precio.numeric' => 'El precio debe ser un valor numérico.',
                'precio.gt' => 'El precio debe ser mayor a 0.',

                'activo.boolean' => 'El campo activo debe ser verdadero o falso.',
            ]);

            // Crear adicional
            $adicional = new Adicional;
            $adicional->nombre = $validated['nombre'];
            $adicional->descripcion = $validated['descripcion'] ?? null;
            $adicional->precio = $validated['precio'];
            $adicional->activo = $validated['activo'] ?? true;
            $adicional->save();

            return response()->json([
                'message' => 'Adicional creado correctamente',
                'adicional' => new AdicionalResource($adicional),
            ], 201);
        } catch (ValidationException $e) {
            // Captura errores de validación
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Captura cualquier otro error
            return response()->json([
                'message' => 'Error interno al crear el adicional',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/adicionales/{id}',
        summary: 'Obtener un adicional por ID',
        tags: ['Adicionales'],
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del adicional',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Adicional obtenido correctamente.'),
            new OA\Response(response: 404, description: 'Adicional no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el adicional.'),
        ]
    )]
    public function show($id)
    {
        try {
            $adicional = Adicional::findOrFail($id);

            return response()->json([
                'adicional' => new AdicionalResource($adicional),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Adicional no encontrado',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el adicional',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/adicionales/{id}',
        summary: 'Actualizar un adicional por ID',
        tags: ['Adicionales'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del adicional a actualizar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre', 'precio'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 50, example: 'Salsa Extra'),
                    new OA\Property(property: 'descripcion', type: 'string', nullable: true, example: 'Porción extra de salsa cheddar'),
                    new OA\Property(property: 'precio', type: 'number', format: 'float', example: 600.00),
                    new OA\Property(property: 'activo', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Adicional actualizado correctamente.'),
            new OA\Response(response: 404, description: 'Adicional no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el adicional.'),
        ]
    )]
    public function update(Request $request, $id)
    {
        try {

            // Buscar el adicional
            $adicional = Adicional::findOrFail($id);

            // Validar datos
            $validated = $request->validate([
                'nombre' => 'required|string|max:50',
                'descripcion' => 'nullable|string',
                'precio' => 'required|numeric|gt:0',
                'activo' => 'boolean',
            ], [
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.max' => 'El nombre no debe exceder los 50 caracteres.',

                'precio.required' => 'El precio es obligatorio.',
                'precio.numeric' => 'El precio debe ser un valor numérico.',
                'precio.gt' => 'El precio debe ser mayor a 0.',

                'activo.boolean' => 'El campo activo debe ser verdadero o falso.',
            ]);

            // Actualizar adicional
            $adicional->nombre = $validated['nombre'];
            $adicional->descripcion = $validated['descripcion'] ?? null;
            $adicional->precio = $validated['precio'];
            if (isset($validated['activo'])) {
                $adicional->activo = $validated['activo'];
            }

            $adicional->save();

            return response()->json([
                'message' => 'Adicional actualizado correctamente.',
                'adicional' => new AdicionalResource($adicional),
            ], 200);
        } catch (ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Adicional no encontrado.',
            ], 404);
        } catch (ValidationException $e) {

            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al actualizar el adicional.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/adicionales/{id}',
        summary: 'Eliminar un adicional por ID',
        tags: ['Adicionales'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del adicional a eliminar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Adicional eliminado correctamente.'),
            new OA\Response(response: 404, description: 'Adicional no encontrado.'),
            new OA\Response(response: 409, description: 'Error de integridad referencial.'),
            new OA\Response(response: 422, description: 'ID no válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el adicional.'),
        ]
    )]
    public function destroy($id)
    {
        try {
            // Verificar que el ID sea válido
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del adicional no es válido.',
                ], 422);
            }

            // Buscar el adicional
            $adicional = Adicional::find($id);

            // Verificar si existe
            if (! $adicional) {
                return response()->json([
                    'message' => 'Adicional no encontrado.',
                ], 404);
            }

            // Eliminar adicional
            $adicional->delete();

            // Respuesta exitosa
            return response()->json([
                'message' => 'Adicional eliminado correctamente.',
            ], 200);
        } catch (QueryException $e) {

            // Error relacionado con la base de datos
            return response()->json([
                'message' => 'No se puede eliminar el adicional porque está siendo utilizado por otros registros.',
            ], 409);
        } catch (\Exception $e) {

            // Error general
            return response()->json([
                'message' => 'Error interno al eliminar el adicional.',
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/adicionales/{id}/restore',
        summary: 'Restaurar un adicional eliminado',
        tags: ['Adicionales'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del adicional a restaurar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Adicional restaurado correctamente.'),
            new OA\Response(response: 404, description: 'Adicional no encontrado.'),
            new OA\Response(response: 409, description: 'El adicional no está eliminado.'),
            new OA\Response(response: 422, description: 'El ID no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar el adicional.'),
        ]
    )]
    public function restore($id)
    {
        try {

            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del adicional no es válido.',
                ], 422);
            }

            $adicional = Adicional::withTrashed()->find($id);

            if (! $adicional) {
                return response()->json([
                    'message' => 'Adicional no encontrado.',
                ], 404);
            }

            // Verificar que realmente esté eliminado
            if (! $adicional->trashed()) {
                return response()->json([
                    'message' => 'El adicional no está eliminado.',
                ], 409);
            }

            $adicional->restore();

            return response()->json([
                'message' => 'Adicional restaurado correctamente.',
                'adicional' => new AdicionalResource($adicional),
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al restaurar el adicional.',
            ], 500);
        }
    }
}
