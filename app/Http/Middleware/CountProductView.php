<?php
// app/Http/Middleware/CountProductView.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CountProductView {
  public function handle(Request $request, Closure $next) {
    $response = $next($request);

    $pid = (string)($request->route('id') ?? '');
    if ($pid === '') return $response;

    // 簡易Bot除外
    $ua = $request->userAgent() ?? '';
    if (preg_match('/bot|crawler|spider|preview|facebook|twitter|bing/i', $ua)) {
      return $response;
    }

    // 30分以内の同一ユーザー同一作品の重複を抑制
    $fp  = hash('sha256', ($request->ip() ?? '0.0.0.0') . '|' . substr($ua,0,120));
    $key = "pv:seen:{$pid}:{$fp}";
    if (Cache::add($key, 1, now()->addMinutes(30))) {
      DB::table('product_views')->insert([
        'productid' => $pid,
        'view_date' => now()->toDateString(),
        'created_at'=> now(),
        'fp'        => $fp,
      ]);
    }

    return $response;
  }
}