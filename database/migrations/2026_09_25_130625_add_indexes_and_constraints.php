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
        // Índices en campos de estado y booleanos para búsquedas frecuentes
        
        Schema::table('users', function (Blueprint $table) {
            $table->index('activo');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->index('categoria_id');
        });

        Schema::table('categorias', function (Blueprint $table) {
            $table->index('activo');
        });

        Schema::table('adicionals', function (Blueprint $table) {
            $table->index('activo');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->index('estado_pedidos_id');
            $table->index('created_at');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->index('estado_pago_id');
        });

        Schema::table('clientes', function (Blueprint $table) {
            // clientes ya tiene índice en username
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['activo']);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['categoria_id']);
        });

        Schema::table('categorias', function (Blueprint $table) {
            $table->dropIndex(['activo']);
        });

        Schema::table('adicionals', function (Blueprint $table) {
            $table->dropIndex(['activo']);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['estado_pedidos_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex(['estado_pago_id']);
        });
    }
};
