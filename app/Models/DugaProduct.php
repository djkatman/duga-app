<?php
// app/Models/DugaProduct.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DugaProduct extends Model {
  protected $table = 'duga_products';
  protected $guarded = [];
  protected $casts = [
        'release_date'  => 'date',
        'open_date'     => 'date',
        'synced_at'     => 'datetime',
        'price'         => 'int',
        'volume'        => 'int',
        'ranking_total' => 'int',
        'mylist_total'  => 'int',
        'review_rating' => 'float',
        'review_count'  => 'int',
    ];

  // --- belongsTo ---
    public function label(): BelongsTo
    {
        return $this->belongsTo(DugaLabel::class, 'label_id');
    }
    public function series(): BelongsTo
    {
        return $this->belongsTo(DugaSeries::class, 'series_id');
    }

    // --- belongsToMany（ピボットとFKを明示）---
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            DugaCategory::class,
            'duga_category_product',
            'duga_product_id',
            'duga_category_id'
        );
    }
    public function performers(): BelongsToMany
    {
        return $this->belongsToMany(
            DugaPerformer::class,
            'duga_performer_product',
            'duga_product_id',
            'duga_performer_id'
        );
    }
    public function directors(): BelongsToMany
    {
        return $this->belongsToMany(
            DugaDirector::class,
            'duga_director_product',
            'duga_product_id',
            'duga_director_id'
        );
    }

    // --- hasMany ---
    public function samples(): HasMany
    {
        // テーブル: duga_product_samples
        return $this->hasMany(DugaSample::class, 'duga_product_id');
    }
    public function thumbnails(): HasMany
    {
        // テーブル: duga_product_thumbnails
        return $this->hasMany(DugaThumbnail::class, 'duga_product_id')
                    ->orderBy('sort_order');
    }
    public function saleTypes(): HasMany
    {
        // テーブル: duga_product_sale_types
        return $this->hasMany(DugaSaleType::class, 'duga_product_id');
    }
}