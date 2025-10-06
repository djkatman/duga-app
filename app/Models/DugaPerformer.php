<?php
// app/Models/DugaPerformer.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DugaPerformer extends Model {
  protected $table = 'duga_performers';
  protected $guarded = [];
  public function products(){ return $this->belongsToMany(DugaProduct::class,'duga_performer_product'); }
}