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
        $tables = [
            'roles',
            'metodos_pago',
            'estado_pagos',
            'pagos',
            'comprobantes',
            'adicionals',
            'pedidos',
            'pedido_items',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $tableBlueprint) {
                $tableBlueprint->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'roles',
            'metodos_pago',
            'estado_pagos',
            'pagos',
            'comprobantes',
            'adicionals',
            'pedidos',
            'pedido_items',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $tableBlueprint) {
                $tableBlueprint->dropSoftDeletes();
            });
        }
    }
};
