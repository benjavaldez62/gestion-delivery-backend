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
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();

            // Relacion con el pago
            $table->foreignId('pago_id')
                ->constrained('pagos')
                ->restrictOnDelete();

            // Tipo de comprobante (Factura, Boleta, etc.)
            $table->string('tipo_comprobante', 50);
            $table->string('numero_comprobante')->unique();
            $table->string('titular');
            $table->string('url_pdf')->nullable(); 

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
