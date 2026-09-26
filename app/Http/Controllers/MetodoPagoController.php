<?php

namespace App\Http\Controllers;

use App\Http\Resources\PagoResource;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illumunate\Validation\Rule;
use OpenApi\Attributes as OA;

class PagoController extends Controller
{
    #[OA\Get(
        path: '/api/pagos',
        summary: 'Listar todos los pagos',
        tags: ['Pagos'],
        responses: [
            new OA\Response(response: 200, description: 'Pagos obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay pagos disponibles.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function index()
    {
        try{
            $pagos = Pago::all();

            if ($pagos->isEmpty()) {
                return response()->json([
                    'message' =>'No hay pagos disponibles.'
                ],404);
            }

            return response()->json([
                'message' => 'Pagos obtenidos correctamente.',
                'pagos' => PagoResource::collection($pagos)
            ],200);
        } catch(\Exception $e){
            return response ()->json([
                'message' => 'Error interno al obtener los pagos.',
            ],500);
        }
    }

   // crea un pago nuevo

    #[OA\Post(
        path: '/api/pagos',
        summary: 'Crear un nuevo pago',
        tags: ['Pagos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['monto', 'metodo_pago_id'],
                properties: [
                    new OA\Property(property: 'monto', type: 'number', format: 'float', example: 1500.50),
                    new OA\Property(property: 'metodo_pago_id', type: 'integer', example: 1),
                    new OA\Property(property: 'referencia', type: 'string', maxLength: 255, nullable: true, example: 'TRX-987654')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pago creado correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos o error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el pago.')
        ]
    )]
    public function store(Request $request)
    {
        try {
            // Validación
            $validated = $request->validate([
                'pedido_id' => 'required|integer|exists:pedidos,id',
                'metodo_pago_id' => 'required|integer|exists:metodos_pago,id',
                'monto' => 'required|numeric|gt:0',
                'fecha_pago' => 'required|date',
                'estado_pago' => 'required|string|max:50',
            ], [
                'pedido_id.required' => 'El pedido es obligatorio.',
                'pedido_id.exists' => 'El pedido seleccionado no existe.',
                
                'metodo_pago_id.required' => 'El método de pago es obligatorio.',
                'metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
                
                'monto.required' => 'El monto es obligatorio.',
                'monto.numeric' => 'El monto debe ser un valor numérico.',
                'monto.gt' => 'El monto debe ser mayor a 0.',
                
                'fecha_pago.required' => 'La fecha de pago es obligatoria.',
                
                'estado_pago.required' => 'El estado del pago es obligatorio.',
                'estado_pago.max' => 'El estado del pago no debe exceder los 50 caracteres.',
            ]);

            // Crear pago
            $pago = new Pago();
            $pago->pedido_id = $validated['pedido_id'];
            $pago->metodo_pago_id = $validated['metodo_pago_id'];
            $pago->monto = $validated['monto'];
            $pago->fecha_pago = $validated['fecha_pago'];
            $pago->estado_pago = $validated['estado_pago'];
            $pago->save();

            return response()->json([
                'message' => 'Pago creado correctamente',
                'pago' => new PagoResource($pago)
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
                'message' => 'Error interno al crear el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/pagos/{id}',
        summary: 'Obtener un pago por ID',
        tags: ['Pagos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pago',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pago obtenido correctamente.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el pago.')
        ]
    )]
    public function show($id)
    {
        try {
            $pago = Pago::findOrFail($id);

            return response()->json([
                'pago' => new PagoResource($pago)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pago no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/pagos/{id}',
        summary: 'Actualizar un pago por ID',
        tags: ['Pagos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pago a actualizar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['monto', 'metodo_pago_id', 'estado'],
                properties: [
                    new OA\Property(property: 'monto', type: 'number', format: 'float', example: 2000.00),
                    new OA\Property(property: 'metodo_pago_id', type: 'integer', example: 1),
                    new OA\Property(property: 'referencia', type: 'string', maxLength: 255, nullable: true, example: 'TRX-987654'),
                    new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'completado', 'rechazado'], example: 'completado')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pago actualizado correctamente.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el pago.')
        ]
    )]
    public function update(Request $request, $id)
    {
        try {

            // Buscar el pago
            $pago = Pago::findOrFail($id);

            // Validar datos
            $validated = $request->validate([
                'pedido_id' => 'required|integer|exists:pedidos,id',
                'metodo_pago_id' => 'required|integer|exists:metodos_pago,id',
                'monto' => 'required|numeric|gt:0',
                'fecha_pago' => 'required|date',
                'estado_pago' => 'required|string|max:50',
            ], [
                'pedido_id.required' => 'El pedido es obligatorio.',
                'pedido_id.exists' => 'El pedido seleccionado no existe.',
                
                'metodo_pago_id.required' => 'El método de pago es obligatorio.',
                'metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
                
                'monto.required' => 'El monto es obligatorio.',
                'monto.numeric' => 'El monto debe ser un valor numérico.',
                'monto.gt' => 'El monto debe ser mayor a 0.',
                
                'fecha_pago.required' => 'La fecha de pago es obligatoria.',
                
                'estado_pago.required' => 'El estado del pago es obligatorio.',
                'estado_pago.max' => 'El estado del pago no debe exceder los 50 caracteres.',
            ]);

            // Actualizar pago
            $pago->pedido_id = $validated['pedido_id'];
            $pago->metodo_pago_id = $validated['metodo_pago_id'];
            $pago->monto = $validated['monto'];
            $pago->fecha_pago = $validated['fecha_pago'];
            $pago->estado_pago = $validated['estado_pago'];

            $pago->save();

            return response()->json([
                'message' => 'Pago actualizado correctamente.',
                'pago' => new PagoResource($pago)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Pago no encontrado.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al actualizar el pago.'
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/pagos/{id}',
        summary: 'Eliminar un pago por ID',
        tags: ['Pagos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pago a eliminar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pago eliminado correctamente.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 409, description: 'Error de integridad referencial.'),
            new OA\Response(response: 422, description: 'ID no válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el pago.')
        ]
    )]
    public function destroy($id)
    {
        try {

            // Verificar que el ID sea válido
            if (!is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del pago no es válido.'
                ], 422);
            }

            // Buscar el pago
            $pago = Pago::find($id);

            // Verificar si existe
            if (!$pago) {
                return response()->json([
                    'message' => 'Pago no encontrado.'
                ], 404);
            }

            // Eliminar pago
            $pago->delete();

            // Respuesta exitosa
            return response()->json([
                'message' => 'Pago eliminado correctamente.'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {

            // Error relacionado con la base de datos
            return response()->json([
                'message' => 'No se puede eliminar el pago porque está siendo utilizado por otros registros.'
            ], 409);
        } catch (\Exception $e) {

            // Error general
            return response()->json([
                'message' => 'Error interno al eliminar el pago.'
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/pagos/{id}/restore',
        summary: 'Restaurar un pago eliminado',
        tags: ['Pagos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pago a restaurar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pago restaurado correctamente.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 409, description: 'El pago no está eliminado.'),
            new OA\Response(response: 422, description: 'El ID no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar el pago.')
        ]
    )]
    public function restore($id)
    {
        try {

            if (!is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del pago no es válido.'
                ], 422);
            }

            $pago = Pago::withTrashed()->find($id);

            if (!$pago) {
                return response()->json([
                    'message' => 'Pago no encontrado.'
                ], 404);
            }

            // Verificar que realmente esté eliminado
            if (!$pago->trashed()) {
                return response()->json([
                    'message' => 'El pago no está eliminado.'
                ], 409);
            }

            $pago->restore();

            return response()->json([
                'message' => 'Pago restaurado correctamente.',
                'pago' => new PagoResource($pago)
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al restaurar el pago.'
            ], 500);
        }
    }
}