<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Eliminamos la unicidad de la migración original en el campo 'nombre'
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropUnique(['nombre']);
        });

        // 2. Agregamos columna auxiliar
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('nombre_activo')->nullable()->after('nombre');
        });

        // 3. Actualizamos la columna auxiliar con los valores de 'nombre' para las categorías activas
        DB::statement("
            UPDATE categorias
            SET nombre_activo = CASE
                WHEN deleted_at IS NULL THEN nombre
                ELSE NULL
            END
        ");

        // 4) Creamos la unicidad solo para registros activos
        Schema::table('categorias', function (Blueprint $table) {
            $table->unique('nombre_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropUnique(['nombre_activo']);
            $table->dropColumn('nombre_activo');
        });

        Schema::table('categorias', function (Blueprint $table) {
            $table->string('nombre', 150)->unique();
        });
    }
};
