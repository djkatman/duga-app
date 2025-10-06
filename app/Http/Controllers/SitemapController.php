<?php
// app/Http/Controllers/SitemapController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use App\Models\DugaProduct;

class SitemapController extends Controller
{
    /** ===== 調整ポイント（必要に応じて変更） ===== */
    // 商品サイトマップの「1ファイルあたりの件数」
    private int $productsPerFile = 1200;

    // 一覧の sort とページ数
    private array $sortsForLists = ['favorite','new','release','price','rating','mylist'];
    private int   $listPagesPerSort = 10; // 各 sort の 1〜10 ページをサイトマップに載せる

    /**
     * /sitemap.xml : サイトマップインデックス
     */
    public function index(Request $request)
    {
        $noCache  = (bool)$request->boolean('nocache', false);
        $cacheKey = 'sitemap:index:v2-db';
        $xml      = $noCache ? null : Cache::get($cacheKey);

        if (!$xml) {
            $entries = [];

            // 1) トップ/一覧（固定1ファイル）
            $entries[] = [
                'loc'     => route('sitemap.lists'),
                'lastmod' => now()->toAtomString(),
            ];

            // 2) 商品サイトマップ（DB件数から自動で分割数を算出）
            $totalProducts = DugaProduct::count();
            $files = max(1, (int) ceil($totalProducts / $this->productsPerFile));

            for ($i = 1; $i <= $files; $i++) {
                $entries[] = [
                    'loc'     => route('sitemap.products', ['n' => $i]),
                    'lastmod' => now()->toAtomString(),
                ];
            }

            $xml = $this->buildSitemapIndex($entries);
            Cache::put($cacheKey, $xml, now()->addHours(12));
        }

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * /sitemap-lists.xml : トップ＆一覧（sort × page）
     */
    public function lists(Request $request)
    {
        $noCache  = (bool)$request->boolean('nocache', false);
        $cacheKey = 'sitemap:lists:v2-db';
        $xml      = $noCache ? null : Cache::get($cacheKey);

        if (!$xml) {
            $urls = [];

            // トップ
            $urls[] = $this->urlRow(route('home'), 'hourly', '1.0', now()->toAtomString());

            // sort つき 1ページ目（便利リンク）
            foreach ($this->sortsForLists as $sort) {
                $urls[] = $this->urlRow(route('home', ['sort' => $sort]), 'hourly', '0.9', now()->toAtomString());
            }

            // 各 sort の複数ページ（?page=1..N）
            foreach ($this->sortsForLists as $sort) {
                for ($p = 1; $p <= $this->listPagesPerSort; $p++) {
                    $urls[] = $this->urlRow(
                        route('home', ['sort' => $sort, 'page' => $p]),
                        'daily',
                        '0.8',
                        now()->toAtomString()
                    );
                }
            }

            $xml = $this->buildUrlset($urls);
            Cache::put($cacheKey, $xml, now()->addHours(12));
        }

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * /sitemap-products-{n}.xml : 商品詳細（分割ファイル）
     * n は 1 から index が示す件数まで
     */
    public function products(Request $request, int $n)
    {
        // index と同じロジックで実ファイル数を算出してガード
        $totalProducts = DugaProduct::count();
        $files = max(1, (int) ceil($totalProducts / $this->productsPerFile));
        abort_unless($n >= 1 && $n <= $files, 404);

        $noCache  = (bool)$request->boolean('nocache', false);
        $cacheKey = "sitemap:products:v2-db:part:{$n}";
        $xml      = $noCache ? null : Cache::get($cacheKey);

        if (!$xml) {
            $urls = [];

            // 並び順は「新しい順」を想定
            // release_date desc → open_date desc → id desc の擬似ソート
            $query = DugaProduct::query()
                ->orderByRaw('CASE WHEN release_date IS NULL THEN 1 ELSE 0 END, release_date DESC')
                ->orderByRaw('CASE WHEN open_date IS NULL THEN 1 ELSE 0 END, open_date DESC')
                ->orderByDesc('id');

            $offset = ($n - 1) * $this->productsPerFile;
            $products = $query->skip($offset)->take($this->productsPerFile)->get([
                'productid','release_date','open_date','updated_at'
            ]);

            foreach ($products as $p) {
                $last = $p->release_date ?? $p->open_date ?? $p->updated_at ?? now();
                if (!$last instanceof Carbon) {
                    $last = Carbon::parse((string)$last);
                }
                $urls[] = $this->urlRow(
                    route('products.show', ['id' => $p->productid]),
                    'weekly',
                    '0.8',
                    $last->toAtomString()
                );
            }

            $xml = $this->buildUrlset($urls);
            Cache::put($cacheKey, $xml, now()->addHours(12));
        }

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** ===== 共通ユーティリティ ===== */

    private function urlRow(string $loc, string $changefreq = 'weekly', string $priority = '0.5', ?string $lastmod = null): array
    {
        return [
            'loc'        => $loc,
            'changefreq' => $changefreq,
            'priority'   => $priority,
            'lastmod'    => $lastmod ?: now()->toAtomString(),
        ];
    }

    private function buildUrlset(array $urls): string
    {
        // loc をキーに重複除去
        $uniq = [];
        foreach ($urls as $u) {
            if (!empty($u['loc'])) {
                $uniq[$u['loc']] = $u;
            }
        }
        $urls = array_values($uniq);

        $esc = fn($v) => htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $loc        = $esc($u['loc']);
            $lastmod    = $esc($u['lastmod'] ?? now()->toAtomString());
            $changefreq = $esc($u['changefreq'] ?? 'weekly');
            $priority   = $esc($u['priority'] ?? '0.5');

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

    private function buildSitemapIndex(array $entries): string
    {
        $esc = fn($v) => htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($entries as $e) {
            $loc     = $esc($e['loc']);
            $lastmod = $esc($e['lastmod'] ?? now()->toAtomString());
            $lines[] = '  <sitemap>';
            $lines[] = "    <loc>{$loc}</loc>";
            $lines[] = "    <lastmod>{$lastmod}</lastmod>";
            $lines[] = '  </sitemap>';
        }
        $lines[] = '</sitemapindex>';

        return implode("\n", $lines);
    }
}