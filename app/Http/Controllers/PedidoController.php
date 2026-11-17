<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarPersonalPedidoRequest;
use App\Http\Requests\ConfirmarPedidoTelegramRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Services\PagoEfectivoService;
use App\Services\TelegramService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

            $datosAsignacion = $request->validated();
            $pedido = DB::transaction(function () use ($id, $datosAsignacion): Pedido {
                $pedido = Pedido::query()
                    ->with('estadoPedido')
                    ->lockForUpdate()
                    ->findOrFail($id);

                $pedido->update($datosAsignacion);

                if ($pedido->estadoPedido?->nombre === 'Sin Asignar') {
                    $estadoPendiente = EstadoPedido::query()
                        ->where('nombre', 'Pendiente')
                        ->first();

                    if (! $estadoPendiente) {
                        throw new \RuntimeException('No se encontró el estado Pendiente para actualizar el pedido.');
                    }

                    $pedido->update(['estado_pedidos_id' => $estadoPendiente->id]);
                }

                return $pedido->load(['cliente', 'cocinero', 'repartidor', 'estadoPedido']);
            });

            if ($pedido->wasChanged('estado_pedidos_id')) {
                app(TelegramService::class)->notificarCambioEstado($pedido);
            }

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

            if (isset($validated['estado_pedidos_id'])) {
                $estadoDestino = EstadoPedido::findOrFail($validated['estado_pedidos_id']);
                if (mb_strtolower(trim($estadoDestino->nombre)) === 'entregado') {
                    if (count($validated) !== 1) {
                        throw ValidationException::withMessages([
                            'pedido' => 'La entrega debe confirmarse sin modificar otros datos del pedido.',
                        ]);
                    }

                    [$pedido, $comprobante] = DB::transaction(function () use ($id, $request, $estadoDestino) {
                        $pedido = Pedido::with(['estadoPedido', 'cliente'])->lockForUpdate()->findOrFail($id);
                        $this->autorizarEntrega($request, $pedido);
                        $estadoActual = mb_strtolower(trim($pedido->estadoPedido?->nombre ?? ''));
                        if ($estadoActual === 'entregado') {
                            $comprobante = $pedido->pagos()->with(['estadoPago', 'comprobantes'])->get()
                                ->filter(fn ($pago) => mb_strtolower(trim($pago->estadoPago?->nombre ?? '')) === 'pagado')
                                ->flatMap(fn ($pago) => $pago->comprobantes)
                                ->firstWhere('tipo_comprobante', 'Ticket no fiscal');
                            if (! $comprobante) {
                                throw ValidationException::withMessages(['pago' => 'El pedido entregado requiere revisión del pago.']);
                            }
                        } else {
                            if ($estadoActual !== 'en camino') {
                                throw ValidationException::withMessages(['pedido' => 'Solo se puede entregar un pedido que está en camino.']);
                            }
                            $comprobante = app(PagoEfectivoService::class)->confirmarCobro($pedido);
                            $pedido->update(['estado_pedidos_id' => $estadoDestino->id]);
                        }

                        return [$pedido->load([
                            'cliente', 'cocinero', 'repartidor', 'estadoPedido', 'metodoPago',
                            'items.producto', 'items.adicionales', 'pagos.estadoPago',
                        ]), $comprobante];
                    });

                    $enviado = app(TelegramService::class)->enviarTicket($pedido, $comprobante);

                    return response()->json([
                        'message' => $enviado
                            ? 'Entrega y cobro registrados. Ticket enviado por Telegram.'
                            : 'Entrega y cobro registrados, pero no se pudo enviar el ticket. Podés reintentar el envío.',
                        'comprobante_enviado' => $enviado,
                        'pedido' => new PedidoResource($pedido),
                    ]);
                }
            }

            [$pedido, $cambioEstado] = DB::transaction(function () use ($id, $validated) {
                $pedido = Pedido::with('estadoPedido')->lockForUpdate()->findOrFail($id);
                if (mb_strtolower(trim($pedido->estadoPedido?->nombre ?? '')) === 'entregado') {
                    throw ValidationException::withMessages(['pedido' => 'No se puede modificar un pedido entregado.']);
                }
                if (mb_strtolower(trim($pedido->estadoPedido?->nombre ?? '')) === 'cancelado') {
                    throw ValidationException::withMessages(['pedido' => 'No se puede modificar un pedido cancelado.']);
                }
                if ($pedido->pagos()->exists() &&
                    ((array_key_exists('monto_total', $validated) &&
                        (int) round((float) $validated['monto_total'] * 100) !== (int) round((float) $pedido->monto_total * 100)) ||
                    (array_key_exists('subtotal', $validated) &&
                        ($validated['subtotal'] === null || (int) round((float) $validated['subtotal'] * 100) !== (int) round((float) $pedido->subtotal * 100))) ||
                    (array_key_exists('metodo_pago_id', $validated) &&
                        (int) $validated['metodo_pago_id'] !== (int) $pedido->metodo_pago_id))) {
                    throw ValidationException::withMessages(['pago' => 'No se puede modificar el subtotal, total o método directamente en un pedido con pagos.']);
                }

                $cambioEstado = isset($validated['estado_pedidos_id']) && (int) $validated['estado_pedidos_id'] !== (int) $pedido->estado_pedidos_id;
                if ($cambioEstado) {
                    $destino = EstadoPedido::findOrFail($validated['estado_pedidos_id']);
                    if (mb_strtolower(trim($destino->nombre)) === 'cancelado') {
                        $pagos = $pedido->pagos()->with('estadoPago')->lockForUpdate()->get();
                        foreach ($pagos as $pago) {
                            if (! in_array(mb_strtolower(trim($pago->estadoPago?->nombre ?? '')), ['pendiente', 'fallido'], true)
                                || $pago->comprobantes()->exists()) {
                                throw ValidationException::withMessages(['pago' => 'No se puede cancelar un pedido con un pago confirmado o facturado.']);
                            }
                            $pago->delete();
                        }
                    }
                }
                $pedido->update($validated);

                return [$pedido->load(['cliente', 'cocinero', 'repartidor', 'estadoPedido', 'metodoPago', 'items.producto', 'items.adicionales', 'pagos.estadoPago']), $cambioEstado];
            });

            if ($cambioEstado) {
                app(TelegramService::class)->notificarCambioEstado($pedido);
            }

            return response()->json([
                'message' => 'Pedido actualizado correctamente.',
                'pedido' => new PedidoResource($pedido),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'No tenés permiso para confirmar esta entrega.'], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Error interno al actualizar el pedido.',
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
                    new OA\Property(property: 'chat_id', type: 'integer', nullable: true, example: 987654321),
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

            $validated = $request->validated();
            $pedido = DB::transaction(function () use ($id, $validated): Pedido {
                $pedido = Pedido::with(['cliente', 'estadoPedido'])->lockForUpdate()->findOrFail($id);
                $estadoActual = mb_strtolower(trim($pedido->estadoPedido?->nombre ?? ''));
                if ($estadoActual !== 'pre-pedido') {
                    throw ValidationException::withMessages([
                        'pedido' => 'El pedido no se encuentra en estado Pre-pedido o ya fue confirmado.',
                    ]);
                }

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

                if (isset($validated['chat_id'])) {
                    $pedido->telegram_chat_id = (int) $validated['chat_id'];
                }
                if (isset($validated['username_telegram'])) {
                    $pedido->telegram_username = ltrim(trim((string) $validated['username_telegram']), '@');
                }

                $pedido->estado_pedidos_id = $estadoSinAsignar->id;
                $pedido->save();
                app(PagoEfectivoService::class)->registrarPendiente($pedido);

                return $pedido->load([
                    'cliente', 'cocinero', 'repartidor', 'estadoPedido', 'metodoPago',
                    'items.producto', 'items.adicionales', 'pagos.estadoPago',
                ]);
            });

            return response()->json([
                'message' => 'Pedido confirmado correctamente. Estado actualizado a Sin Asignar.',
                'pedido' => new PedidoResource($pedido),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors()['pedido'][0] ?? 'No se pudo confirmar el pedido.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al confirmar el pedido de Telegram.', [
                'pedido_id' => is_numeric($id) ? (int) $id : null,
                'exception_type' => $e::class,
            ]);

            return response()->json([
                'message' => 'Error interno al confirmar el pedido.',
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/pedidos/{id}/ticket/enviar',
        summary: 'Reintentar el envío del ticket no fiscal de un pedido entregado',
        tags: ['Pedidos'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Ticket enviado.'),
            new OA\Response(response: 403, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'El pedido no tiene un ticket disponible.'),
            new OA\Response(response: 503, description: 'No se pudo enviar el ticket.'),
        ]
    )]
    public function reenviarTicket(Request $request, string $id): JsonResponse
    {
        $pedido = Pedido::with(['estadoPedido', 'pagos.estadoPago', 'pagos.comprobantes'])->findOrFail($id);
        $this->autorizarEntrega($request, $pedido);
        $pago = $pedido->pagos->first(fn ($pago) => mb_strtolower(trim($pago->estadoPago?->nombre ?? '')) === 'pagado');
        $comprobante = $pago?->comprobantes->firstWhere('tipo_comprobante', 'Ticket no fiscal');
        if (mb_strtolower(trim($pedido->estadoPedido?->nombre ?? '')) !== 'entregado' || ! $comprobante) {
            return response()->json(['message' => 'El pedido no tiene un ticket disponible.'], 422);
        }
        if (! $comprobante->contenido_ticket) {
            return response()->json(['message' => 'El ticket no tiene contenido guardado y requiere revisión.'], 422);
        }

        if (! app(TelegramService::class)->enviarTicket($pedido, $comprobante)) {
            return response()->json(['message' => 'No se pudo enviar el ticket por Telegram.'], 503);
        }

        return response()->json(['message' => 'Ticket enviado por Telegram.']);
    }

    private function autorizarEntrega(Request $request, Pedido $pedido): void
    {
        $user = $request->user();
        $rol = mb_strtolower(trim($user?->role?->nombre ?? ''));
        if (! $user?->activo || (! in_array($rol, ['administrador', 'superadmin'], true) &&
            ! ($rol === 'repartidor' && (int) $pedido->repartidor_id === (int) $user->id))) {
            throw new AuthorizationException('No tenés permiso para confirmar esta entrega.');
        }
    }
}
