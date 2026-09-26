<?php

namespace App\Http\Controllers;

use App\Models\EstadoPagos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class EstadoPagosController extends Controller
{
    #[OA\Get(
        path: '/api/estado-pagos',
        summary: 'Listar todos los estados de pago',
        tags: ['Estados de Pago'],
        responses: [
            new OA\Response(response: 200, description: 'Estados de pagos obtenidos exitosamente.'),
            new OA\Response(response: 404, description: 'No se encontraron estados de pagos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function index()
    {
        try{
            $estadoPagos = EstadoPagos::select(
                'id',
                'nombre',
                'descripcion',
            )->get();
            
            if ($estadoPagos->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron estados de pagos'
                    ], 404);
            }

            return response()->json([
                'message' => 'Estados de pagos obtenidos exitosamente',
                'estadoPagos' => $estadoPagos
            ], 200);
        }catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los estados de pagos',
            ], 500);
        }
    }


    #[OA\Post(
        path: '/api/estado-pagos',
        summary: 'Crear un nuevo estado de pago',
        tags: ['Estados de Pago'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 50, example: 'Aprobado'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'El pago fue procesado correctamente')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Estado de pago creado exitosamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el estado de pago.')
        ]
    )]
    public function store(Request $request)
    {
        try {
            // validacion
            $validated = $request->validate([
                'nombre' => 'required|string|max:255|unique:estado_pagos,nombre',
                'descripcion' => 'nullable|string|max:255',
            ],
            [
                'nombre.required' => 'El nombre del Estado de pago es obligatorio',
                'nombre.string' => 'El nombre del Estado de pago debe ser una cadena de texto',
                'nombre.max' => 'El nombre del Estado de pago no debe exceder los 255 caracteres',
                'nombre.unique' => 'El nombre del Estado de pago ya existe',
                'descripcion.string' => 'La descripción del Estado de pago debe ser una cadena de texto',
                'descripcion.max' => 'La descripción del Estado de pago no debe exceder los 255 caracteres',
            ]);
        
        //Creación del Estado
        $estadoPago = new EstadoPagos();
        $estadoPago->nombre = $validated['nombre'];
        $estadoPago->descripcion = $validated['descripcion'];
        $estadoPago->save();

        $estadoPago->refresh();

        return response()->json([
            'message' => 'Estado de pago creado exitosamente',
            'EstadoPago' => [
                'id' => $estadoPago->id,
                'nombre' => $estadoPago->nombre,
                'descripcion' => $estadoPago->descripcion,
            ]
        ], 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Error de validación',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al crear el Estado de pago',
            'error' => $e->getMessage()
        ], 500);
    }
    }

    #[OA\Get(
        path: '/api/estado-pagos/{id}',
        summary: 'Obtener un estado de pago por ID',
        tags: ['Estados de Pago'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del estado de pago',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado de pago obtenido exitosamente.'),
            new OA\Response(response: 404, description: 'Estado de pago no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el estado de pago.')
        ]
    )]
    public function show($id)
    {
        try{
            $estadoPago = EstadoPagos::findOrFail($id);

            return response()->json([
                    'id' => $estadoPago->id,
                    'nombre' => $estadoPago->nombre,
                    'descripcion' => $estadoPago->descripcion
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Estado de pago no encontrado',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el Estado de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EstadoPagos $estadoPagos)
    {
        //
    }

    #[OA\Put(
        path: '/api/estado-pagos/{id}',
        summary: 'Actualizar un estado de pago por ID',
        tags: ['Estados de Pago'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del estado de pago a actualizar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', minLength: 4, maxLength: 50, example: 'Rechazado'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'El pago fue rechazado')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Estado de pago actualizado exitosamente.'),
            new OA\Response(response: 404, description: 'Estado de pago no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el estado de pago.')
        ]
    )]
    public function update(Request $request, $id)
    {
        try{
            $estadoPago = EstadoPagos::findOrFail($id);

            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'min:4',
                    'max:50',
                    'unique:estado_pagos,nombre,' . $estadoPago->id,
                ],
                'descripcion' => 'nullable|string|max:255',
                'estado' => [

                ]
            ],
            [
                'nombre.required' => 'El nombre del Estado de pago es obligatorio',
                'nombre.string' => 'El nombre del Estado de pago debe ser una cadena de texto',
                'nombre.max' => 'El nombre del Estado de pago no debe exceder los 255 caracteres',
                'nombre.unique' => 'El nombre del Estado de pago ya existe',
                'descripcion.string' => 'La descripción del Estado de pago debe ser una cadena de texto',
                'descripcion.max' => 'La descripción del Estado de pago no debe exceder los 255 caracteres',
            ]);

            $estadoPago->nombre = $validated['nombre'];
            $estadoPago->descripcion = $validated['descripcion'];
            $estadoPago->save();

            return response()->json([
                'message' => 'Estado de pago actualizado exitosamente',
                'estadoPago' => [
                    'id' => $estadoPago->id,
                    'nombre' => $estadoPago->nombre,
                    'descripcion' => $estadoPago->descripcion,
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
           
            return response()->json([
                'message' => 'Error de validación: estado no encontrado.',
            ], 404);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            
            return response()->json([
                'message' => 'Estado de pago no encontrado',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el Estado de pago',
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/estado-pagos/{id}',
        summary: 'Eliminar un estado de pago por ID',
        tags: ['Estados de Pago'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del estado de pago a eliminar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado de pago eliminado exitosamente.'),
            new OA\Response(response: 404, description: 'Estado de pago no encontrado.'),
            new OA\Response(response: 409, description: 'No se puede eliminar el estado de pago porque está en uso.'),
            new OA\Response(response: 422, description: 'ID no válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el estado de pago.')
        ]
    )]
    public function destroy($id)
    {
        try {
            //verificamos que el id sea válido 
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'message' => 'El ID proporcionado no es válido.'
                ], 422);
            }

            //Buscamos el estado de pago
            $estadoPago = EstadoPagos::find($id);

            //verificamos si exige
            if (!$estadoPago) {
                return response()->json([
                    'message' => 'Estado de pago no encontrado.'
                ], 404);
            }

            //Eliminar estado de pago
            $estadoPago->delete();

            // Respuesta exitosa
            return response()->json([
                'message' => 'Estado de pago eliminado exitosamente.'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {

            return response()->json([
                'message' => 'Error al eliminar el Estado de pago.',
            ], 409);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al eliminar el Estado de pago.'
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/estado-pagos/{id}/restore',
        summary: 'Restaurar un estado de pago eliminado',
        tags: ['Estados de Pago'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del estado de pago a restaurar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado de pago restaurado correctamente.'),
            new OA\Response(response: 404, description: 'Estado de pago no encontrado.'),
            new OA\Response(response: 409, description: 'El estado de pago no está eliminado.'),
            new OA\Response(response: 422, description: 'El ID no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar el estado de pago.')
        ]
    )]
    public function restore($id)
    {
        try {

            if (!is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del estado de pago no es válido.'
                ], 422);
            }

            $estadoPago = EstadoPagos::withTrashed()->find($id);

            if (!$estadoPago) {
                return response()->json([
                    'message' => 'Estado de pago no encontrado.'
                ], 404);
            }

            // Verificar que realmente esté eliminada
            if (!$estadoPago->trashed()) {
                return response()->json([
                    'message' => 'El estado de pago no está eliminado.'
                ], 409);
            }

            $estadoPago->restore();

            return response()->json([
                'message' => 'Estado de pago restaurado correctamente.',
                'estadoPago' => [
                    'id' => $estadoPago->id,
                    'nombre' => $estadoPago->nombre,
                    'descripcion' => $estadoPago->descripcion,
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al restaurar el estado de pago.'
            ], 500);
        }
    }

}
