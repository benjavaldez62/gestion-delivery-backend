<?php

namespace Database\Factories;

use App\Models\EstadoPagos;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pedido_id' => fn () => throw new \LogicException('La fábrica de pagos requiere un pedido explícito.'),
            'metodo_pago_id' => fn (array $atributos) => Pedido::findOrFail($atributos['pedido_id'])->metodo_pago_id,
            'monto' => fn (array $atributos) => Pedido::findOrFail($atributos['pedido_id'])->monto_total,
            'estado_pago_id' => fn () => EstadoPagos::whereRaw('LOWER(TRIM(nombre)) = ?', ['pendiente'])->firstOrFail()->id,
            'fecha_pago' => null,
        ];
    }
}
