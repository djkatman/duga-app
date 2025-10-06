<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DugaThumbnail extends Model
{
    protected $table = 'duga_product_thumbnails';

    protected $guarded = [];

    protected $casts = [
        'duga_product_id' => 'int',
        'sort'            => 'int',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(DugaProduct::class, 'duga_product_id');
    }
}