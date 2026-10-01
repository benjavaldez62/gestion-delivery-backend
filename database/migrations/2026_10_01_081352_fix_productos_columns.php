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
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'precio_unitario') && ! Schema::hasColumn('productos', 'precio')) {
                $table->renameColumn('precio_unitario', 'precio');
            } elseif (! Schema::hasColumn('productos', 'precio')) {
                $table->decimal('precio', 10, 2)->after('imagen');
            }

            if (Schema::hasColumn('productos', 'stock')) {
                $table->dropColumn('stock');
            }

            if (Schema::hasColumn('productos', 'stock_minimo')) {
                $table->dropColumn('stock_minimo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'precio') && ! Schema::hasColumn('productos', 'precio_unitario')) {
                $table->renameColumn('precio', 'precio_unitario');
            }

            if (! Schema::hasColumn('productos', 'stock')) {
                $table->unsignedInteger('stock')->default(0);
            }

            if (! Schema::hasColumn('productos', 'stock_minimo')) {
                $table->unsignedInteger('stock_minimo')->default(0);
            }
        });
    }
};
