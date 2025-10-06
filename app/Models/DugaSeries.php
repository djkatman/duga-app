<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DugaSeries extends Model
{
    protected $table = 'duga_series';

    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(DugaProduct::class, 'series_id');
    }
}