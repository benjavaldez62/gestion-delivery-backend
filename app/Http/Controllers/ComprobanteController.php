<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComprobanteResource;
use App\Models\Comprobante;
use Illuminate\Http\Request; 
use Illumunate\Validation\Rule;     
use OpenApi\Attributes as OA;

class ComprobanteController extends Controller
{
    #[OA\Get(
        path: '/api/comprobantes',
        summary: 'Listar todos los comprobantes',
        tags: ['Comprobantes'],
        responses: [
            new OA\Response(response: 200, description: 'Comprobantes obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay comprobantes disponibles.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function index()
    {
        try {
            $comprobantes = Comprobante::all();

            if ($comprobantes->isEmpty()) {
                return response()->json([
                    'message' => 'No hay comprobantes disponibles.'
                ], 404);
            }

            return response()->json([
                'message' => 'Comprobantes obtenidos correctamente.',
                'comprobantes' => ComprobanteResource::collection($comprobantes)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener los comprobantes.',
            ], 500);
        }
    }

    //Crea un comprobante nuevo

    #[OA\Post(
        path: '/api/comprobantes',
        summary: 'Crear un nuevo comprobante',
        tags: ['Comprobantes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pago_id', 'tipo_comprobante', 'numero_comprobante', 'titular'],
                properties: [
                    new OA\Property(property: 'pago_id', type: 'integer', example: 1),
                    new OA\Property(property: 'tipo_comprobante', type: 'string', maxLength: 50, example: 'Factura A'),
                    new OA\Property(property: 'numero_comprobante', type: 'string', maxLength: 100, example: '0001-00000123'),
                    new OA\Property(property: 'titular', type: 'string', maxLength: 150, example: 'Juan Pérez'),
                    new OA\Property(property: 'url_pdf', type: 'string', maxLength: 255, nullable: true, example: 'https://ejemplo.com/comprobante.pdf')
                ]
            )
        ),
            responses: [
                new OA\Response(response: 201, description: 'Comprobante creado correctamente.'),
                new OA\Response(response: 422, description: 'Datos inválidos o error de validación.'),
                new OA\Response(response: 500, description: 'Error interno al crear el comprobante.')
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'pago_id' => 'required|integer|exists:pagos,id',
                'tipo_comprobante' => 'required|string|max:50',
                'numero_comprobante' => 'required|string|max:100|unique:comprobantes,numero_comprobante',
                'titular' => 'required|string|max:150',
                'url_pdf' => 'nullable|string|max:255',
            ], [
                'pago_id.required' => 'El pago es obligatorio.',
                'pago_id.exists' => 'El pago seleccionado no existe.',
                
                'tipo_comprobante.required' => 'El tipo de comprobante es obligatorio.',
                'tipo_comprobante.max' => 'El tipo de comprobante no debe exceder los 50 caracteres.',
                
                'numero_comprobante.required' => 'El número de comprobante es obligatorio.',
                'numero_comprobante.unique' => 'El número de comprobante ya se encuentra registrado.',
                'numero_comprobante.max' => 'El número de comprobante no debe exceder los 100 caracteres.',
                
                'titular.required' => 'El titular es obligatorio.',
                'titular.max' => 'El titular no debe exceder los 150 caracteres.',
                
                'url_pdf.max' => 'La URL del PDF no debe exceder los 255 caracteres.',
            ]);
            // Crear comprobante
            $comprobante = new Comprobante();
            $comprobante->pago_id = $validated['pago_id'];
            $comprobante->tipo_comprobante = $validated['tipo_comprobante'];
            $comprobante->numero_comprobante = $validated['numero_comprobante'];
            $comprobante->titular = $validated['titular'];
            $comprobante->url_pdf = $validated['url_pdf'] ?? null;
            $comprobante->save();

            return response()->json([
                'message' => 'Comprobante creado correctamente.',
                'comprobante' => new ComprobanteResource($comprobante)
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Captura errores de validación
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Captura errores generales
            return response()->json([
                'message' => 'Error interno al crear el comprobante.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/comprobantes/{id}',
        summary: 'Obtener un comprobante específico',
        tags: ['Comprobantes'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del comprobante',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Comprobante obtenido correctamente.'),
            new OA\Response(response: 404, description: 'Comprobante no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function show($id)
    {
        try {
            $comprobante = Comprobante::find($id);

            return response()->json([
                'message' => 'Comprobante obtenido correctamente.',
                'comprobante' => new ComprobanteResource($comprobante)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Comprobante no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el comprobante.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/comprobantes/{id}',
        summary: 'Eliminar un comprobante por ID',
        tags: ['Comprobantes'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del comprobante a eliminar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Comprobante eliminado correctamente.'),
            new OA\Response(response: 404, description: 'Comprobante no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el comprobante.')
        ]
    )]
    public function destroy($id)
    {
        try {

            // Verificar que el ID sea válido
            if (!is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del comprobante no es válido.'
                ], 422);
            }

            // Buscar el comprobante
            $comprobante = Comprobante::find($id);

            // Verificar si existe
            if (!$comprobante) {
                return response()->json([
                    'message' => 'Comprobante no encontrado.'
                ], 404);
            }

            // Eliminar comprobante
            $comprobante->delete();

            // Respuesta exitosa
            return response()->json([
                'message' => 'Comprobante eliminado correctamente.'
            ], 200);

        } catch (\Illuminate\Database\QueryException $e) {

            // Error relacionado con la base de datos
            return response()->json([
                'message' => 'No se puede eliminar el comprobante porque está siendo utilizado por otros registros.'
            ], 409);

        } catch (\Exception $e) {

            // Error general
            return response()->json([
                'message' => 'Error interno al eliminar el comprobante.'
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/comprobantes/{id}/restore',
        summary: 'Restaurar un comprobante eliminado',
        tags: ['Comprobantes'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del comprobante a restaurar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Comprobante restaurado correctamente.'),
            new OA\Response(response: 404, description: 'Comprobante no encontrado entre los eliminados.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar el comprobante.')
        ]
    )]
    public function restore($id)
        {
            try {
                // Verificar que el ID sea válido
                if (!is_numeric($id) || (int) $id <= 0) {
                    return response()->json([
                        'message' => 'El ID del comprobante no es válido.'
                    ], 422);
                }
                // Buscar el comprobante (incluyendo eliminados)
                $comprobante = Comprobante::withTrashed()->find($id);

                // Verificar si existe
                if (!$comprobante) {
                    return response()->json([
                        'message' => 'Comprobante no encontrado.'
                    ], 404);
                }

                // Verificar que realmente esté eliminado
                if (!$comprobante->trashed()) {
                    return response()->json([
                        'message' => 'El comprobante no está eliminado.'
                    ], 409);
                }

                // Restaurar el comprobante
                $comprobante->restore();

                // Respuesta exitosa
                return response()->json([
                    'message' => 'Comprobante restaurado correctamente.',
                    'comprobante' => new ComprobanteResource($comprobante)
                ], 200);

            } catch (\Exception $e) {

                // Error general
                return response()->json([
                    'message' => 'Error interno al restaurar el comprobante.'
                ], 500);
            }
        }
}