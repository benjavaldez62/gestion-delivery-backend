<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoMovimientoStock extends Model
{
    /** @use HasFactory<\Database\Factories\TipoMovimientoStockFactory> */
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function movimientosStock(): HasMany
    {
        return $this->hasMany(MovimientoStock::class);
    }
}
