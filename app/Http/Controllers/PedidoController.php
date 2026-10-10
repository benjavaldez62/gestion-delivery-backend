<?php

namespace App\Http\Controllers;

use App\Http\Resources\PedidoResource;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PedidoController extends Controller
{
    #[OA\Get(
        path: '/api/pedidos',
        summary: 'Listar todos los pedidos',
        tags: ['Pedidos'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Pedidos obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'No autorizado'),
            new OA\Response(response: 404, description: 'No hay pedidos disponibles.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $userRoleName = $user?->role?->nombre;
            $isAllowedRole = in_array((int) $user?->role_id, [1, 2, 3], true)
                || ($userRoleName && strcasecmp($userRoleName, 'superadmin') === 0);

            if (! $user || ! $user->activo || ! $isAllowedRole) {
                return response()->json([
                    'message' => 'No autorizado para consultar los pedidos.',
                ], 403);
            }

            $pedidos = Pedido::with([
                'cliente',
                'cocinero',
                'repartidor',
                'estadoPedido',
                'metodoPago',
                'items.producto',
                'pagos',
            ])->orderBy('created_at', 'desc')->get();

            if ($pedidos->isEmpty()) {
                return response()->json([
                    'message' => 'No hay pedidos disponibles.',
                ], 404);
            }

            return response()->json([
                'message' => 'Pedidos obtenidos correctamente.',
                'pedidos' => PedidoResource::collection($pedidos),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener los pedidos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/pedidos',
        summary: 'Crear un nuevo pedido',
        tags: ['Pedidos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cliente_id', 'estado_pedidos_id'],
                properties: [
                    new OA\Property(property: 'cliente_id', type: 'integer', example: 1),
                    new OA\Property(property: 'estado_pedidos_id', type: 'integer', example: 1),
                    new OA\Property(property: 'subtotal', type: 'number', format: 'float', nullable: true, example: 2500.00),
                    new OA\Property(property: 'monto_total', type: 'number', format: 'float', nullable: true, example: 2500.00),
                    new OA\Property(property: 'metodo_pago_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'cocinero_id', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'repartidor_id', type: 'integer', nullable: true, example: 3),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pedido creado correctamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el pedido.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'cliente_id' => 'required|integer|exists:clientes,id',
                'estado_pedidos_id' => 'required|integer|exists:estado_pedidos,id',
                'subtotal' => 'nullable|numeric|min:0',
                'monto_total' => 'nullable|numeric|min:0',
                'metodo_pago_id' => 'nullable|integer|exists:metodos_pago,id',
                'cocinero_id' => 'nullable|integer|exists:users,id',
                'repartidor_id' => 'nullable|integer|exists:users,id',
            ], [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.integer' => 'El ID del cliente debe ser un número entero.',
                'cliente_id.exists' => 'El cliente seleccionado no existe.',
                'estado_pedidos_id.required' => 'El estado del pedido es obligatorio.',
                'estado_pedidos_id.integer' => 'El ID del estado del pedido debe ser un número entero.',
                'estado_pedidos_id.exists' => 'El estado de pedido seleccionado no existe.',
                'subtotal.numeric' => 'El subtotal debe ser un valor numérico.',
                'subtotal.min' => 'El subtotal no puede ser negativo.',
                'monto_total.numeric' => 'El monto total debe ser un valor numérico.',
                'monto_total.min' => 'El monto total no puede ser negativo.',
                'metodo_pago_id.integer' => 'El ID del método de pago debe ser un número entero.',
                'metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
                'cocinero_id.integer' => 'El ID del cocinero debe ser un número entero.',
                'cocinero_id.exists' => 'El cocinero seleccionado no existe.',
                'repartidor_id.integer' => 'El ID del repartidor debe ser un número entero.',
                'repartidor_id.exists' => 'El repartidor seleccionado no existe.',
            ]);

            if (! isset($validated['monto_total'])) {
                $validated['monto_total'] = $validated['subtotal'] ?? 0;
            }

            $pedido = Pedido::create($validated);
            $pedido->load(['cliente', 'cocinero', 'repartidor', 'estadoPedido', 'metodoPago']);

            return response()->json([
                'message' => 'Pedido creado correctamente.',
                'pedido' => new PedidoResource($pedido),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al crear el pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/pedidos/{id}',
        summary: 'Obtener un pedido por ID',
        tags: ['Pedidos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pedido',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido obtenido exitosamente.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'El ID del pedido no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el pedido.'),
        ]
    )]
    public function show($id)
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del pedido no es válido.',
                ], 422);
            }

            $pedido = Pedido::with([
                'cliente',
                'cocinero',
                'repartidor',
                'estadoPedido',
                'metodoPago',
                'items.producto',
                'pagos',
            ])->findOrFail($id);

            return response()->json([
                'pedido' => new PedidoResource($pedido),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/pedidos/{id}',
        summary: 'Actualizar un pedido por ID',
        tags: ['Pedidos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pedido a actualizar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'cliente_id', type: 'integer', example: 1),
                    new OA\Property(property: 'estado_pedidos_id', type: 'integer', example: 2),
                    new OA\Property(property: 'subtotal', type: 'number', format: 'float', nullable: true, example: 3000.00),
                    new OA\Property(property: 'monto_total', type: 'number', format: 'float', nullable: true, example: 3000.00),
                    new OA\Property(property: 'metodo_pago_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'cocinero_id', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'repartidor_id', type: 'integer', nullable: true, example: 3),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pedido actualizado correctamente.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el pedido.'),
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del pedido no es válido.',
                ], 422);
            }

            $pedido = Pedido::findOrFail($id);

            $validated = $request->validate([
                'cliente_id' => 'sometimes|required|integer|exists:clientes,id',
                'estado_pedidos_id' => 'sometimes|required|integer|exists:estado_pedidos,id',
                'subtotal' => 'nullable|numeric|min:0',
                'monto_total' => 'nullable|numeric|min:0',
                'metodo_pago_id' => 'sometimes|nullable|integer|exists:metodos_pago,id',
                'cocinero_id' => 'nullable|integer|exists:users,id',
                'repartidor_id' => 'nullable|integer|exists:users,id',
            ], [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.integer' => 'El ID del cliente debe ser un número entero.',
                'cliente_id.exists' => 'El cliente seleccionado no existe.',
                'estado_pedidos_id.required' => 'El estado del pedido es obligatorio.',
                'estado_pedidos_id.integer' => 'El ID del estado del pedido debe ser un número entero.',
                'estado_pedidos_id.exists' => 'El estado de pedido seleccionado no existe.',
                'subtotal.numeric' => 'El subtotal debe ser un valor numérico.',
                'subtotal.min' => 'El subtotal no puede ser negativo.',
                'monto_total.numeric' => 'El monto total debe ser un valor numérico.',
                'monto_total.min' => 'El monto total no puede ser negativo.',
                'metodo_pago_id.integer' => 'El ID del método de pago debe ser un número entero.',
                'metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
                'cocinero_id.integer' => 'El ID del cocinero debe ser un número entero.',
                'cocinero_id.exists' => 'El cocinero seleccionado no existe.',
                'repartidor_id.integer' => 'El ID del repartidor debe ser un número entero.',
                'repartidor_id.exists' => 'El repartidor seleccionado no existe.',
            ]);

            $pedido->update($validated);
            $pedido->load(['cliente', 'cocinero', 'repartidor', 'estadoPedido', 'metodoPago', 'items.producto', 'pagos']);

            return response()->json([
                'message' => 'Pedido actualizado correctamente.',
                'pedido' => new PedidoResource($pedido),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al actualizar el pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/pedidos/{id}',
        summary: 'Eliminar un pedido por ID',
        tags: ['Pedidos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pedido a eliminar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido eliminado correctamente.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 409, description: 'No se puede eliminar el pedido porque está siendo utilizado por otros registros.'),
            new OA\Response(response: 422, description: 'El ID del pedido no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el pedido.'),
        ]
    )]
    public function destroy($id)
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del pedido no es válido.',
                ], 422);
            }

            $pedido = Pedido::findOrFail($id);
            $pedido->delete();

            return response()->json([
                'message' => 'Pedido eliminado correctamente.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Error - No se puede eliminar el pedido porque está siendo utilizado por otros registros.',
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al eliminar el pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/pedidos-desactivados',
        summary: 'Listar todos los pedidos desactivados',
        tags: ['Pedidos'],
        responses: [
            new OA\Response(response: 200, description: 'Pedidos desactivados obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay pedidos desactivados para mostrar.'),
            new OA\Response(response: 500, description: 'Error interno del servidor al obtener pedidos desactivados.'),
        ]
    )]
    public function indexDesactivados()
    {
        try {
            // llamamos lo inhabilitado
            $pedidosDesactivados = Pedido::onlyTrashed()->with('metodoPago')->get();

            // Caso 404: NO hay nada inhabilitado -> retornamos mensaje.
            if ($pedidosDesactivados->isEmpty()) {
                return response()->json([
                    'message' => 'No hay pedidos desactivados para mostrar.',
                ], 404);
            }

            // Caso 200: retornamos lo desactivado
            return response()->json([
                'message' => 'Pedidos desactivados obtenidos correctamente.',
                'pedidosDesactivados' => PedidoResource::collection($pedidosDesactivados),
            ], 200);
        } catch (\Exception $e) {
            // Caso 500: Falla en el servidor -> retornamos mensaje.
            return response()->json([
                'message' => 'Error interno del servidor al obtener pedidos desactivados.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/pedidos/{id}/restore',
        summary: 'Restaurar un pedido eliminado',
        tags: ['Pedidos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pedido a restaurar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido restaurado correctamente.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 409, description: 'El pedido no está eliminado.'),
            new OA\Response(response: 422, description: 'El ID no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar el pedido.'),
        ]
    )]
    public function restore($id)
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del pedido no es válido.',
                ], 422);
            }

            $pedido = Pedido::withTrashed()->find($id);

            if (! $pedido) {
                return response()->json([
                    'message' => 'Pedido no encontrado.',
                ], 404);
            }

            if (! $pedido->trashed()) {
                return response()->json([
                    'message' => 'El pedido no está eliminado.',
                ], 409);
            }

            $pedido->restore();
            $pedido->load(['cliente', 'cocinero', 'repartidor', 'estadoPedido', 'metodoPago']);

            return response()->json([
                'message' => 'Pedido restaurado correctamente.',
                'pedido' => new PedidoResource($pedido),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al restaurar el pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
