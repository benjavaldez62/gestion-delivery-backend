<?php

namespace App\Http\Controllers;

use App\Http\Resources\PedidoItemResource;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PedidoItemController extends Controller
{
    /**
     * Helper privado para verificar si el pedido está en un estado editable.
     */
    private function esPedidoEditable(Pedido $pedido): bool
    {
        $estadosNoEditables = ['entregado', 'cancelado', 'en_camino'];
        $estadoNombre = $pedido->estadoPedido?->nombre ?? '';

        return ! in_array(strtolower($estadoNombre), $estadosNoEditables);
    }

    /**
     * Helper privado para recalcular el monto_total del pedido padre.
     */
    private function recalcularMontoTotalPedido(Pedido $pedido): void
    {
        $nuevoTotal = $pedido->items()->sum('subtotal');
        $pedido->monto_total = $nuevoTotal;
        $pedido->save();
    }

    #[OA\Get(
        path: '/api/pedido-items',
        summary: 'Listar los ítems de un pedido puntual',
        tags: ['PedidoItems'],
        parameters: [
            new OA\Parameter(
                name: 'pedido_id',
                in: 'query',
                required: true,
                description: 'ID del pedido del cual listar los ítems',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ítems del pedido obtenidos correctamente.'),
            new OA\Response(response: 422, description: 'Parámetro pedido_id requerido o no válido.'),
            new OA\Response(response: 404, description: 'No se encontraron ítems para el pedido ingresado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function index(Request $request)
    {
        try {
            $pedidoId = $request->query('pedido_id');

            if (! $pedidoId || ! is_numeric($pedidoId) || (int) $pedidoId <= 0) {
                return response()->json([
                    'message' => 'El parámetro pedido_id es obligatorio y debe ser un entero positivo.',
                ], 422);
            }

            $pedidoItems = PedidoItem::with('producto')
                ->where('pedido_id', $pedidoId)
                ->get();

            if ($pedidoItems->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron ítems para el pedido especificado.',
                ], 404);
            }

            return response()->json([
                'message' => 'Ítems obtenidos correctamente.',
                'items' => PedidoItemResource::collection($pedidoItems),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener los ítems del pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/pedidos/{pedido}/items',
        summary: 'Agregar un nuevo producto a un pedido existente',
        tags: ['PedidoItems'],
        parameters: [
            new OA\Parameter(
                name: 'pedido',
                in: 'path',
                required: true,
                description: 'ID del pedido al que se agregará el ítem',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['producto_id', 'cantidad'],
                properties: [
                    new OA\Property(property: 'producto_id', type: 'integer', example: 5),
                    new OA\Property(property: 'cantidad', type: 'integer', example: 2),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ítem agregado correctamente al pedido.'),
            new OA\Response(response: 400, description: 'El pedido no está en un estado editable.'),
            new OA\Response(response: 404, description: 'Pedido o producto no encontrado.'),
            new OA\Response(response: 422, description: 'Datos de entrada no válidos.'),
            new OA\Response(response: 500, description: 'Error interno al agregar el ítem.'),
        ]
    )]
    public function store(Request $request, $pedido)
    {
        try {
            $pedidoModel = Pedido::findOrFail($pedido);

            if (! $this->esPedidoEditable($pedidoModel)) {
                return response()->json([
                    'message' => 'No se pueden agregar ítems a un pedido que ya no está en estado editable.',
                ], 400);
            }

            $validated = $request->validate([
                'producto_id' => 'required|integer|exists:productos,id',
                'cantidad' => 'required|integer|min:1',
            ], [
                'producto_id.required' => 'El producto es obligatorio.',
                'producto_id.exists' => 'El producto seleccionado no existe.',
                'cantidad.required' => 'La cantidad es obligatoria.',
                'cantidad.integer' => 'La cantidad debe ser un número entero.',
                'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            ]);

            $producto = Producto::findOrFail($validated['producto_id']);
            $precioUnitario = $producto->precio;
            $subtotal = $precioUnitario * $validated['cantidad'];

            $pedidoItem = DB::transaction(function () use ($pedidoModel, $validated, $precioUnitario, $subtotal) {
                $item = new PedidoItem;
                $item->pedido_id = $pedidoModel->id;
                $item->producto_id = $validated['producto_id'];
                $item->cantidad = $validated['cantidad'];
                $item->precio_unitario = $precioUnitario;
                $item->subtotal = $subtotal;
                $item->save();

                $this->recalcularMontoTotalPedido($pedidoModel);

                return $item;
            });

            return response()->json([
                'message' => 'Ítem agregado al pedido correctamente.',
                'item' => new PedidoItemResource($pedidoItem->load('producto')),
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'El pedido o producto especificado no existe.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Datos de entrada no válidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al agregar el ítem al pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/pedido-items/{pedidoItem}',
        summary: 'Ver el detalle de un ítem por ID',
        tags: ['PedidoItems'],
        parameters: [
            new OA\Parameter(
                name: 'pedidoItem',
                in: 'path',
                required: true,
                description: 'ID del ítem del pedido',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ítem obtenido correctamente.'),
            new OA\Response(response: 404, description: 'Ítem no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al obtener el ítem.'),
        ]
    )]
    public function show($pedidoItem)
    {
        try {
            $item = PedidoItem::with(['producto', 'pedido', 'adicionales'])->findOrFail($pedidoItem);

            return response()->json([
                'item' => new PedidoItemResource($item),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ítem de pedido no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al obtener el ítem.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/pedido-items/{pedidoItem}',
        summary: 'Modificar la cantidad de un ítem existente',
        tags: ['PedidoItems'],
        parameters: [
            new OA\Parameter(
                name: 'pedidoItem',
                in: 'path',
                required: true,
                description: 'ID del ítem a actualizar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cantidad'],
                properties: [
                    new OA\Property(property: 'cantidad', type: 'integer', example: 3),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ítem actualizado correctamente.'),
            new OA\Response(response: 400, description: 'El pedido no está en un estado editable.'),
            new OA\Response(response: 404, description: 'Ítem no encontrado.'),
            new OA\Response(response: 422, description: 'Datos de entrada no válidos.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el ítem.'),
        ]
    )]
    public function update(Request $request, $pedidoItem)
    {
        try {
            $item = PedidoItem::with('pedido')->findOrFail($pedidoItem);
            $pedido = $item->pedido;

            if (! $this->esPedidoEditable($pedido)) {
                return response()->json([
                    'message' => 'No se puede modificar un ítem de un pedido que no está en estado editable.',
                ], 400);
            }

            $validated = $request->validate([
                'cantidad' => 'required|integer|min:1',
            ], [
                'cantidad.required' => 'La cantidad es obligatoria.',
                'cantidad.integer' => 'La cantidad debe ser un entero.',
                'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            ]);

            DB::transaction(function () use ($item, $pedido, $validated) {
                $item->cantidad = $validated['cantidad'];
                $item->subtotal = $item->precio_unitario * $validated['cantidad'];
                $item->save();

                $this->recalcularMontoTotalPedido($pedido);
            });

            return response()->json([
                'message' => 'Ítem actualizado correctamente.',
                'item' => new PedidoItemResource($item->fresh('producto')),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ítem de pedido no encontrado.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Datos de entrada no válidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al actualizar el ítem.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/pedido-items/{pedidoItem}',
        summary: 'Quitar un ítem del pedido',
        tags: ['PedidoItems'],
        parameters: [
            new OA\Parameter(
                name: 'pedidoItem',
                in: 'path',
                required: true,
                description: 'ID del ítem a eliminar',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ítem eliminado correctamente.'),
            new OA\Response(response: 400, description: 'Estado del pedido no editable o el pedido no puede quedar sin ítems.'),
            new OA\Response(response: 404, description: 'Ítem no encontrado.'),
            new OA\Response(response: 422, description: 'ID no válido.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el ítem.'),
        ]
    )]
    public function destroy($pedidoItem)
    {
        try {
            if (! is_numeric($pedidoItem) || (int) $pedidoItem <= 0) {
                return response()->json([
                    'message' => 'El ID del ítem no es válido.',
                ], 422);
            }

            $item = PedidoItem::with('pedido')->find($pedidoItem);

            if (! $item) {
                return response()->json([
                    'message' => 'Ítem de pedido no encontrado.',
                ], 404);
            }

            $pedido = $item->pedido;

            if (! $this->esPedidoEditable($pedido)) {
                return response()->json([
                    'message' => 'No se puede eliminar un ítem de un pedido que no está en estado editable.',
                ], 400);
            }

            $totalItems = $pedido->items()->count();
            if ($totalItems <= 1) {
                return response()->json([
                    'message' => 'No se puede eliminar el ítem. Un pedido no puede quedar sin ítems.',
                ], 400);
            }

            DB::transaction(function () use ($item, $pedido) {
                $item->delete();
                $this->recalcularMontoTotalPedido($pedido);
            });

            return response()->json([
                'message' => 'Ítem eliminado del pedido correctamente.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno al eliminar el ítem.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/pedido-items-desactivados',
        summary: 'Listar todos los items de pedido desactivados',
        tags: ['PedidoItems'],
        responses: [
            new OA\Response(response: 200, description: 'Items de pedido desactivados obtenidos correctamente.'),
            new OA\Response(response: 404, description: 'No hay items de pedido desactivados para mostrar.'),
            new OA\Response(response: 500, description: 'Error interno del servidor al obtener items de pedido desactivados.')
        ]
    )]
    public function indexDesactivados() {
        try {
            // llamamos lo inhabilitado
            $pedidoItemsDesacivados = PedidoItem::onlyTrashed()->get();

            // Caso 404: NO hay nada inhabilitado -> retornamos mensaje.
            if ($pedidoItemsDesacivados->isEmpty()) {
                return response()->json([
                    'message' => 'No hay items de pedido desactivados para mostrar.'
                ], 404);
            }

            // Caso 200: retornamos lo desactivado
            return response()->json([
                'message' => 'Items de pedido desactivados obtenidos correctamente',
                'pedidoItemsDesactivados' => PedidoItemResource::collection($pedidoItemsDesacivados)
            ], 200);
        } catch (\Exception $e){
            // Caso 500: Falla en el servidor -> retornamos mensaje.
            return response()->json([
                'message' => 'Error interno del servidor al obtener items de pedido desactivados',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
