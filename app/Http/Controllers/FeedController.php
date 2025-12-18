<?php

namespace App\Http\Controllers;

use App\Models\DugaProduct;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class FeedController extends Controller
{
    public function index(): Response
    {
        // 好みで基準を変更：release_date優先 → 無ければcreated_at
        $items = DugaProduct::query()
            ->orderByRaw('release_date IS NULL') // release_dateがあるものを先に
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
