<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BorradorCheckout extends Model
{
    use HasUuids;

    protected $table = 'borradores_checkout';

    protected $fillable = ['datos'];

    protected function casts(): array
    {
        return ['datos' => 'array'];
    }
}
