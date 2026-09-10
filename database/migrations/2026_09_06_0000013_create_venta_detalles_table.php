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
        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();

            // Venta a la que pertenece el detalle
            $table->foreignId('venta_id')
                ->constrained('ventas')
                ->cascadeOnDelete();

            // Producto vendido
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            // Datos de la venta
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);

            $table->timestamps();

            // Índices
            $table->index('venta_id');
            $table->index('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
    }
};
