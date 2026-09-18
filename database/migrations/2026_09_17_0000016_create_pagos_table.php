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
            
            // Relación con el pedido
            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->restrictOnDelete();
            
            // Relacion con el método de pago
            $table->foreignId('metodo_pago_id')
                ->constrained('metodos_pago')
                ->restrictOnDelete();
            
            // Monto a pagar
            $table->decimal('monto', 10, 2);
            
            // Fecha de pago
            $table->date('fecha_pago');

            // Relación con el estado del pago
            $table->foreignId('estado_pago_id')
                ->constrained('estado_pagos')
                ->restrictOnDelete();
            
            // Timestamps created_at y updated_at
            $table->timestamps();

            // Indices --para optimizar las consultas
            $table->index('fecha_pago');
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
