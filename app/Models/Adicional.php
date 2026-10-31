<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Adicional extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'adicionals';

    protected $fillable = [
        'categoria_id',
        'nombre',
        'descripcion',
        'precio',
        'activo',
    ];

    protected $casts = [
        'categoria_id' => 'integer',
        'precio' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function pedidoItems(): BelongsToMany
    {
        return $this->belongsToMany(PedidoItem::class, 'pedidoitems_adicionales', 'adicional_id', 'pedido_item_id')
            ->withPivot('precio_adicional')
            ->withTimestamps();
    }
}
