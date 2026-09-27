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
        Schema::table('productos', function (Blueprint $table) {
            $table->string('nombre_activo', 150)->nullable()->after('nombre');
        });

        DB::statement('UPDATE productos SET nombre_activo = CASE WHEN deleted_at IS NULL AND activo = 1 THEN nombre ELSE NULL END');

        Schema::table('productos', function (Blueprint $table) {
            $table->unique('nombre_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique(['nombre_activo']);
            $table->dropColumn('nombre_activo');
        });
    }
};
