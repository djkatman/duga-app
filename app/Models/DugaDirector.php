<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DugaDirector extends Model
{
    protected $table = 'duga_directors';

    protected $guarded = [];

    /**
     * 監督が関わった作品（多対多）
     */
   public function products(){ return $this->belongsToMany(DugaProduct::class,'duga_director_product'); }
}