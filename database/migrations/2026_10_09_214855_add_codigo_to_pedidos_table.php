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
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('metodo_pago_id')
                ->nullable()
                ->after('monto_total')
                ->constrained('metodos_pago')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['metodo_pago_id']);
            $table->dropColumn('metodo_pago_id');
        });
    }
};
