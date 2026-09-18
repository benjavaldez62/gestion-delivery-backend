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
        Schema::create('pedido_items', function (Blueprint $table) {
            $table->id();
            
            // Relacion con el pedido
            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete(); // si se borra el pedido, se borran los items asociados
            
            // Relacion con el producto
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete(); // no permitir borrar un producto si tiene items históricos
            
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedido_items');
    }
};
