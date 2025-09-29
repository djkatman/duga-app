<?php
// app/Http/Controllers/SitemapController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;

class SitemapController extends Controller
{
    // 取り込む商品のページ数（1ページ=hits件）
    private int $productPages = 5;   // 例: 5ページ分
    private int $hitsPerPage  = 60;  // DUGA API の1ページ件数

    public function index(Request $request)
    {
        // nocache=1 で強制再生成できるように
        $noCache = (bool) $request->boolean('nocache', false);

        $xml = $noCache ? null : Cache::get('sitemap:xml:v1');

        if (!$xml) {
            $urls = [];

            // 1) 固定ページ（必要に応じて増やす）
            $urls[] = [
                'loc'        => route('home'),
                'changefreq' => 'hourly',
                'priority'   => '1.0',
                'lastmod'    => now()->toAtomString(),
            ];
            // 人気順/新着順のトップ1ページ（任意）
            $urls[] = [
                'loc'        => route('home', ['sort' => 'favorite']),
                'changefreq' => 'hourly',
                'priority'   => '0.9',
                'lastmod'    => now()->toAtomString(),
            ];
            $urls[] = [
                'loc'        => route('home', ['sort' => 'new']),
                'changefreq' => 'hourly',
                'priority'   => '0.9',
                'lastmod'    => now()->toAtomString(),
            ];

            // 2) 商品詳細（最新 N ページ分をAPIから取得）
            //    new（新着）をベースに拾うのが自然。favorite にしたい場合は sort を変更。
            $sort = 'new';
            for ($page = 1; $page <= $this->productPages; $page++) {
                $items = $this->fetchItemsFromDuga($sort, $this->hitsPerPage, $page);

                foreach ($items as $row) {
                    $pid = Arr::get($row, 'productid');
                    if (!$pid) continue;

                    // release/open 日付を lastmod に利用（なければ今日）
                    $lastmod = Arr::get($row, 'releasedate')
                             ?: Arr::get($row, 'opendate')
                             ?: now()->toDateString();

                    $urls[] = [
                        'loc'        => route('products.show', ['id' => $pid]),
                        'changefreq' => 'weekly',
                        'priority'   => '0.8',
                        'lastmod'    => \Illuminate\Support\Carbon::parse($lastmod)->toAtomString(),
                    ];
                }
            }

            // 3) URL を XML に整形（urlset）
            $xml = $this->buildUrlset($urls);

            // 12時間キャッシュ
            Cache::put('sitemap:xml:v1', $xml, now()->addHours(12));
        }

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** DUGA API 呼び出し→ items（行配列）を返す */
    private function fetchItemsFromDuga(string $sort, int $hits, int $page): array
    {
        $offset = ($page - 1) * $hits + 1;

        $resp = Http::timeout(10)->retry(2, 200)->get('https://affapi.duga.jp/search', [
            'appid'    => env('APEX_APP_ID'),
            'agentid'  => env('APEX_AGENT_ID'),
            'version'  => env('APEX_API_VERSION', '1.2'),
            'format'   => env('APEX_FORMAT', 'json'),
            'adult'    => env('APEX_ADULT', 1),
            'bannerid' => env('APEX_BANNER_ID'),
            'sort'     => $sort,
            'hits'     => $hits,
            'offset'   => $offset,
        ]);

        if ($resp->failed()) return [];

        $data = $resp->json();
        if (!is_array($data)) return [];

        return $this->extractItems($data);
    }

    /** API の items 形を正規化（DugaApiController と同じロジック） */
    private function extractItems(array $data): array
    {
        $items = Arr::get($data, 'items', []);

        if (is_array($items) && isset($items['item']) && is_array($items['item'])) {
            $items = $items['item'];
        }
        if (is_array($items) && !empty($items) && isset($items[0]) && is_array($items[0]) && !Arr::isAssoc($items[0])) {
            $items = $items[0];
        }
        if (is_array($items) && !empty($items) && isset($items[0]) && is_array($items[0]) && Arr::isAssoc($items[0])) {
            return $items;
        }
        if (empty($items) && isset($data[0]) && is_array($data[0]) && Arr::isAssoc($data[0])) {
            return $data;
        }
        return is_array($items) ? $items : [];
    }

    /** urlset を生成 */
    private function buildUrlset(array $urls): string
    {
        $escape = fn($v) => htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $loc        = $escape($u['loc']);
            $lastmod    = $escape($u['lastmod'] ?? now()->toAtomString());
            $changefreq = $escape($u['changefreq'] ?? 'weekly');
            $priority   = $escape($u['priority'] ?? '0.5');

            $lines[] = '  <url>';
            $lines[] = "    <loc>{$loc}</loc>";
            $lines[] = "    <lastmod>{$lastmod}</lastmod>";
            $lines[] = "    <changefreq>{$changefreq}</changefreq>";
            $lines[] = "    <priority>{$priority}</priority>";
            $lines[] = '  </url>';
        }
        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }
}
