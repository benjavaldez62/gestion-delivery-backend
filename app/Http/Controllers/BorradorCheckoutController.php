<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBorradorCheckoutRequest;
use App\Models\Adicional;
use App\Models\BorradorCheckout;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class BorradorCheckoutController extends Controller
{
    #[OA\Post(
        path: '/api/checkout/borradores',
        summary: 'Registrar un pre-pedido desde el checkout web y persistir el borrador',
        tags: ['Checkout'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre_cliente', 'direccion', 'telefono', 'metodo_pago', 'items'],
                properties: [
                    new OA\Property(property: 'nombre_cliente', type: 'string', maxLength: 25, example: 'Nombre Apellido'),
                    new OA\Property(property: 'direccion', type: 'string', maxLength: 25, example: 'Av. Eva Perón 1234'),
                    new OA\Property(property: 'telefono', description: 'Hasta 25 dígitos con + opcional al inicio.', type: 'string', maxLength: 26, minLength: 1, pattern: '^\\+?[0-9]{1,25}$', example: '+543454123456'),
                    new OA\Property(property: 'notas', type: 'string', maxLength: 255, nullable: true, example: 'Timbre 2'),
                    new OA\Property(property: 'metodo_pago', type: 'string', enum: ['efectivo'], example: 'efectivo'),
                    new OA\Property(property: 'monto_recibido', type: 'number', format: 'float', nullable: true, example: 5000),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            required: ['producto_id', 'cantidad', 'adicionales'],
                            properties: [
                                new OA\Property(property: 'producto_id', type: 'integer', example: 1),
                                new OA\Property(property: 'cantidad', type: 'integer', example: 2),
                                new OA\Property(
                                    property: 'adicionales',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [1, 2]
                                ),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pre-pedido y borrador de checkout registrados correctamente.'),
            new OA\Response(response: 422, description: 'Error de validación en los datos enviados o productos no disponibles.'),
            new OA\Response(response: 500, description: 'Error interno al procesar el checkout.'),
        ]
    )]
    public function store(StoreBorradorCheckoutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        [$borrador, $pedido] = DB::transaction(function () use ($validated) {
            $items = [];
            $subtotalCentavos = 0;

            foreach ($validated['items'] as $index => $item) {
                if (count($item['adicionales']) !== count(array_unique($item['adicionales']))) {
                    throw ValidationException::withMessages([
                        "items.$index.adicionales" => 'No se pueden repetir adicionales en un mismo ítem.',
                    ]);
                }

                $producto = Producto::where('activo', true)->find($item['producto_id']);
                if (! $producto) {
                    throw ValidationException::withMessages([
                        "items.$index.producto_id" => 'El producto ya no está disponible.',
                    ]);
                }

                $adicionales = Adicional::where('activo', true)->whereIn('id', $item['adicionales'])->get();
                if ($adicionales->count() !== count($item['adicionales'])) {
                    throw ValidationException::withMessages([
                        "items.$index.adicionales" => 'Uno o más adicionales ya no están disponibles.',
                    ]);
                }

                $precioCentavos = (int) round((float) $producto->precio * 100);
                $adicionalesDatos = [];
                foreach ($adicionales as $adicional) {
                    if ($adicional->categoria_id !== null && $adicional->categoria_id !== (int) $producto->categoria_id) {
                        throw ValidationException::withMessages([
                            "items.$index.adicionales" => 'Un adicional no corresponde a la categoría del producto.',
                        ]);
                    }
                    $precioCentavos += (int) round((float) $adicional->precio * 100);
                    $adicionalesDatos[] = [
                        'id' => $adicional->id,
                        'nombre' => $adicional->nombre,
                        'precio' => (float) $adicional->precio,
                    ];
                }

                $subtotalItem = $precioCentavos * (int) $item['cantidad'];
                $subtotalCentavos += $subtotalItem;
                $items[] = [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'precio_unitario' => (float) $producto->precio,
                    'cantidad' => (int) $item['cantidad'],
                    'adicionales' => $adicionalesDatos,
                    'subtotal' => $subtotalItem / 100,
                ];
            }

            $costoEnvio = 1500;
            $montoTotalCentavos = $subtotalCentavos + ($costoEnvio * 100);
            $montoRecibido = $validated['monto_recibido'] ?? null;
            if ($montoRecibido !== null && (int) round((float) $montoRecibido * 100) < $montoTotalCentavos) {
                throw ValidationException::withMessages([
                    'monto_recibido' => 'El monto recibido no alcanza para cubrir el total actualizado.',
                ]);
            }

            $direccion = trim($validated['direccion']);
            $nombreCliente = trim($validated['nombre_cliente']);
            $telefono = trim($validated['telefono']);
            $subtotal = $subtotalCentavos / 100;
            $montoTotal = ($subtotalCentavos / 100) + $costoEnvio;

            // Resolver o crear el cliente por teléfono
            $cliente = Cliente::where('telefono', $telefono)->first();
            if ($cliente) {
                $cliente->update(['direccion' => $direccion]);
            } else {
                $digitos = substr(preg_replace('/\D+/', '', $telefono) ?: (string) time(), 0, 20);
                $baseUsername = 'web_'.$digitos;
                $username = substr($baseUsername, 0, 30);
                $contador = 1;
                while (Cliente::withTrashed()->where('username', $username)->exists()) {
                    $sufijo = '_'.$contador++;
                    $username = substr($baseUsername, 0, 30 - strlen($sufijo)).$sufijo;
                }

                $cliente = Cliente::create([
                    'username' => $username,
                    'telefono' => $telefono,
                    'direccion' => $direccion,
                ]);
            }

            // Resolver o crear el método de pago
            $mapaMetodosPago = [
                'efectivo' => [
                    'nombres' => ['efectivo'],
                    'default' => 'Efectivo',
                    'descripcion' => 'Pago en efectivo al momento de la entrega',
                ],
                'transferencia' => [
                    'nombres' => ['transferencia', 'transferencia bancaria'],
                    'default' => 'Transferencia',
                    'descripcion' => 'Pago mediante transferencia bancaria a la cuenta del restaurante',
                ],
                'tarjeta' => [
                    'nombres' => ['tarjeta', 'tarjeta de débito', 'tarjeta de debito', 'tarjeta de crédito', 'tarjeta de credito'],
                    'default' => 'Tarjeta',
                    'descripcion' => 'Pago con tarjeta',
                ],
            ];

            $configMetodo = $mapaMetodosPago[$validated['metodo_pago']] ?? [
                'nombres' => [mb_strtolower(trim($validated['metodo_pago']))],
                'default' => ucfirst($validated['metodo_pago']),
                'descripcion' => 'Método de pago web',
            ];

            $metodoPago = MetodoPago::whereIn(DB::raw('LOWER(TRIM(nombre))'), $configMetodo['nombres'])->first()
                ?? MetodoPago::firstOrCreate(
                    ['nombre' => $configMetodo['default']],
                    ['descripcion' => $configMetodo['descripcion'], 'activo' => true]
                );

            // Resolver o crear el estado 'Pre-pedido'
            $estadoPrePedido = EstadoPedido::whereRaw("LOWER(TRIM(nombre)) = 'pre-pedido'")->first()
                ?? EstadoPedido::firstOrCreate(
                    ['nombre' => 'Pre-pedido'],
                    ['descripcion' => 'El pedido fue tomado desde la web y aguarda confirmación del cliente en Telegram']
                );

            // Crear el registro de Pedido en estado 'Pre-pedido'
            $pedido = Pedido::create([
                'cliente_id' => $cliente->id,
                'nombre_cliente' => $nombreCliente,
                'estado_pedidos_id' => $estadoPrePedido->id,
                'metodo_pago_id' => $metodoPago->id,
                'subtotal' => $subtotal,
                'monto_total' => $montoTotal,
                'cocinero_id' => null,
                'repartidor_id' => null,
            ]);

            // Crear cada PedidoItem y adjuntar sus adicionales en la tabla pivote
            foreach ($items as $itemData) {
                $pedidoItem = PedidoItem::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $itemData['producto_id'],
                    'cantidad' => $itemData['cantidad'],
                    'precio_unitario' => $itemData['precio_unitario'],
                    'subtotal' => $itemData['subtotal'],
                ]);

                if (! empty($itemData['adicionales'])) {
                    $attachAdicionales = [];
                    foreach ($itemData['adicionales'] as $adicionalData) {
                        $attachAdicionales[$adicionalData['id']] = [
                            'precio_adicional' => $adicionalData['precio'],
                        ];
                    }
                    $pedidoItem->adicionales()->attach($attachAdicionales);
                }
            }

            $borrador = BorradorCheckout::create(['datos' => [
                'pedido_id' => $pedido->id,
                'estado' => 'Pre-pedido',
                'nombre_cliente' => $nombreCliente,
                'direccion' => $direccion,
                'telefono' => $telefono,
                'notas' => trim($validated['notas'] ?? ''),
                'metodo_pago' => $validated['metodo_pago'],
                'monto_recibido' => $montoRecibido === null ? null : (float) $montoRecibido,
                'items' => $items,
                'subtotal' => $subtotal,
                'costo_envio' => $costoEnvio,
                'monto_total' => $montoTotal,
            ]]);

            return [$borrador, $pedido];
        });

        return response()->json([
            'message' => 'Pre-pedido registrado. Aguardando confirmación en Telegram.',
            'id' => $borrador->id,
            'pedido_id' => $pedido->id,
            'estado' => 'Pre-pedido',
            'subtotal' => $borrador->datos['subtotal'],
            'costo_envio' => $borrador->datos['costo_envio'],
            'monto_total' => $borrador->datos['monto_total'],
            'items' => $borrador->datos['items'],
        ], 201);
    }
}
