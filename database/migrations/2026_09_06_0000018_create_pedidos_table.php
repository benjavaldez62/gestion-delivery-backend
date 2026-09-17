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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            // información del pedido
            $table->decimal('subtotal', 10, 2)->nullable(); //subtotal SIN repartidor ??
            $table->decimal('monto_total', 10, 2);

            // Relación con el cliente
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            
            // Relación con el cocinero
            $table->foreignId('cocinero_id')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            
            // Relación con el repartidor
            $table->foreignId('repartidor_id')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            
            
            // estado del pedido
            $table->foreignId('estado_id')
                ->constrained('estados')
                ->restrictOnDelete();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
