<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            // Venta asociada
            $table->foreignId('venta_id')
                ->constrained('ventas')
                ->cascadeOnDelete();

            // Método de pago
            $table->foreignId('metodo_pago_id')
                ->constrained('metodos_pago')
                ->restrictOnDelete();

            // Estado del pago
            $table->foreignId('estado_pago_id')
                ->constrained('estados_pago')
                ->restrictOnDelete();

            // Información del pago
            $table->decimal('importe', 12, 2);
            $table->dateTime('fecha');
            $table->string('referencia', 100)->nullable();

            $table->timestamps();

            // Índices
            $table->index('venta_id');
            $table->index('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
