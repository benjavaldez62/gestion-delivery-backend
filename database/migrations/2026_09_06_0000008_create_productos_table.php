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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();

            // Información del producto
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('imagen')->nullable();
            
            // Precios
            $table->decimal('precio', 10, 2);
     

            // Estado
            $table->boolean('activo')->default(true);

            // Relación con categorías
            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();
            // Índices
            $table->index('nombre');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
