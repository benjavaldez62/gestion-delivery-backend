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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();

            // Cliente que realiza la compra
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();

            // Usuario que registra la venta
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Información de la venta
            $table->dateTime('fecha');

            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            // Estado de la venta
            $table->foreignId('estado_venta_id')
                ->constrained('estados_venta')
                ->restrictOnDelete();

            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Índices
            $table->index('fecha');          
            $table->index(['cliente_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
