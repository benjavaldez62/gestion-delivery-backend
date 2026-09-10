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
        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();

            // Producto afectado
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            // Usuario que realizó el movimiento
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Tipo de movimiento
            $table->foreignId('tipo_movimiento_stock_id')
                ->constrained('tipos_movimiento_stock')
                ->restrictOnDelete();

            // Cantidad del movimiento
            $table->unsignedInteger('cantidad');

            // Stock antes y después del movimiento
            $table->unsignedInteger('stock_anterior');
            $table->unsignedInteger('stock_nuevo');

            // Motivo del movimiento
            $table->string('motivo', 255)->nullable();

            $table->timestamps();

            // Índices
           
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_stocks');
    }
};
