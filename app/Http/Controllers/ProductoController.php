<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $productos = Producto::select(
                'id',
                'nombre',
                'descripcion',
                'precio',
                'estado'
            )->get();

            if ($productos->isEmpty()) {
                return response()->json([
                    'message' => 'No hay productos disponibles para mostrar.'
                ], 404);
            }

            return response()->json([
                'message' => 'Productos obtenidos correctamente.',
                'productos' => $productos
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error al obtener los productos.'
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Producto $producto)
    {
        //
        try {

            $producto = Producto::findOrFail($producto->id);

            return response()->json([
                'message' => 'Producto obtenido correctamente.',
                'producto' => $producto
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el producto.'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Producto $producto)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Producto $producto)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Producto $producto)
    {
        //
    }
}
