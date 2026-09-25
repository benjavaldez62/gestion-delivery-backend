<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProductoController extends Controller
{
    #[OA\Get(
        path: '/api/productos',
        summary: 'Listar todos los productos',
        tags: ['Productos'],
        responses: [
            new OA\Response(response: 200, description: 'Productos obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay productos disponibles para mostrar.'),
            new OA\Response(response: 500, description: 'Error al obtener los productos.')
        ]
    )]
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

    #[OA\Post(
        path: '/api/productos',
        summary: 'Crear un nuevo producto',
        tags: ['Productos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['categoria_id', 'nombre', 'precio'],
                properties: [
                    new OA\Property(property: 'categoria_id', type: 'integer', example: 1),
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 100, example: 'Hamburguesa Doble'),
                    new OA\Property(property: 'descripcion', type: 'string', nullable: true, example: 'Doble carne, queso cheddar y panceta'),
                    new OA\Property(property: 'precio', type: 'number', format: 'float', example: 4500.50),
                    new OA\Property(property: 'imagen', type: 'string', nullable: true, example: 'hamburguesa-doble.jpg'),
                    new OA\Property(property: 'activo', type: 'boolean', example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Producto creado correctamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el producto.')
        ]
    )]
    public function store(Request $request)
    {
        //
    }

    #[OA\Get(
        path: '/api/productos/{id}',
        summary: 'Obtener un producto por ID',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del producto',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto obtenido correctamente.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 500, description: 'Error al obtener el producto.')
        ]
    )]
    public function show($id)
    {
        //
        try {
            $producto = Producto::findOrFail($id);

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

    #[OA\Put(
        path: '/api/productos/{id}',
        summary: 'Actualizar un producto por ID',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del producto a actualizar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'categoria_id', type: 'integer', example: 1),
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 100, example: 'Hamburguesa Triple'),
                    new OA\Property(property: 'descripcion', type: 'string', nullable: true, example: 'Triple carne y queso'),
                    new OA\Property(property: 'precio', type: 'number', format: 'float', example: 5500.00),
                    new OA\Property(property: 'activo', type: 'boolean', example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado correctamente.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function update(Request $request, $id)
    {
        //
    }

    #[OA\Delete(
        path: '/api/productos/{id}',
        summary: 'Eliminar un producto por ID',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del producto a eliminar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto eliminado correctamente.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function destroy($id)
    {
        //
    }

    #[OA\Put(
        path: '/api/productos/{id}/restore',
        summary: 'Restaurar un producto eliminado',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del producto a restaurar',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto restaurado correctamente.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function restore($id)
    {
        //
    }
}
