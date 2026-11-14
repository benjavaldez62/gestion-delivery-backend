<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarPagoRequest;
use App\Http\Requests\RegistrarPagoPedidoRequest;
use App\Http\Resources\PagoResource;
use App\Models\EstadoPagos;
use App\Models\Pago;
use App\Models\Pedido;
use App\Services\PagoEfectivoService;
use App\Services\ResumenEnviosService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PagoController extends Controller
{
    #[OA\Get(
        path: '/api/pagos/envios',
        summary: 'Envíos cobrados por repartidor y fecha',
        description: 'Suma el costo de envío de pedidos entregados y cobrados; usa la zona horaria de pagos y señala los registros inconsistentes.',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'fecha', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))],
        responses: [
            new OA\Response(response: 200, description: 'Envíos agrupados por repartidor.', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'fecha', type: 'string', format: 'date'),
                    new OA\Property(property: 'zona_horaria', type: 'string'),
                    new OA\Property(property: 'total_envios', type: 'number'),
                    new OA\Property(property: 'repartidores', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'nombre', type: 'string'),
                            new OA\Property(property: 'total', type: 'number'),
                            new OA\Property(property: 'envios', type: 'array', items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'pedido_id', type: 'integer'),
                                    new OA\Property(property: 'importe', type: 'number'),
                                ], type: 'object'
                            )),
                        ], type: 'object'
                    )),
                    new OA\Property(property: 'pendientes_revision', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'pedido_id', type: 'integer'),
                            new OA\Property(property: 'motivo', type: 'string'),
                        ], type: 'object'
                    )),
                ]
            )),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No autorizado.'),
            new OA\Response(response: 422, description: 'Fecha inválida.'),
        ]
    )]
    public function resumenEnvios(Request $request, ResumenEnviosService $service): JsonResponse
    {
        $datos = $request->validate(['fecha' => ['sometimes', 'required', 'date_format:Y-m-d']]);

        return response()->json($service->calcular($datos['fecha'] ?? now(config('app.pagos_timezone'))->toDateString()));
    }

    #[OA\Get(
        path: '/api/pagos/resumen',
        summary: 'Ingresos cobrados del día y del mes',
        description: 'Suma los pagos en estado Pagado según su fecha de cobro, usando la zona horaria de pagos.',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Totales cobrados y fecha de referencia.', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'fecha', type: 'string', format: 'date'),
                    new OA\Property(property: 'zona_horaria', type: 'string'),
                    new OA\Property(property: 'ingreso_diario', type: 'number'),
                    new OA\Property(property: 'ingreso_mensual', type: 'number'),
                ]
            )),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No autorizado.'),
        ]
    )]
    public function resumen(): JsonResponse
    {
        $fecha = now(config('app.pagos_timezone'));
        $hoy = $fecha->toDateString();
        $inicioMes = $fecha->copy()->startOfMonth()->toDateString();
        $pagados = Pago::whereHas('estadoPago', fn ($query) => $query->whereRaw('LOWER(TRIM(nombre)) = ?', ['pagado']));

        return response()->json([
            'fecha' => $hoy,
            'zona_horaria' => config('app.pagos_timezone'),
            'ingreso_diario' => round((float) (clone $pagados)->whereDate('fecha_pago', $hoy)->sum('monto'), 2),
            'ingreso_mensual' => round((float) (clone $pagados)->whereDate('fecha_pago', '>=', $inicioMes)->whereDate('fecha_pago', '<=', $hoy)->sum('monto'), 2),
        ]);
    }

    #[OA\Get(
        path: '/api/pagos',
        summary: 'Listar pagos con filtros opcionales',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'pedido_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'busqueda', in: 'query', description: 'Número de pago, pedido o cliente, nombre o teléfono.', schema: new OA\Schema(type: 'string', maxLength: 100)),
            new OA\Parameter(name: 'estado_pago_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'metodo_pago_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pagos obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No hay pagos disponibles.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function index(Request $request)
    {
        try {
            $filtros = $request->validate([
                'pedido_id' => 'sometimes|integer|min:1',
                'busqueda' => 'sometimes|nullable|string|max:100',
                'estado_pago_id' => 'sometimes|integer|min:1',
                'metodo_pago_id' => 'sometimes|integer|min:1',
            ]);
            $query = Pago::with([
                'pedido' => fn ($q) => $q->withTrashed(),
                'pedido.cliente' => fn ($q) => $q->withTrashed(),
                'metodoPago', 'estadoPago', 'comprobantes',
            ]);
            foreach (['pedido_id', 'estado_pago_id', 'metodo_pago_id'] as $campo) {
                if (isset($filtros[$campo])) {
                    $query->where($campo, $filtros[$campo]);
                }
            }
            $busqueda = trim($filtros['busqueda'] ?? '');
            if ($busqueda !== '') {
                $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($busqueda)).'%';
                $digitos = preg_match('/^[0-9\s()+.\-]+$/', $busqueda) ? preg_replace('/\D+/', '', $busqueda) : '';
                $query->where(function ($q) use ($busqueda, $patron, $digitos) {
                    if (ctype_digit($busqueda) && strlen($busqueda) <= 18) {
                        $numero = (int) $busqueda;
                        $q->where('pagos.id', $numero)->orWhere('pedido_id', $numero);
                    }
                    $q->orWhereHas('pedido', function ($pedido) use ($busqueda, $patron, $digitos) {
                        $pedido->withTrashed()->where(function ($filtro) use ($busqueda, $patron, $digitos) {
                            if (ctype_digit($busqueda) && strlen($busqueda) <= 18) {
                                $filtro->where('cliente_id', (int) $busqueda);
                            }
                            $filtro->orWhereRaw("LOWER(nombre_cliente) LIKE ? ESCAPE '!'", [$patron])
                                ->orWhereHas('cliente', function ($cliente) use ($patron, $digitos) {
                                    $cliente->withTrashed()->where(function ($datos) use ($patron, $digitos) {
                                        $datos->whereRaw("LOWER(username) LIKE ? ESCAPE '!'", [$patron])
                                            ->orWhereRaw("LOWER(telefono) LIKE ? ESCAPE '!'", [$patron]);
                                        if ($digitos !== '') {
                                            $datos->orWhereRaw(
                                                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '') LIKE ?",
                                                ['%'.$digitos.'%']
                                            );
                                        }
                                    });
                                });
                        });
                    });
                });
            }
            $pagos = $query->get();

            if ($pagos->isEmpty()) {
                return response()->json([
                    'message' => 'No hay pagos disponibles.',
                ], 404);
            }

            return response()->json([
                'message' => 'Pagos obtenidos correctamente.',
                'pagos' => PagoResource::collection($pagos),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Filtros inválidos.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Error interno al obtener los pagos.',
            ], 500);
        }
    }

    // crea un pago nuevo

    #[OA\Post(
        path: '/api/pagos',
        summary: 'Crear un nuevo pago',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pedido_id', 'monto', 'metodo_pago_id'],
                properties: [
                    new OA\Property(property: 'pedido_id', type: 'integer', example: 10),
                    new OA\Property(property: 'monto', type: 'number', format: 'float', example: 1500.50),
                    new OA\Property(property: 'metodo_pago_id', type: 'integer', example: 1),
                    new OA\Property(property: 'referencia', type: 'string', maxLength: 255, nullable: true, example: 'TRX-987654'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pago creado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Datos inválidos o error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al crear el pago.'),
        ]
    )]
    public function store(RegistrarPagoPedidoRequest $request)
    {
        return $this->registrarPago($request, (string) $request->validated('pedido_id'));
    }

    #[OA\Post(
        path: '/api/pedidos/{pedido}/pagos',
        summary: 'Registrar un pago pendiente en efectivo para un pedido',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'pedido', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['metodo_pago_id', 'monto'],
            properties: [
                new OA\Property(property: 'metodo_pago_id', type: 'integer'),
                new OA\Property(property: 'monto', type: 'number'),
                new OA\Property(property: 'referencia', type: 'string', nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Pago pendiente registrado.'),
            new OA\Response(response: 403, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'Pago inválido o duplicado.'),
        ]
    )]
    public function registrarPago(RegistrarPagoPedidoRequest $request, string $pedido): JsonResponse
    {
        $datos = $request->validated();
        $pago = DB::transaction(function () use ($pedido, $datos): Pago {
            $orden = Pedido::with('estadoPedido')->lockForUpdate()->findOrFail($pedido);
            if (in_array(mb_strtolower(trim($orden->estadoPedido?->nombre ?? '')), ['pre-pedido', 'entregado', 'cancelado'], true)) {
                throw ValidationException::withMessages(['pedido' => 'No se puede registrar un pago para este estado del pedido.']);
            }
            if ($orden->pagos()->exists()) {
                throw ValidationException::withMessages(['pedido' => 'El pedido ya tiene un pago registrado.']);
            }
            if ((int) $datos['metodo_pago_id'] !== (int) $orden->metodo_pago_id ||
                (int) round((float) $datos['monto'] * 100) !== (int) round((float) $orden->monto_total * 100)) {
                throw ValidationException::withMessages(['pago' => 'El método y monto deben coincidir con el pedido.']);
            }
            $pago = app(PagoEfectivoService::class)->registrarPendiente($orden);
            if (array_key_exists('referencia', $datos) && $datos['referencia'] !== null) {
                $pago->update(['referencia' => $datos['referencia']]);
            }

            return $pago->load(['pedido', 'pedido.cliente' => fn ($q) => $q->withTrashed(), 'metodoPago', 'estadoPago']);
        });

        return response()->json(['message' => 'Pago creado correctamente', 'pago' => new PagoResource($pago)], 201);
    }

    #[OA\Get(
        path: '/api/pagos/{id}',
        summary: 'Obtener un pago por ID',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pago',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pago obtenido correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el pago.'),
        ]
    )]
    public function show($id)
    {
        try {
            $pago = Pago::with([
                'pedido' => fn ($q) => $q->withTrashed(),
                'pedido.cliente' => fn ($q) => $q->withTrashed(),
                'comprobantes', 'metodoPago', 'estadoPago',
            ])->findOrFail($id);

            return response()->json([
                'pago' => new PagoResource($pago),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pago no encontrado',
            ], 404);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Error interno al obtener el pago',
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/pagos/{id}',
        summary: 'Actualizar un pago por ID',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pago a actualizar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['estado_pago_id'],
                properties: [
                    new OA\Property(property: 'pedido_id', type: 'integer', example: 10),
                    new OA\Property(property: 'monto', type: 'number', format: 'float', example: 2000.00),
                    new OA\Property(property: 'metodo_pago_id', type: 'integer', example: 1),
                    new OA\Property(property: 'estado_pago_id', type: 'integer', example: 1),
                    new OA\Property(property: 'fecha_pago', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'referencia', type: 'string', maxLength: 255, nullable: true, example: 'TRX-987654'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pago actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el pago.'),
        ]
    )]
    public function update(ActualizarPagoRequest $request, $id)
    {
        try {

            // Buscar el pago
            $pago = Pago::findOrFail($id);

            $validated = $request->validated();

            $pago = DB::transaction(function () use ($pago, $validated): Pago {
                Pedido::lockForUpdate()->findOrFail($pago->pedido_id);
                $pago = Pago::with('estadoPago')->lockForUpdate()->findOrFail($pago->id);
                $estadoActual = mb_strtolower(trim($pago->estadoPago?->nombre ?? ''));
                if (! in_array($estadoActual, ['pendiente', 'fallido'], true) || $pago->comprobantes()->exists()) {
                    throw ValidationException::withMessages(['pago' => 'No se puede modificar un pago confirmado o facturado.']);
                }
                $estadoNuevo = EstadoPagos::findOrFail($validated['estado_pago_id']);
                if (! in_array(mb_strtolower(trim($estadoNuevo->nombre)), ['pendiente', 'fallido'], true)) {
                    throw ValidationException::withMessages(['estado_pago_id' => 'Solo se pueden registrar estados pendiente o fallido; el cobro se confirma al entregar.']);
                }
                if ((isset($validated['pedido_id']) && (int) $validated['pedido_id'] !== (int) $pago->pedido_id) ||
                    (isset($validated['metodo_pago_id']) && (int) $validated['metodo_pago_id'] !== (int) $pago->metodo_pago_id) ||
                    (isset($validated['monto']) && (int) round((float) $validated['monto'] * 100) !== (int) round((float) $pago->monto * 100))) {
                    throw ValidationException::withMessages(['pago' => 'No se puede modificar el pedido, método, monto o fecha del pago.']);
                }
                $cambios = ['estado_pago_id' => $estadoNuevo->id];
                if (array_key_exists('referencia', $validated)) {
                    $cambios['referencia'] = $validated['referencia'];
                }
                $pago->update($cambios);

                return $pago->load(['pedido', 'pedido.cliente' => fn ($q) => $q->withTrashed(), 'metodoPago', 'estadoPago']);
            });

            return response()->json([
                'message' => 'Pago actualizado correctamente.',
                'pago' => new PagoResource($pago),
            ], 200);
        } catch (ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Pago no encontrado.',
            ], 404);
        } catch (ValidationException $e) {

            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al actualizar el pago.',
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/pagos/{id}',
        summary: 'Eliminar un pago por ID',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pago a eliminar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pago eliminado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 409, description: 'Error de integridad referencial.'),
            new OA\Response(response: 422, description: 'ID no válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el pago.'),
        ]
    )]
    public function destroy($id)
    {
        try {

            // Verificar que el ID sea válido
            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del pago no es válido.',
                ], 422);
            }

            // Buscar el pago
            $pago = Pago::find($id);

            // Verificar si existe
            if (! $pago) {
                return response()->json([
                    'message' => 'Pago no encontrado.',
                ], 404);
            }

            DB::transaction(function () use ($pago): void {
                Pedido::lockForUpdate()->findOrFail($pago->pedido_id);
                $pago = Pago::with('estadoPago')->lockForUpdate()->findOrFail($pago->id);
                $estado = mb_strtolower(trim($pago->estadoPago?->nombre ?? ''));
                if (! in_array($estado, ['pendiente', 'fallido'], true) || $pago->comprobantes()->exists()) {
                    throw ValidationException::withMessages(['pago' => 'No se puede anular un pago confirmado o facturado.']);
                }
                $pago->delete();
            });

            // Respuesta exitosa
            return response()->json([
                'message' => 'Pago eliminado correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'No se pudo anular el pago.', 'errors' => $e->errors()], 422);
        } catch (QueryException $e) {

            // Error relacionado con la base de datos
            return response()->json([
                'message' => 'No se puede eliminar el pago porque está siendo utilizado por otros registros.',
            ], 409);
        } catch (\Exception $e) {

            // Error general
            return response()->json([
                'message' => 'Error interno al eliminar el pago.',
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/pagos-desactivados',
        summary: 'Listar todos los pagos desactivados',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Pagos desactivados obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No hay pagos desactivados para mostrar.'),
            new OA\Response(response: 500, description: 'Error interno del servidor al obtener pagos desactivados.'),
        ]
    )]
    public function indexDesactivados()
    {
        try {
            // llamamos lo inhabilitado
            $pagosDesactivados = Pago::onlyTrashed()->get();

            // Caso 404: NO hay nada inhabilitado -> retornamos mensaje.
            if ($pagosDesactivados->isEmpty()) {
                return response()->json([
                    'message' => 'No hay pagos desactivados para mostrar.',
                ], 404);
            }

            // Caso 200: retornamos lo desactivado
            return response()->json([
                'message' => 'Pagos desactivados obtenidos correctamente.',
                'pagosDesactivados' => PagoResource::collection($pagosDesactivados),
            ], 200);
        } catch (\Exception $e) {
            // Caso 500: Falla en el servidor -> retornamos mensaje.
            return response()->json([
                'message' => 'Error interno del servidor al obtener pagos desactivados.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/pagos/{id}/restore',
        summary: 'Restaurar un pago eliminado',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del pago a restaurar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pago restaurado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 409, description: 'El pago no está eliminado.'),
            new OA\Response(response: 422, description: 'El ID no es válido.'),
            new OA\Response(response: 500, description: 'Error interno al restaurar el pago.'),
        ]
    )]
    public function restore($id)
    {
        try {

            if (! is_numeric($id) || (int) $id <= 0) {
                return response()->json([
                    'message' => 'El ID del pago no es válido.',
                ], 422);
            }

            $pago = Pago::withTrashed()->find($id);

            if (! $pago) {
                return response()->json([
                    'message' => 'Pago no encontrado.',
                ], 404);
            }

            // Verificar que realmente esté eliminado
            if (! $pago->trashed()) {
                return response()->json([
                    'message' => 'El pago no está eliminado.',
                ], 409);
            }

            $pago = DB::transaction(function () use ($pago): Pago {
                $pedido = Pedido::with('estadoPedido')->lockForUpdate()->findOrFail($pago->pedido_id);
                $pago = Pago::onlyTrashed()->lockForUpdate()->findOrFail($pago->id);
                if (mb_strtolower(trim($pedido->estadoPedido?->nombre ?? '')) === 'cancelado') {
                    throw ValidationException::withMessages(['pedido' => 'No se puede restaurar un pago de un pedido cancelado.']);
                }
                if (Pago::where('pedido_id', $pago->pedido_id)->exists()) {
                    throw ValidationException::withMessages(['pedido' => 'El pedido ya tiene otro pago activo.']);
                }
                $pago->restore();

                return $pago;
            });

            return response()->json([
                'message' => 'Pago restaurado correctamente.',
                'pago' => new PagoResource($pago),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'No se pudo restaurar el pago.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error interno al restaurar el pago.',
            ], 500);
        }
    }
}
