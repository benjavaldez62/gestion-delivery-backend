<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->date('fecha_pago')->nullable()->change();
        });
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->longText('contenido_ticket')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('pagos')->whereNull('fecha_pago')->exists()) {
            throw new RuntimeException('No se puede revertir la migración mientras existan pagos sin fecha.');
        }

        if (DB::table('comprobantes')->whereNotNull('contenido_ticket')->exists()) {
            throw new RuntimeException('No se puede revertir la migración mientras existan tickets registrados.');
        }

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropColumn('contenido_ticket');
        });
        Schema::table('pagos', function (Blueprint $table) {
            $table->date('fecha_pago')->nullable(false)->change();
        });
    }
};
