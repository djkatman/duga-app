<?php

namespace App\Http\Controllers;

use App\Models\DugaProduct;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class FeedController extends Controller
{
    public function index(): Response
    {

        $now = Carbon::today(); // 今日の00:00基準（必要なら now() に変更）

        $items = DugaProduct::query()
            ->where(function ($q) use ($now) {
                $q
                // open_date が「今日以前」
                ->whereDate('open_date', '<=', $now)
                // または open_date が NULL（未設定）
                ->orWhereNull('open_date');
            })
            // 並び順：open_date 優先 → created_at
            ->orderByRaw('open_date IS NULL')
            ->orderByDesc('open_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $channel = [
            'title'       => config('app.name', 'DUGA Feed'),
            'link'        => url('/'),
            'description' => '最新作品の更新情報',
            'language'    => 'ja',
            'lastBuildDate' => Carbon::now('Asia/Tokyo')->toRssString(),
        ];

        return response()
            ->view('feed.rss', [
                'channel' => $channel,
                'items'   => $items,
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')

            // ▼ キャッシュ完全禁止
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
