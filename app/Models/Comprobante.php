<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comprobante extends Model
{
    use HasFactory;

    protected $table = 'comprobantes';

    public const UPDATED_AT = null;

    protected $fillable = [
        'pago_id',
        'tipo_comprobante',
        'numero_comprobante',
        'titular',
        'url_pdf',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }
}
