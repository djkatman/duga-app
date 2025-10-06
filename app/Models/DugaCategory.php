<?php

// app/Models/DugaCategory.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DugaCategory extends Model {
  protected $table = 'duga_categories';
  protected $guarded = [];
  public function products(){ return $this->belongsToMany(DugaProduct::class,'duga_category_product'); }
}