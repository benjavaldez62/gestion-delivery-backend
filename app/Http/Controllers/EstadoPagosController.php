<?php

namespace App\Http\Controllers;

use App\Models\EstadoPagos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstadoPagosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $estadoPagos = EstadoPagoa::select(
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
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
        
        //Creación del Estad
        $estadoPago = new EstadoPagos();
        $estadoPago->nombre = $validated['nombre'];
        $estadoPago->descripcion = $validated['descripcion'];
        $estadoPago->save();

        $estadoPago->refesh();

        return response ()->jason([
            'message' => 'Estado de pago creado exitosamente',
            'EstadoPago' => [
                'id' => $estadoPago->id,
                'nombre' => $estadoPago->nombre,
                'descripcion' => $estadoPago->descripcion,
            ]
        ], 201);
    }catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Error de validación',
            'errors' => $e->errors(),
        ], 422);
    }catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al crear el Estado de pago',
            'error' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el Estado de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
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

    /**
     * Update the specified resource in storage.
     */
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
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el Estado de pago',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
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
