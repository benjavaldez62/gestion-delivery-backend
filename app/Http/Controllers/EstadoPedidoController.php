<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstadoPedidoRequest;
use App\Http\Requests\UpdateEstadoPedidoRequest;
use App\Http\Resources\EstadoPedidoResource;
use App\Models\EstadoPedido;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use OpenApi\Attributes as OA;

class EstadoPedidoController extends Controller
{
    #[OA\Get(
        path: '/api/estado-pedidos',
        summary: 'Listar todos los estados de pedidos',
        tags: ['Estados de Pedido'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Estados de pedidos obtenidos exitosamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No se encontraron estados de pedidos para mostrar.'),
            new OA\Response(response: 500, description: 'Error interno del servidor al obtener estados de pedidos.'),
        ]
    )]
    public function index()
    {
        try {
            $estados = EstadoPedido::all();

            if ($estados->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron estados de pedidos para mostrar.',
                ], 404);
            }

            return response()->json([
                'message' => 'Estados de pedidos obtenidos exitosamente.',
                'estadoPedidos' => EstadoPedidoResource::collection($estados),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor al obtener estados de pedidos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/estado-pedidos-desactivados',
        summary: 'Listar todos los estados de pedidos desactivados',
        tags: ['Estados de Pedido'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Estados de pedidos desactivados obtenidos exitosamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No se encontraron estados de pedido desactivados.'),
            new OA\Response(response: 500, description: 'Error interno del servidor al obtener estados de pedido desactivados.'),
        ]
    )]
    public function indexDesactivados()
    {
        try {
            $estadoPedidoDesactivados = EstadoPedido::onlyTrashed()->get();

            if ($estadoPedidoDesactivados->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron estados de pedido desactivados.',
                ], 404);
            }

            return response()->json([
                'message' => 'Estados de pedido desactivados obtenidos exitosamente.',
                'estadoPedidosDesactivados' => EstadoPedidoResource::collection($estadoPedidoDesactivados),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor al obtener estados de pedido desactivados.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function create()
    {
        //
    }

    #[OA\Post(
        path: '/api/estado-pedidos',
        summary: 'Crear un nuevo estado de pedido',
        tags: ['Estados de Pedido'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 50, example: 'Pendiente'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'El pedido fue creado correctamente'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Estado de pedido creado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Datos inválidos o error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el estado de pedido.'),
        ]
    )]
    public function store(StoreEstadoPedidoRequest $request)
    {
        try {
            $validated = $request->validated();

            $estadoPedido = new EstadoPedido;
            $estadoPedido->nombre = $validated['nombre'];
            $estadoPedido->descripcion = $validated['descripcion'] ?? null;
            $estadoPedido->save();

            return response()->json([
                'message' => 'Estado de pedido creado correctamente.',
                'estadoPedido' => new EstadoPedidoResource($estadoPedido),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al crear el estado de pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/estado-pedidos/{id}',
        summary: 'Obtener un estado de pedido por ID',
        tags: ['Estados de Pedido'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de estado de pedido',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado de pedido obtenido exitosamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Estado de pedido no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el estado de pedido.'),
        ]
    )]
    public function show($id)
    {
        try {
            $estadoPedido = EstadoPedido::findOrFail($id);

            return response()->json([
                'message' => 'Estado de pedido obtenido exitosamente.',
                'estadoPedido' => new EstadoPedidoResource($estadoPedido),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Estado de pedido no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el estado de pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit(EstadoPedido $estadoPedido)
    {
        //
    }

    #[OA\Put(
        path: '/api/estado-pedidos/{id}',
        summary: 'Actualizar un estado de pedido por ID',
        tags: ['Estados de Pedido'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del estado de pedido a actualizar',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 50, example: 'Pendiente'),
                    new OA\Property(property: 'descripcion', type: 'string', maxLength: 255, nullable: true, example: 'Pedido aún no ingresó a cocina.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Estado de pedido actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Estado de pedido no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el estado de pedido.'),
        ]
    )]
    public function update(UpdateEstadoPedidoRequest $request, $id)
    {
        try {
            $estadoPedido = EstadoPedido::findOrFail($id);
            $validated = $request->validated();

            if (array_key_exists('nombre', $validated)) {
                $estadoPedido->nombre = $validated['nombre'];
            }
            if (array_key_exists('descripcion', $validated)) {
                $estadoPedido->descripcion = $validated['descripcion'] ?? null;
            }
            $estadoPedido->save();

            return response()->json([
                'message' => 'Estado de pedido actualizado correctamente.',
                'estadoPedido' => new EstadoPedidoResource($estadoPedido),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Estado de pedido no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al actualizar el estado de pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/estado-pedidos/{id}',
        summary: 'Eliminar un estado de pedido por ID',
        tags: ['Estados de Pedido'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del estado de pedido a eliminar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado de pedido eliminado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Estado de pedido no encontrado.'),
            new OA\Response(response: 409, description: 'No se puede eliminar el estado de pedido porque está siendo utilizado por otros registros.'),
            new OA\Response(response: 422, description: 'ID no válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el estado de pedido.'),
        ]
    )]
    public function destroy($id)
    {
        try {
            $estadoPedido = EstadoPedido::findOrFail($id);

            // Verificar si existen pedidos asociados a este estado
            if ($estadoPedido->pedidos()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el estado de pedido porque está siendo utilizado por otros registros.',
                ], 409);
            }

            $estadoPedido->delete();

            return response()->json([
                'message' => 'Estado de pedido eliminado correctamente.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Estado de pedido no encontrado.',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'No se puede eliminar el estado de pedido porque está siendo utilizado por otros registros.',
                'error' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al eliminar el estado de pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/estado-pedidos/{id}/restore',
        summary: 'Restaurar un estado de pedido eliminado',
        tags: ['Estados de Pedido'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del estado de pedido a restaurar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado de pedido restaurado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Estado de pedido no encontrado.'),
            new OA\Response(response: 409, description: 'El estado de pedido no está eliminado.'),
            new OA\Response(response: 422, description: 'El ID no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar el estado de pedido.'),
        ]
    )]
    public function restore($id)
    {
        try {
            $estadoPedido = EstadoPedido::onlyTrashed()->findOrFail($id);
            $estadoPedido->restore();

            return response()->json([
                'message' => 'Estado de pedido restaurado correctamente.',
                'estadoPedido' => new EstadoPedidoResource($estadoPedido),
            ], 200);
        } catch (ModelNotFoundException $e) {
            // Verificar si existe activo (sin eliminar) para devolver 409
            if (EstadoPedido::where('id', $id)->exists()) {
                return response()->json([
                    'message' => 'El estado de pedido no está eliminado.',
                ], 409);
            }

            return response()->json([
                'message' => 'Estado de pedido no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al restaurar el estado de pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
