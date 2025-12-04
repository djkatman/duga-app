<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;

class DugaSaleScraper
{
    /**
     * DUGA商品ページを解析してセール情報を抽出
     *
     * @param  string  $productUrl  例: https://duga.jp/ppv/mbm-0033/
     * @return array{
     *   is_sale: bool,
     *   label: string|null,
     *   until: ?\Carbon\Carbon,
     *   items: array<int, array{orig:int,now:int,rate:int}>
     * }
     */
    public function fetch(string $productUrl): array
    {
        return Cache::remember("duga:sale:" . md5($productUrl), now()->addHours(6), function () use ($productUrl) {
            $response = Http::timeout(8)->get($productUrl);
            if (!$response->ok()) {
                return ['is_sale' => false, 'label' => null, 'until' => null, 'items' => []];
            }

            $html = $response->body();
            $text = preg_replace('/\s+/u', ' ', strip_tags($html));

            $crawler = new Crawler($html);

            // 1) セールを示すキーワード
            $hasCampaign = preg_match('/(キャンペーン|期間限定セール|DUGA割)/u', $text) === 1;

            // 2) 終了日時
            $until = null;
            if (preg_match('/(\d{1,2})月(\d{1,2})日(\d{1,2})時(\d{2})分まで/u', $text, $m)) {
                $year = (int) now('Asia/Tokyo')->year;
                $until = Carbon::create($year, (int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4], 0, 'Asia/Tokyo');
                // 年跨ぎ補正
                if ($until->isPast()) {
                    $until->addYear();
                }
            }

            // 3) 「○円→○円（○%OFF）」パターン抽出
            $items = [];
            if (preg_match_all('/(\d[\d,]*)円\s*→\s*(\d[\d,]*)\s*円\s*（\s*(\d{1,2})％?%?OFF\s*）/u', $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $orig = (int) str_replace(',', '', $m[1]);
                    $now  = (int) str_replace(',', '', $m[2]);
                    $rate = (int) $m[3];
                    if ($orig > 0 && $now > 0 && $now < $orig) {
                        $items[] = compact('orig', 'now', 'rate');
                    }
                }
            }

            $isSale = $hasCampaign || !empty($items);
            $maxRate = !empty($items) ? max(array_column($items, 'rate')) : null;
            $label = $isSale
                ? ($maxRate ? "最大{$maxRate}%OFF" : 'セール中')
                : null;

            return [
                'is_sale' => $isSale,
                'label'   => $label,
                'until'   => $until,
                'items'   => $items,
            ];
        });
    }
}