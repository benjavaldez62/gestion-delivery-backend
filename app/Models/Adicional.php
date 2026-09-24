<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Adicional extends Model
{
    use HasFactory;

    protected $table = 'adicionals';

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'activo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function pedidoItems(): BelongsToMany
    {
        return $this->belongsToMany(PedidoItem::class, 'pedidoitems_adicionales', 'adicional_id', 'pedido_item_id')
            ->withPivot('precio_adicional')
            ->withTimestamps();
    }
}
