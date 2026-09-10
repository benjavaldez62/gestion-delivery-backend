<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoStock extends Model
{
    /** @use HasFactory<\Database\Factories\MovimientoStockFactory> */
    use HasFactory;

    protected $fillable = [
        'producto_id',
        'user_id',
        'tipo_movimiento_stock_id',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'motivo',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipoMovimientoStock(): BelongsTo
    {
        return $this->belongsTo(TipoMovimientoStock::class);
    }
}
