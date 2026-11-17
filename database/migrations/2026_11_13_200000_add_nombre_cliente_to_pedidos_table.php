<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('nombre_cliente', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('pedidos')->whereNotNull('nombre_cliente')->exists()) {
            throw new RuntimeException('No se puede revertir la migración mientras existan nombres registrados.');
        }

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('nombre_cliente');
        });
    }
};
