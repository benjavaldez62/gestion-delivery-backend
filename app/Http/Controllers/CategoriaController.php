<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {

            $categorias = Categoria::select(
                'id',
                'nombre',
                'descripcion',
                'estado'
            )->get();

            if ($categorias->isEmpty()) {
                return response()->json([
                    'message' => 'No hay categorías disponibles.'
                ], 404);
            }

            return response()->json([
                'message' => 'Categorías obtenidas correctamente.',
                'categorias' => $categorias
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al obtener las categorías.'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
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

            $categoria->refresh();

            return response()->json([
                'message' => 'Categoría creada correctamente',
                'categoria' => [
                    'nombre' => $categoria->nombre,
                    'estado' => $categoria->estado == 1 ? 'Activo' : 'Inactivo',
                    'descripcion' => $categoria->descripcion
                ]
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

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $categoria = Categoria::findOrFail($id);

            return response()->json([
                'nombre' => $categoria->nombre,
                'descripcion' => $categoria->descripcion,
                'estado' => $categoria->estado == 1 ? 'Activo' : 'Inactivo',

                //'estado' => $categoria->estado
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

    /**
     * Update the specified resource in storage.
     */
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

            // Respuesta
            return response()->json([
                'message' => 'Categoría actualizada correctamente.',
                'categoria' => [
                    'nombre' => $categoria->nombre,
                    'estado' => $categoria->estado == 1 ? 'Activo' : 'Inactivo',
                    'descripcion' => $categoria->descripcion
                ]
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

    /**
     * Remove the specified resource from storage.
     */
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
