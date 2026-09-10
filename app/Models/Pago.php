<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    /** @use HasFactory<\Database\Factories\PagoFactory> */
    use HasFactory;

    protected $fillable = [
        'venta_id',
        'metodo_pago_id',
        'estado_pago_id',
        'importe',
        'fecha',
        'referencia',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function estadoPago(): BelongsTo
    {
        return $this->belongsTo(EstadoPago::class);
    }
}
