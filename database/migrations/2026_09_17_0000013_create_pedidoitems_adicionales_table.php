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
        Schema::create('pedidoitems_adicionales', function (Blueprint $table) {
            
            // Relacion con el pedido item
            $table->foreignId('pedido_item_id')
                ->constrained('pedido_items')
                ->cascadeOnDelete();

            // Relacion con el/los adicional/es
            $table->foreignId('adicional_id')
                ->constrained('adicionals')
                ->restrictOnDelete();

            $table->decimal('precio_adicional', 10, 2);
            $table->timestamps();

            $table->primary(['pedido_item_id', 'adicional_id']); // Clave primaria compuesta.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidoitems_adicionales');
    }
};
