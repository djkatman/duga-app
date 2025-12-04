<?php

// app/Services/PopularViewService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PopularViewService
{
  public function topByViews(int $days = 7, int $limit = 10): array {
    $key = "top:views:{$days}:{$limit}";
    return Cache::remember($key, now()->addMinutes(10), function () use ($days,$limit) {
      $from = Carbon::today()->subDays($days-1)->toDateString(); // 当日含む
      return DB::table('product_views')
        ->select('productid', DB::raw('COUNT(*) as views'))
        ->where('view_date', '>=', $from)
        ->groupBy('productid')
        ->orderByDesc('views')
        ->limit($limit)
        ->get()
        ->map(fn($r)=>['productid'=>$r->productid,'views'=>(int)$r->views])
        ->toArray();
    });
  }
}