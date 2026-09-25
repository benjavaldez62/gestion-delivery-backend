<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class MetodoPagoController extends Controller
{
    #[OA\Get(
        path: '/api/metodos-pago',
        summary: 'Listar todos los métodos de pago',
        tags: ['Métodos de Pago'],
        responses: [
            new OA\Response(response: 200, description: 'Métodos de pago obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay métodos de pago disponibles.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function index()
    {
        // es un select *, le restrinjo los campos de todo lo que quiero ver
        try{
            
            $metodosPago = MetodoPago::select(
                'id',
                'nombre',
                'descripcion',
                'activo'
            )->get();

            if ($metodosPago->isEmpty()) {
                return response()->json([
                    'message' => 'No hay métodos de pago disponibles.'
                ], 404);
            }

            return response()->json([
                'message' => 'Métodos de pago obtenidos correctamente.',
                'metodosPago' => $metodosPago
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al obtener los métodos de pago.'
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
        path: '/api/metodos-pago',
        summary: 'Crear un nuevo método de pago',
        tags: ['Métodos de Pago'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 50, example: 'Efectivo'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'Pago en efectivo al recibir')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Método de pago creado correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos o error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el método de pago.')
        ]
    )]
    public function store(Request $request) 
    {
        try{
            // Validación
            $validated = $request->validate([
                'nombre' => 'required|string|max:50|unique:metodos_pago,nombre',
                'descripcion' => 'nullable|string|max:255',
            ], [
                'nombre.required' => 'El nombre del método de pago es obligatorio.',
                'nombre.string' => 'El nombre del método de pago debe ser una cadena de texto.',
                'nombre.max' => 'El nombre del método de pago no debe exceder los 50 caracteres.',
                'nombre.unique' => 'Ya existe un método de pago con ese nombre.',
                'descripcion.string' => 'La descripción del método de pago debe ser una cadena de texto.',
                'descripcion.max' => 'La descripción del método de pago no debe exceder los 255 caracteres.',
            ]);
            // Crear Método de Pago
            $metodoPago = new MetodoPago();
            $metodoPago->nombre = $validated['nombre'];
            $metodoPago->descripcion = $validated['descripcion'];
            $metodoPago->save();

            $metodoPago->refresh();

            return response()->json([
                'message' => 'Método de pago creado correctamente',
                'metodoPago' => [
                    'nombre' => $metodoPago->nombre,
                    'activo' => $metodoPago->activo == 1 ? 'Activo' : 'Inactivo',
                    'descripcion' => $metodoPago->descripcion
                ]
            ], 201);
        }catch (\Illuminate\Validation\ValidationException $e) {
            // Captura errores de validación
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Captura cualquier otro error
            return response()->json([
                'message' => 'Error interno al crear el Método de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    

    #[OA\Get(
        path: '/api/metodos-pago/{id}',
        summary: 'Obtener un método de pago por ID',
        tags: ['Métodos de Pago'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del método de pago',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Método de pago obtenido correctamente.'),
            new OA\Response(response: 404, description: 'Método de pago no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el método de pago.')
        ]
    )]
    public function show($id)    
    {
        try {
            $metodoPago = MetodoPago::findOrFail($id);

            return response()->json([
                'message' => 'Método de pago obtenido correctamente.',
                'metodoPago' => [
                    'nombre' => $metodoPago->nombre,
                    'activo' => $metodoPago->activo == 1 ? 'Activo' : 'Inactivo',
                    
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Método de pago no encontrado.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el método de pago.',
                'error' => $e->getMessage()  
            ], 500);
        }
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MetodoPago $metodoPago)
    {
        //ESTE SE BORRA?
    }

    #[OA\Put(
        path: '/api/metodos-pago/{id}',
        summary: 'Actualizar un método de pago por ID',
        tags: ['Métodos de Pago'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del método de pago a actualizar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre', 'activo'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', minLength: 5, maxLength: 50, example: 'Tarjeta de Débito'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'Pago con tarjeta'),
                    new OA\Property(property: 'activo', type: 'integer', enum: [0, 1], example: 1, description: '1: Activo, 0: Inactivo')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Método de pago actualizado correctamente.'),
            new OA\Response(response: 404, description: 'Método de pago no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el método de pago.')
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            $metodoPago = MetodoPago::findOrFail($id);

            // Validación
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'min:5', //ej: banco 
                    'max:50',
                    'unique:metodos_pago,nombre,' . $metodoPago->id,
                    Rule::unique('categorias', 'nombre')
                        ->ignore($metodoPago->id),
                    ],
                'descripcion' => 'nullable|string|max:255',
                'activo' => [
                    'required',
                    Rule::in([0, 1]),
                ],
            ], [
                'nombre.required' => 'El nombre del método de pago es obligatorio.',
                'nombre.string' => 'El nombre del método de pago debe ser una cadena de texto.',
                'nombre.min' => 'El nombre del método de pago debe tener al menos 5 caracteres.',
                'nombre.max' => 'El nombre del método de pago no debe exceder los 50 caracteres.',
                'nombre.unique' => 'Ya existe un método de pago con ese nombre.',
                'descripcion.string' => 'La descripción del método de pago debe ser una cadena de texto.',
                'descripcion.max' => 'La descripción del método de pago no debe exceder los 255 caracteres.',
                'activo.required' => 'El estado del método de pago es obligatorio.',
                'activo.in' => 'El estado debe ser 1 (Activo) o 0 (Inactivo).',
            ]);

            //Actualizar Método de Pago
            $metodoPago->nombre = $validated['nombre'];
            $metodoPago->descripcion = $validated['descripcion'];
            $metodoPago->activo = $validated['activo'];
            $metodoPago->save();

            return response()->json([
                'message' => 'Método de pago actualizado correctamente.',
                'metodoPago' => [
                    'nombre' => $metodoPago->nombre,
                    'activo' => $metodoPago->activo == 1 ? 'Activo' : 'Inactivo',
                    'descripcion' => $metodoPago->descripcion
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Método de pago no encontrado.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al actualizar el método de pago.',
            ], 500);
        }
    }
    #[OA\Delete(
        path: '/api/metodos-pago/{id}',
        summary: 'Eliminar un método de pago por ID',
        tags: ['Métodos de Pago'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del método de pago a eliminar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Método de pago eliminado correctamente.'),
            new OA\Response(response: 404, description: 'Método de pago no encontrado.'),
            new OA\Response(response: 409, description: 'Error de integridad referencial.'),
            new OA\Response(response: 422, description: 'ID no válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el método de pago.')
        ]
    )]
    public function destroy($id = null)
    {
        try{
            //Verificar que el ID sea válido
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'message' => 'El ID del método de pago debe ser un número positivo.'
                ], 422);
            }

            //Buscar el Método de pago por ID
            $metodoPago = MetodoPago::find($id);
            //Verifica si existe
            if (!$metodoPago) {
                return response()->json([
                    'message' => 'Método de pago no encontrado.'
                ], 404);
            }

            //Eliminar el Método de pago
            $metodoPago->delete();

            //Respuesta exitosa
            return response()->json([
                'message' => 'Método de pago eliminado correctamente.'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            // Error relacionado con la base de datos
            return response()->json([
                'message' => 'Error interno al eliminar el método de pago.'
            ], 409);
            
            //Error general
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al eliminar el método de pago.'
            ], 500);
        }
    }
    #[OA\Put(
        path: '/api/metodos-pago/{id}/restore',
        summary: 'Restaurar un método de pago eliminado',
        tags: ['Métodos de Pago'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del método de pago a restaurar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Método de pago restaurado correctamente.'),
            new OA\Response(response: 404, description: 'Método de pago no encontrado.'),
            new OA\Response(response: 409, description: 'El método de pago no está eliminado.'),
            new OA\Response(response: 422, description: 'El ID no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar el método de pago.')
        ]
    )]
    public function restore($id)
    {
        try {
            // Verificar que el ID sea válido
            if (!is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del método de pago no es válido.'
                ], 422);
            }

            // Buscar el método de pago eliminado
            $metodoPago = MetodoPago::withTrashed()->find($id);

            // Verificar si existe
            if (!$metodoPago) {
                return response()->json([
                    'message' => 'Método de pago no encontrado o no está eliminado.'
                ], 404);
            }
            // Verificar que realmente esté eliminada
            if (!$metodoPago->trashed()) {
                return response()->json([
                    'message' => 'El método de pago no está eliminado.'
                ], 409);
            }
            
            // Restaurar el método de pago
            $metodoPago->restore();

            // Respuesta exitosa
            return response()->json([
                'message' => 'Método de pago restaurado correctamente.',
                'metodoPago' => [
                    'id' => $metodoPago->id,
                    'nombre' => $metodoPago->nombre,
                    'activo' => $metodoPago->activo == 1 ? 'Activo' : 'Inactivo',
                    'descripcion' => $metodoPago->descripcion
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al restaurar el método de pago.'
            ], 500);
        }
    }
}
