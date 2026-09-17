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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            $table->string('username', 30)->unique();
            $table->string('telefono', 30)->nullable();
            $table->string('direccion', 255)->nullable();
            //$table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
