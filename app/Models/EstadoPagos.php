<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoPagos extends Model
{
    use HasFactory;

    protected $table = 'estado_pagos';

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'estado_pago_id');
    }
}
