<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DugaLabel extends Model
{
    protected $table = 'duga_labels';

    protected $fillable = [
        'duga_id', 'name',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(DugaProduct::class, 'label_id');
    }
}