<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DugaSaleType extends Model
{
    protected $table = 'duga_product_sale_types';

    protected $guarded = [];

    protected $casts = [
        'duga_product_id' => 'int',
        'price'           => 'int',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(DugaProduct::class, 'duga_product_id');
    }
}
