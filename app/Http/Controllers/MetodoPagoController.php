<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetodoPagoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // es un select *, le restrinjo los campos de todo lo que quiero ver
        try{
            
            $metodosPago = MetodoPago::select(
                'id',
                'nombre',
                'descripcion',
                'estado'
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

    /**
     * Store a newly created resource in storage.
     */
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
                    'estado' => $metodoPago->estado == 1 ? 'Activo' : 'Inactivo',
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

    

    /**
     * Display the specified resource.
     */
    public function show($id)    
    {
        try {
            $metodoPago = MetodoPago::findOrFail($id);

            return response()->json([
                'message' => 'Método de pago obtenido correctamente.',
                'metodoPago' => [
                    'nombre' => $metodoPago->nombre,
                    'estado' => $metodoPago->estado == 1 ? 'Activo' : 'Inactivo',
                    
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Método de pago no encontrado.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el método de pago.'
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

    /**
     * Update the specified resource in storage.
     */
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
                ],
                'descripcion' => 'nullable|string|max:255',
                'estado' => [
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
                'estado.required' => 'El estado del método de pago es obligatorio.',
                'estado.in' => 'El estado debe ser 1 (Activo) o 0 (Inactivo).',
            ]);

            //Actualizar Método de Pago
            $metodoPago->nombre = $validated['nombre'];
            $metodoPago->descripcion = $validated['descripcion'];
            $metodoPago->estado = $validated['estado'];
            $metodoPago->save();

            return response()->json([
                'message' => 'Método de pago actualizado correctamente.',
                'metodoPago' => [
                    'nombre' => $metodoPago->nombre,
                    'estado' => $metodoPago->estado == 1 ? 'Activo' : 'Inactivo',
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
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MetodoPago $metodoPago)
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
                    'estado' => $metodoPago->estado == 1 ? 'Activo' : 'Inactivo',
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
