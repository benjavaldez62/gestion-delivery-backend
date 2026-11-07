<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBorradorCheckoutRequest;
use App\Models\Adicional;
use App\Models\BorradorCheckout;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BorradorCheckoutController extends Controller
{
    public function store(StoreBorradorCheckoutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $borrador = DB::transaction(function () use ($validated) {
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

            return BorradorCheckout::create(['datos' => [
                'direccion' => trim($validated['direccion']),
                'telefono' => trim($validated['telefono']),
                'notas' => trim($validated['notas'] ?? ''),
                'metodo_pago' => $validated['metodo_pago'],
                'items' => $items,
                'subtotal' => $subtotalCentavos / 100,
                'costo_envio' => $costoEnvio,
                'monto_total' => ($subtotalCentavos / 100) + $costoEnvio,
            ]]);
        });

        return response()->json([
            'message' => 'Borrador guardado. El pedido todavía no fue enviado.',
            'id' => $borrador->id,
            'subtotal' => $borrador->datos['subtotal'],
            'costo_envio' => $borrador->datos['costo_envio'],
            'monto_total' => $borrador->datos['monto_total'],
        ], 201);
    }
}
