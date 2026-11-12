<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarPersonalPedidoRequest;
use App\Http\Requests\ConfirmarPedidoTelegramRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PedidoController extends Controller
{
    #[OA\Put(
        path: '/api/pedidos/{id}/asignaciones',
        summary: 'Asignar cocinero o repartidor a un pedido',
        tags: ['Pedidos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pedido',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'cocinero_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'repartidor_id', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Personal asignado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'El personal debe estar activo y tener el rol correspondiente.'),
        ]
    )]
    public function asignarPersonal(AsignarPersonalPedidoRequest $request, string $id): JsonResponse
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del pedido no es válido.',
                ], 422);
            }

            $pedido = Pedido::findOrFail($id);
            $pedido->update($request->validated());
            $pedido->load(['cliente', 'cocinero', 'repartidor', 'estadoPedido']);

            return response()->json([
                'message' => 'Personal asignado correctamente.',
                'pedido' => new PedidoResource($pedido),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al asignar personal al pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/pedidos',
        summary: 'Listar todos los pedidos operativos',
        tags: ['Pedidos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'incluir_pre_pedidos',
                in: 'query',
                required: false,
                description: 'Indica si se deben incluir los pedidos en estado Pre-pedido en el listado',
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
        ],
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

            $query = Pedido::with([
                'cliente',
                'cocinero',
                'repartidor',
                'estadoPedido',
                'metodoPago',
                'items.producto',
                'items.adicionales',
                'pagos.estadoPago',
            ]);

            if (! $request->boolean('incluir_pre_pedidos')) {
                $query->whereHas('estadoPedido', fn ($q) => $q->whereRaw("LOWER(TRIM(nombre)) != 'pre-pedido'"));
            }

            $pedidos = $query->orderBy('created_at', 'desc')->get();

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
                required: ['cliente_id'],
                properties: [
                    new OA\Property(property: 'cliente_id', type: 'integer', example: 1),
                    new OA\Property(property: 'subtotal', type: 'number', format: 'float', nullable: true, example: 2500.00),
                    new OA\Property(property: 'monto_total', type: 'number', format: 'float', nullable: true, example: 2500.00),
                    new OA\Property(property: 'metodo_pago_id', type: 'integer', nullable: true, example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pedido creado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el pedido.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'cliente_id' => 'required|integer|exists:clientes,id',
                'subtotal' => 'nullable|numeric|min:0',
                'monto_total' => 'nullable|numeric|min:0',
                'metodo_pago_id' => 'nullable|integer|exists:metodos_pago,id',
            ], [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.integer' => 'El ID del cliente debe ser un número entero.',
                'cliente_id.exists' => 'El cliente seleccionado no existe.',
                'subtotal.numeric' => 'El subtotal debe ser un valor numérico.',
                'subtotal.min' => 'El subtotal no puede ser negativo.',
                'monto_total.numeric' => 'El monto total debe ser un valor numérico.',
                'monto_total.min' => 'El monto total no puede ser negativo.',
                'metodo_pago_id.integer' => 'El ID del método de pago debe ser un número entero.',
                'metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
            ]);

            if (! isset($validated['monto_total'])) {
                $validated['monto_total'] = $validated['subtotal'] ?? 0;
            }

            // Crear pedido con estado inicial 'Sin Asignar' (o primer estado disponible como respaldo)
            $pedido = new Pedido;
            $pedido->estado_pedidos_id = EstadoPedido::whereRaw("LOWER(TRIM(nombre)) = 'sin asignar'")->value('id')
                ?? EstadoPedido::orderBy('id')->value('id');
            $pedido->cliente_id = $validated['cliente_id'] ?? null;
            $pedido->subtotal = $validated['subtotal'] ?? null;
            $pedido->monto_total = $validated['monto_total'];
            $pedido->metodo_pago_id = $validated['metodo_pago_id'];
            $pedido->cocinero_id = null; // por defecto, S/A
            $pedido->repartidor_id = null; // por defecto, S/A
            $pedido->save();
            
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
            new OA\Response(response: 401, description: 'No autenticado.'),
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
                'items.adicionales',
                'pagos.estadoPago',
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
            new OA\Response(response: 401, description: 'No autenticado.'),
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
            $pedido->load(['cliente', 'cocinero', 'repartidor', 'estadoPedido', 'metodoPago', 'items.producto', 'items.adicionales', 'pagos.estadoPago']);

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
            new OA\Response(response: 401, description: 'No autenticado.'),
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
            new OA\Response(response: 401, description: 'No autenticado.'),
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
            new OA\Response(response: 401, description: 'No autenticado.'),
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

    #[OA\Post(
        path: '/api/pedidos/{id}/confirmar-telegram',
        summary: 'Confirmar un pre-pedido desde el bot de Telegram y pasarlo a Sin Asignar',
        tags: ['Pedidos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pedido en estado Pre-pedido a confirmar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 255, nullable: true, example: 'Juan Pérez'),
                    new OA\Property(property: 'username_telegram', type: 'string', maxLength: 255, nullable: true, example: 'juan_perez'),
                    new OA\Property(property: 'telefono', type: 'string', maxLength: 30, nullable: true, example: '+5493454123456'),
                    new OA\Property(property: 'direccion', type: 'string', maxLength: 255, nullable: true, example: 'Av. Eva Perón 1234'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pedido confirmado correctamente. Estado actualizado a Sin Asignar.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'El pedido no se encuentra en estado Pre-pedido o error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al confirmar el pedido.'),
        ]
    )]
    public function confirmarTelegram(ConfirmarPedidoTelegramRequest $request, $id): JsonResponse
    {
        try {
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'Pedido no encontrado.',
                ], 404);
            }

            $pedido = Pedido::with(['cliente', 'estadoPedido'])->findOrFail($id);

            $estadoActual = mb_strtolower(trim($pedido->estadoPedido?->nombre ?? ''));
            if ($estadoActual !== 'pre-pedido') {
                return response()->json([
                    'message' => 'El pedido no se encuentra en estado Pre-pedido o ya fue confirmado.',
                ], 422);
            }

            $validated = $request->validated();

            if ($pedido->cliente) {
                $cliente = $pedido->cliente;
                $huboCambiosCliente = false;

                $nuevoUsername = trim((string) ($validated['username_telegram'] ?? $validated['nombre'] ?? ''));
                $nuevoUsername = ltrim($nuevoUsername, '@');
                if ($nuevoUsername !== '') {
                    $usernameTruncado = mb_substr($nuevoUsername, 0, 30);
                    $existeOtro = Cliente::withTrashed()
                        ->where('username', $usernameTruncado)
                        ->where('id', '!=', $cliente->id)
                        ->exists();

                    if (! $existeOtro) {
                        $cliente->username = $usernameTruncado;
                        $huboCambiosCliente = true;
                    }
                }

                if (isset($validated['telefono']) && trim((string) $validated['telefono']) !== '') {
                    $cliente->telefono = trim((string) $validated['telefono']);
                    $huboCambiosCliente = true;
                }

                if (isset($validated['direccion']) && trim((string) $validated['direccion']) !== '') {
                    $cliente->direccion = trim((string) $validated['direccion']);
                    $huboCambiosCliente = true;
                }

                if ($huboCambiosCliente) {
                    $cliente->save();
                }
            }

            $estadoSinAsignar = EstadoPedido::whereRaw("LOWER(TRIM(nombre)) = 'sin asignar'")->first()
                ?? EstadoPedido::firstOrCreate(
                    ['nombre' => 'Sin Asignar'],
                    ['descripcion' => 'El pedido fue creado y está pendiente de asignación de un recurso']
                );

            $pedido->estado_pedidos_id = $estadoSinAsignar->id;
            $pedido->save();

            $pedido->load([
                'cliente',
                'cocinero',
                'repartidor',
                'estadoPedido',
                'metodoPago',
                'items.producto',
                'items.adicionales',
                'pagos.estadoPago',
            ]);

            return response()->json([
                'message' => 'Pedido confirmado correctamente. Estado actualizado a Sin Asignar.',
                'pedido' => new PedidoResource($pedido),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al confirmar el pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
