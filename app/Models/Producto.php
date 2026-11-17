<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'categoria_id',
        'nombre',
        'descripcion',
        'imagen',
        'precio',
        'activo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function pedidoItems(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    /**
     * Scope para filtrar productos por categoria_id y termino de busqueda 'q'.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFiltrar($query, array $filters)
    {
        if (isset($filters['categoria_id']) && is_scalar($filters['categoria_id']) && trim((string) $filters['categoria_id']) !== '') {
            $query->where('categoria_id', $filters['categoria_id']);
        }

        if (isset($filters['q']) && is_string($filters['q'])) {
            $termino = trim($filters['q']);
            if ($termino !== '') {
                $query->where(function ($q) use ($termino) {
                    $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . mb_strtolower($termino) . '%'])
                      ->orWhereRaw('LOWER(descripcion) LIKE ?', ['%' . mb_strtolower($termino) . '%']);
                });
            }
        }

        return $query;
    }
}
