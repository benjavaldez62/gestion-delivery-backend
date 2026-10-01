<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            new OA\Response(response: 500, description: 'Error al obtener los productos.'),
        ]
    )]
    public function index()
    {
        try {
            $productos = Producto::with('categoria')->get();

            if ($productos->isEmpty()) {
                return response()->json([
                    'message' => 'No hay productos disponibles para mostrar.',
                ], 404);
            }

            return response()->json([
                'message' => 'Productos obtenidos correctamente.',
                'productos' => ProductoResource::collection($productos),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los productos.',
                'error' => $e->getMessage(),
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
                    new OA\Property(property: 'imagen', type: 'string', format: 'uri', nullable: true, example: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500'),
                    new OA\Property(property: 'activo', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Producto creado correctamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el producto.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            // Validación
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'min:5',
                    'max:100',
                    Rule::unique('productos', 'nombre')
                        ->whereNull('deleted_at')
                        ->where('activo', true),
                ],
                'descripcion' => 'nullable|string|max:255',
                'imagen' => 'nullable|url|max:255|unique:productos,imagen',
                'precio' => 'required|numeric|min:1|max:60000',
                'categoria_id' => 'required|integer|min:1|exists:categorias,id',
            ], [
                'nombre.required' => 'El nombre del producto es obligatorio.',
                'nombre.string' => 'El nombre del producto debe ser una cadena de texto.',
                'nombre.min' => 'El nombre del producto debe tener al menos 5 caracteres.',
                'nombre.max' => 'El nombre del producto no debe exceder los 100 caracteres.',
                'nombre.unique' => 'Ya existe un producto con ese nombre.',
                'descripcion.string' => 'La descripción debe ser una cadena de texto.',
                'descripcion.max' => 'La descripción no debe exceder los 255 caracteres.',
                'imagen.url' => 'La imagen debe ser una url válida.',
                'imagen.max' => 'La URL de la imagen no debe exceder los 255 caracteres.',
                'imagen.unique' => 'Ya existe un producto con esa imagen.',
                'precio.required' => 'Es obligatorio asignar un precio al producto.',
                'precio.numeric' => 'El precio del producto debe ser numérico.',
                'precio.min' => 'El precio del producto debe ser mayor a $1.',
                'precio.max' => 'El precio del producto no debe exceder los $60mil.',
                'categoria_id.required' => 'El producto debe tener una categoría.',
                'categoria_id.exists' => 'La categoría a la que se quiere relacionar el producto debe existir.',
                'categoria_id.integer' => 'La categoria debe ser un entero.',
                'categoria_id.min' => 'La categoría del producto no puede ser cero.',
            ]);

            // Crear producto
            $producto = new Producto;
            $producto->nombre = $validated['nombre'];
            $producto->nombre_activo = $validated['nombre'];
            $producto->descripcion = $validated['descripcion'] ?? null;
            $producto->imagen = $validated['imagen'] ?? null;
            $producto->precio = $validated['precio'];
            $producto->categoria_id = $validated['categoria_id'];
            $producto->activo = true; // por defecto, está activo
            $producto->save();

            return response()->json([
                'message' => 'Producto creado correctamente.',
                'producto' => new ProductoResource($producto->load('categoria')),
            ], 201);
        } catch (ValidationException $e) {
            // Captura errores de validación
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Captura cualquier otro error
            return response()->json([
                'message' => 'Error interno al crear el producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto obtenido correctamente.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 500, description: 'Error al obtener el producto.'),
        ]
    )]
    public function show($id)
    {
        try {
            $producto = Producto::with('categoria')->findOrFail($id);

            return response()->json([
                'message' => 'Producto obtenido correctamente.',
                'producto' => new ProductoResource($producto),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el producto.',
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
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre', 'activo'],
                properties: [
                    new OA\Property(property: 'categoria_id', type: 'integer', example: 1),
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 100, example: 'Hamburguesa Triple'),
                    new OA\Property(property: 'descripcion', type: 'string', nullable: true, example: 'Triple carne y queso'),
                    new OA\Property(property: 'precio', type: 'number', format: 'float', example: 5500.00),
                    new OA\Property(property: 'imagen', type: 'string', format: 'uri', nullable: true, example: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500'),
                    new OA\Property(property: 'activo', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado correctamente.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            $producto = Producto::findOrFail($id);
            $esActivo = $request->has('activo')
                ? $request->boolean('activo')
                : (bool) $producto->activo;

            $reglasNombre = ['sometimes', 'required', 'string', 'min:5', 'max:100'];
            if ($esActivo) {
                $reglasNombre[] = Rule::unique('productos', 'nombre')
                    ->whereNull('deleted_at')
                    ->where('activo', true)
                    ->ignore($producto->getKey(), $producto->getKeyName());
            }

            $validated = $request->validate([
                'nombre' => $reglasNombre,
                'descripcion' => 'sometimes|nullable|string|max:255',
                'imagen' => [
                    'sometimes',
                    'nullable',
                    'url',
                    'max:255',
                    Rule::unique('productos', 'imagen')->ignore($producto->getKey(), $producto->getKeyName()),
                ],
                'precio' => 'sometimes|required|numeric|min:1|max:60000',
                'categoria_id' => 'sometimes|required|integer|min:1|exists:categorias,id',
                'activo' => 'sometimes|required|boolean',
            ], [
                'nombre.required' => 'El nombre del producto es obligatorio.',
                'nombre.string' => 'El nombre del producto debe ser una cadena de texto.',
                'nombre.min' => 'El nombre del producto debe tener al menos 5 caracteres.',
                'nombre.max' => 'El nombre del producto no debe exceder los 100 caracteres.',
                'nombre.unique' => 'Ya existe un producto con ese nombre.',
                'descripcion.string' => 'La descripción debe ser una cadena de texto.',
                'descripcion.max' => 'La descripción no debe exceder los 255 caracteres.',
                'imagen.url' => 'La imagen debe ser una URL válida.',
                'imagen.max' => 'La URL de la imagen no debe exceder los 255 caracteres.',
                'imagen.unique' => 'Ya existe un producto con esa imagen.',
                'precio.numeric' => 'El precio del producto debe ser numérico.',
                'precio.min' => 'El precio del producto debe ser mayor a $1.',
                'precio.max' => 'El precio del producto no debe exceder los $60mil.',
                'categoria_id.integer' => 'La categoría del producto debe ser un entero.',
                'categoria_id.min' => 'La categoría del producto no puede ser cero.',
                'categoria_id.exists' => 'La categoría indicada no existe.',
                'activo.boolean' => 'El campo activo debe ser un valor booleano.',
            ]);

            foreach (['nombre', 'descripcion', 'imagen', 'precio', 'categoria_id', 'activo'] as $campo) {
                if (array_key_exists($campo, $validated)) {
                    $producto->{$campo} = $validated[$campo];
                }
            }

            $producto->nombre_activo = $esActivo
                ? ($validated['nombre'] ?? $producto->nombre)
                : null;
            $producto->save();
            $producto->refresh()->load('categoria');

            return response()->json([
                'message' => 'Producto actualizado correctamente.',
                'producto' => new ProductoResource($producto),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'No se pudo actualizar el producto por un conflicto con los datos existentes.',
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al actualizar el producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto eliminado correctamente.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function destroy($id)
    {
        try {
            $producto = Producto::findOrFail($id);

            DB::transaction(function () use ($producto) {
                $producto->nombre_activo = null;
                $producto->activo = false;
                $producto->save();
                $producto->delete();
            });

            return response()->json([
                'message' => 'Producto eliminado correctamente.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'No se puede eliminar el producto porque está siendo utilizado por otros registros.',
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al eliminar el producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto restaurado correctamente.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function restore($id)
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del producto no es válido.',
                ], 422);
            }

            $producto = Producto::withTrashed()->find($id);

            if (! $producto) {
                return response()->json([
                    'message' => 'Producto no encontrado.',
                ], 404);
            }

            if (! $producto->trashed()) {
                return response()->json([
                    'message' => 'El producto no está eliminado.',
                ], 409);
            }

            DB::transaction(function () use ($producto) {
                $producto->restore();
                $producto->activo = true;
                $producto->nombre_activo = $producto->nombre;
                $producto->save();
            });

            return response()->json([
                'message' => 'Producto restaurado correctamente.',
                'producto' => new ProductoResource($producto->load('categoria')),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al restaurar el producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
