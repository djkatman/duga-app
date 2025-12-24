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
                // release_date が「今日以前」
                ->whereDate('release_date', '<=', $now)
                // または release_date が NULL（未設定）
                ->orWhereNull('release_date');
            })
            // 並び順：release_date 優先 → created_at
            ->orderByRaw('release_date IS NULL')
            ->orderByDesc('release_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $channel = [
            'title'       => config('app.name', 'DUGA Feed'),
            'link'        => url('/'),
            'description' => '最新作品の更新情報',
            'language'    => 'ja',
            'lastBuildDate' => optional($items->first()?->updated_at)->toRssString() ?? now()->toRssString(),
        ];

        return response()
            ->view('feed.rss', [
                'channel' => $channel,
                'items'   => $items,
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
