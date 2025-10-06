<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DugaSample extends Model
{
    protected $table = 'duga_product_samples';

    protected $guarded = [];

    protected $casts = [
        'duga_product_id' => 'int',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(DugaProduct::class, 'duga_product_id');
    }
}