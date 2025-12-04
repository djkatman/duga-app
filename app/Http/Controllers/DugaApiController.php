<?php
// app/Http/Controllers/DugaApiController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Utils\ConvertObject;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\DugaIngestService;
use App\Services\PopularViewService;
use App\Models\DugaProduct;
use App\ViewModels\DugaProductView;

use Throwable;

class DugaApiController extends Controller
{

    public function __construct(private DugaIngestService $ingest) {}

    public function index(Request $request)
    {
        $page     = max(1, (int) $request->integer('page', 1));
        $perPage  = max(1, min(100, (int) $request->integer('per_page', 60)));
        $sort     = $request->input('sort', 'favorite');
        $offset   = ($page - 1) * $perPage + 1;

        // nocache=1 で一時的にキャッシュ無効化可能
        $noCache  = (bool) $request->boolean('nocache', false);

        $cacheKey = sprintf("duga:index:v2:sort:%s:page:%d:per:%d", $sort, $page, $perPage);

        $data = $noCache ? null : Cache::get($cacheKey);

        if (!$data) {
            $endpoint = 'https://affapi.duga.jp/search';
            $resp = Http::timeout(12)->retry(2, 200)->get($endpoint, [
                'appid'    => config('duga.app_id'),
                'agentid'  => config('duga.agent_id'),
                'version'  => config('duga.version'),
                'format'   => config('duga.format'),
                'adult'    => config('duga.adult'),
                'bannerid' => config('duga.banner_id'),
                'sort'     => $sort,
                'hits'     => $perPage,
                'offset'   => $offset,
            ]);

            if ($resp->failed() || ($err = data_get($resp->json(), 'error.reason'))) {
                Log::warning('duga:index api error', [
                    'status' => $resp->status(),
                    'reason' => $err,
                    'body'   => Str::limit($resp->body(), 500)
                ]);
                            // 失敗時は空一覧で返す（500落ち回避）
                $empty = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $page, [
                    'path'  => route('home'),
                    'query' => $request->query(),
                ]);
                return view('index', ['items' => $empty, 'sort' => $sort]);
            }

            $json = $resp->json();
            if (!is_array($json)) {
                \Log::warning('duga:index invalid json');
                $empty = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $page, [
                    'path'  => route('home'),
                    'query' => $request->query(),
                ]);
                return view('index', ['items' => $empty, 'sort' => $sort]);
            }

            // API側の論理エラー（認証エラー等）
            if (isset($json['error'])) {
                \Log::warning('duga:index api error', ['error' => $json['error']]);
                $empty = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $page, [
                    'path'  => route('home'),
                    'query' => $request->query(),
                ]);
                abort(502, 'DUGA API error: '.($json['error']['reason'] ?? 'unknown'));
            }

            // 取得直後の形を軽く記録（必要に応じて log level 調整）
            \Log::debug('duga:index raw keys', ['keys' => array_keys($json)]);

            // 30分キャッシュ
            Cache::put($cacheKey, $json, now()->addMinutes(180));
            $data = $json;
        }

        // 正規化
        $itemsRaw = $this->extractItems($data);
        $total    = $this->extractTotal($data);

        if (empty($itemsRaw)) {
            \Log::warning('duga:index items empty', [
                'sort'   => $sort,
                'page'   => $page,
                'per'    => $perPage,
                'keys'   => array_keys($data),
                'sample' => array_slice($data, 0, 3, true),
            ]);
        }

        $objects = \App\Utils\ConvertObject::arrayToObject($itemsRaw);

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $objects,
            $total ?: ($page * $perPage),
            $perPage,
            $page,
            [
                'path'  => route('home'),
                'query' => $request->query(),
            ]
        );

        return view('index', [
            'items' => $paginator,
            'sort'  => $sort,
        ]);
    }

    // public function show(string $id, Request $request)
    // {

    //     // キャッシュキーを商品ID単位で作成
    //     $cacheKey = "duga:detail:{$id}";

    //     // 24時間キャッシュ
    //     $item = Cache::remember($cacheKey, now()->addDay(), function () use ($id) {
    //         $endpoint = 'https://affapi.duga.jp/search';

    //         $resp = Http::timeout(10)->retry(2, 200)->get($endpoint, [
    //             'appid'    => config('duga.app_id'),
    //             'agentid'  => config('duga.agent_id'),
    //             'version'  => config('duga.version'),
    //             'format'   => config('duga.format'),
    //             'adult'    => config('duga.adult'),
    //             'bannerid' => config('duga.banner_id'),
    //             // productid を指定して1件だけ返す
    //             'keyword'   => $id,
    //             'hits'      => 1,
    //             'offset'    => 1,
    //         ]);

    //         if ($resp->failed()) {
    //             abort(502, 'Failed to fetch data from DUGA API');
    //         }

    //         $data = $resp->json();
    //         if (!is_array($data)) {
    //             abort(502, 'Invalid JSON response from DUGA API');
    //         }

    //         $itemsRaw = $this->extractItems($data);
    //         // 2重配列のケースに対応
    //         if (!empty($itemsRaw) && is_array($itemsRaw) && isset($itemsRaw[0]) && is_array($itemsRaw[0]) && Arr::isAssoc($itemsRaw[0]) === false) {
    //             $itemsRaw = $itemsRaw[0];
    //         }

    //         $first = is_array($itemsRaw) ? (reset($itemsRaw) ?: null) : null;
    //         if (!$first) {
    //             abort(404, 'Item not found');
    //         }

    //         $objects = ConvertObject::arrayToObject([$first]);
    //         return $objects[0] ?? null;
    //     });

    //     if (!$item) {
    //         abort(404, 'Item not found');
    //     }

    //     $related = $this->fetchRelatedItems($item, limit: 12);

    //     return view('products.show', [
    //         'item'    => $item,
    //         'related' => $related,
    //     ]);
    // }

    public function show(string $id, PopularViewService $popular)
    {
        // $cacheKey = "duga:product:{$id}";
        // 旧キャッシュと衝突しないようにキーを更新（v2 など）
        $cacheKey = "duga:vm:v2:{$id}";

        $url = null;
        $top7 =null;
        $top30 = null;

        $top7  = $popular->topByViews(7, 12);
        $top30 = $popular->topByViews(30, 12);
        
        // 追加：カード化（タイトル・サムネ・URL）
        $cards7  = $this->buildCardsFromRanking($top7);
        $cards30 = $this->buildCardsFromRanking($top30);

        // 1) まずキャッシュを素直に読む（あれば即返す）
        if ($vm = Cache::get($cacheKey)) {
            // 念のため：キャッシュはあるが DB が空の“過去遺産”を自動補修
            if (!$this->existsInDb($id)) {
                // 同期で DB に埋める（確実に登録させたい場合は同期のままが安全）
                $this->ingest->fetchAndUpsertByProductId($id);
            }
            // $url = $vm->url;
            // セール情報を取得
            // $sale = $url ? $scraper->fetch($url) : ['is_sale' => false, 'label' => null, 'until' => null, 'items' => []];
            $related = $this->fetchRelatedItems($vm, limit: 12);
            return view('products.show', ['item' => $vm, 'related' => $related, 'top7'=>$cards7,'top30'=>$cards30]);
        }

        // 2) DB に無ければ API → DB upsert → 再取得
        $product = $this->findProduct($id);
        if (!$product) {
            $this->ingest->fetchAndUpsertByProductId($id);   // ★ここで必ず DB に登録
            $product = $this->findProduct($id);
            if (!$product) {
                abort(404, '商品が見つかりません。');
            }
        }

        // 3) Eloquent -> ViewModel に変換
        $vm = $this->toViewModel($product);

        // 4) 完成した ViewModel を 24h キャッシュ
        Cache::put($cacheKey, $vm, now()->addDay());

        // $url = $vm->url;
        // セール情報を取得
        // $sale = $url ? $scraper->fetch($url) : ['is_sale' => false, 'label' => null, 'until' => null, 'items' => []];

        // 5) 画面へ
        $related = $this->fetchRelatedItems($vm, limit: 12);
        return view('products.show', ['item' => $vm, 'related' => $related]);
    }

    /**
     * ランキング（productid, views）配列をカード配列へ
     * 返却: [['productid'=>..., 'title'=>..., 'thumb'=>..., 'url'=>..., 'views'=>...], ...]
     */
    private function buildCardsFromRanking(array $ranking): array
    {
        $out = [];
        foreach ($ranking as $row) {
            // $row は配列 or stdClass 両対応
            $pid   = is_array($row) ? ($row['productid'] ?? null) : ($row->productid ?? null);
            $views = (int) (is_array($row) ? ($row['views'] ?? 0) : ($row->views ?? 0));
            if (!$pid) continue;

            $out[] = $this->resolveCard($pid, $views);
        }
        return $out;
    }

    /**
     * 単一作品のカード情報を取得（キャッシュ→DB→API）
     */
    private function resolveCard(string $productId, int $views = 0): array
    {
        // 1) 作品ページ用キャッシュに ViewModel があれば流用
        $vm = Cache::get("duga:vm:v2:{$productId}");
        if ($vm && is_object($vm)) {
            [$title, $thumb] = $this->extractTitleThumbFromVm($vm);
            return [
                'productid' => $productId,
                'title'     => $title ?: ('#'.$productId),
                'thumb'     => $thumb,
                'url'       => route('products.show', ['id'=>$productId]),
                'views'     => $views,
            ];
        }

        // 2) DBにあれば最小コストでタイトル＆画像を引き出す
        if ($model = $this->findProduct($productId)) {
            $vm = $this->toViewModel($model);
            [$title, $thumb] = $this->extractTitleThumbFromVm($vm);
            // 次に備えて軽くキャッシュ
            Cache::put("duga:vm:v2:{$productId}", $vm, now()->addHours(6));
            return [
                'productid' => $productId,
                'title'     => $title ?: ('#'.$productId),
                'thumb'     => $thumb,
                'url'       => route('products.show', ['id'=>$productId]),
                'views'     => $views,
            ];
        }

        // 3) 無ければAPI → upsert → 取り直し
        try {
            $this->ingest->fetchAndUpsertByProductId($productId);
            if ($model = $this->findProduct($productId)) {
                $vm = $this->toViewModel($model);
                [$title, $thumb] = $this->extractTitleThumbFromVm($vm);
                Cache::put("duga:vm:v2:{$productId}", $vm, now()->addHours(6));
                return [
                    'productid' => $productId,
                    'title'     => $title ?: ('#'.$productId),
                    'thumb'     => $thumb,
                    'url'       => route('products.show', ['id'=>$productId]),
                    'views'     => $views,
                ];
            }
        } catch (\Throwable $e) {
            // ログだけ残してフォールバック
            \Log::warning('resolveCard failed: '.$productId.' '.$e->getMessage());
        }

        // 4) それでもダメならプレースホルダ
        return [
            'productid' => $productId,
            'title'     => '#'.$productId,
            'thumb'     => asset('images/placeholder-wide.png'),
            'url'       => route('products.show', ['id'=>$productId]),
            'views'     => $views,
        ];
    }

    /**
     * ViewModel からタイトルとサムネイルURLを安全に抽出
     */
    private function extractTitleThumbFromVm(object $vm): array
    {
        $title = method_exists($vm,'getTitle') ? (string)$vm->getTitle() : '';
        $thumb = null;

        $poster = method_exists($vm,'getPosterImage') ? $vm->getPosterImage() : null;
        if (is_object($poster)) {
            if (method_exists($poster,'getSmall') && $poster->getSmall())   $thumb = $thumb ?: $poster->getSmall();
            if (method_exists($poster,'getMedium') && $poster->getMedium()) $thumb = $thumb ?: $poster->getMedium();
            if (method_exists($poster,'getMidium') && $poster->getMidium()) $thumb = $thumb ?: $poster->getMidium();
            if (method_exists($poster,'getLarge') && $poster->getLarge())   $thumb = $thumb ?: $poster->getLarge();
        }

        // ジャケットで代替
        if (!$thumb) {
            $j = method_exists($vm,'getJacketImage') ? $vm->getJacketImage() : null;
            if (is_object($j)) {
                if (method_exists($j,'getSmall') && $j->getSmall())   $thumb = $thumb ?: $j->getSmall();
                if (method_exists($j,'getMedium') && $j->getMedium()) $thumb = $thumb ?: $j->getMedium();
                if (method_exists($j,'getMidium') && $j->getMidium()) $thumb = $thumb ?: $j->getMidium();
                if (method_exists($j,'getLarge') && $j->getLarge())   $thumb = $thumb ?: $j->getLarge();
            }
        }

        // プレースホルダ
        if (!$thumb) $thumb = asset('images/placeholder-wide.png');

        return [$title, $thumb];
    }

    /** 関連をまとめてロードして1件取得 */
    private function findProduct(string $productId): ?DugaProduct
    {
        return DugaProduct::with([
            'label','series','categories','performers','directors',
            'samples','thumbnails','saleTypes'
        ])->where('productid', $productId)->first();
    }

    /** DB に存在するかだけ軽く見る */
    private function existsInDb(string $productId): bool
    {
        return \App\Models\DugaProduct::where('productid', $productId)->exists();
    }

    /** Eloquent -> View 用のプレゼンター（API 互換メソッドを持つ ViewModel）へ */
    private function toViewModel(DugaProduct $p): DugaProductView
    {
        $firstSample = $p->samples->first();

        return new DugaProductView([
            'id'             => $p->id,
            'productid'      => $p->productid,
            'title'          => $p->title,
            'original_title' => $p->original_title,
            'caption'        => $p->caption,
            'maker'          => $p->maker,
            'url'            => $p->url,
            'affiliate_url'  => $p->affiliate_url,
            'open_date'      => $p->open_date,
            'release_date'   => $p->release_date,
            'item_no'        => $p->item_no,
            'price'          => $p->price,
            'volume'         => $p->volume,
            'ranking_total'  => $p->ranking_total,
            'mylist_total'   => $p->mylist_total,
            'review_rating'  => $p->review_rating,
            'review_count'   => $p->review_count,

            // 画像
            'poster_small'   => $p->poster_small,
            'poster_medium'  => $p->poster_medium,
            'poster_large'   => $p->poster_large,
            'jacket_small'   => $p->jacket_small,
            'jacket_medium'  => $p->jacket_medium,
            'jacket_large'   => $p->jacket_large,

            // サンプル動画
            'sample' => $firstSample ? [
                'movie'   => $firstSample->movie_url,
                'capture' => $firstSample->capture_url,
            ] : null,

            // サムネイル（小 → 大のURLはBladeで置換して拡大）
            'thumbs' => $p->thumbnails->pluck('thumb_url')->filter()->values()->all(),

            // 関連
            'label'      => $p->label  ? ['id'=>$p->label->duga_id,  'name'=>$p->label->name]  : null,
            'series'     => $p->series ? ['id'=>$p->series->duga_id, 'name'=>$p->series->name] : null,
            'categories' => $p->categories->map(fn($c)=>['id'=>$c->duga_id,'name'=>$c->name])->all(),
            'performers' => $p->performers->map(fn($a)=>['id'=>$a->duga_id,'name'=>$a->name,'kana'=>$a->kana])->all(),
            'directors'  => $p->directors->map(fn($d)=>['id'=>$d->duga_id,'name'=>$d->name])->all(),
            'sale_types' => $p->saleTypes->map(fn($s)=>['type'=>$s->type,'price'=>$s->price])->all(),
        ]);
    }

    public function browse(string $type, string $id, Request $request)
    {
        // ページング入力
        $page    = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(100, (int) $request->integer('per_page', 60)));
        $sort    = $request->input('sort', 'favorite');
        $offset  = ($page - 1) * $perPage + 1;

        // --- type → APIのクエリキーに変換 ---
        $paramMap = [
            'category'  => 'category',
            'label'     => 'labelid',
            'series'    => 'seriesid',
            'performer' => 'performerid',
        ];
        if (!isset($paramMap[$type])) abort(404);
        $filterKey = $paramMap[$type];

        // キャッシュキー
        $cacheKey = sprintf(
            "duga:browse:%s:%s:sort:%s:page:%d:perPage:%d",
            $type,
            $id,
            $sort,
            $page,
            $perPage
        );

        // 30分キャッシュ
        $data = Cache::remember($cacheKey, now()->addMinutes(180), function () use ($filterKey, $id, $sort, $perPage, $offset) {
            $endpoint = 'https://affapi.duga.jp/search';
            $resp = Http::timeout(10)->retry(2, 200)->get($endpoint, [
                'appid'    => config('duga.app_id'),
                'agentid'  => config('duga.agent_id'),
                'version'  => config('duga.version'),
                'format'   => config('duga.format'),
                'adult'    => config('duga.adult'),
                'bannerid' => config('duga.banner_id'),
                'sort'      => $sort,
                'hits'      => $perPage,
                'offset'    => $offset,
                $filterKey  => $id,
            ]);
            if ($resp->failed()) abort(502, 'Failed to fetch data from DUGA API');

            $json = $resp->json();
            if (!is_array($json)) abort(502, 'Invalid JSON response from DUGA API');

            return $json;
        });

        $itemsRaw = $this->extractItems($data);
        // 二重配列対応
        if (!empty($itemsRaw) && is_array($itemsRaw) && isset($itemsRaw[0]) && is_array($itemsRaw[0]) && !Arr::isAssoc($itemsRaw[0])) {
            $itemsRaw = $itemsRaw[0];
        }

        $objects = ConvertObject::arrayToObject($itemsRaw);
        $currentCount = count($objects);

        $total    = $this->extractTotal($data);

        // ===== フィルター名取得 =====
        $filterName = '';
        if (!empty($objects)) {
            $first = $objects[0];

            switch ($type) {
                case 'label':
                    $label = method_exists($first, 'getLabel') ? $first->getLabel() : null;
                    if ($label && method_exists($label, 'getName')) {
                        $filterName = (string) $label->getName();
                    }
                    break;

                case 'series':
                    $series = method_exists($first, 'getSeries') ? $first->getSeries() : null;
                    if ($series && method_exists($series, 'getName')) {
                        $filterName = (string) $series->getName();
                    }
                    break;

                case 'category':
                    $cats = method_exists($first, 'getCategory') ? (array) $first->getCategory() : [];
                    foreach ($cats as $c) {
                        $cid = method_exists($c, 'getId') ? (string) $c->getId() : null;
                        if ($cid === (string) $id) {
                            $filterName = method_exists($c, 'getName') ? (string) $c->getName() : '';
                            break;
                        }
                    }
                    if ($filterName === '' && !empty($cats) && method_exists($cats[0], 'getName')) {
                        $filterName = (string) $cats[0]->getName();
                    }
                    break;

                case 'performer':
                    $pers = method_exists($first, 'getPerformer') ? (array) $first->getPerformer() : [];
                    foreach ($pers as $p) {
                        $pid = method_exists($p, 'getId') ? (string) $p->getId() : null;
                        if ($pid === (string) $id) {
                            $filterName = method_exists($p, 'getName') ? (string) $p->getName() : '';
                            break;
                        }
                    }
                    if ($filterName === '' && !empty($pers) && method_exists($pers[0], 'getName')) {
                        $filterName = (string) $pers[0]->getName();
                    }
                    break;
            }
        }

        // 生配列フォールバック
        if ($filterName === '' && !empty($itemsRaw)) {
            $firstRow = is_array($itemsRaw) ? (is_array(reset($itemsRaw)) ? reset($itemsRaw) : $itemsRaw) : [];

            switch ($type) {
                case 'label':
                    $filterName = (string) Arr::get($firstRow, 'label.0.name', '');
                    break;

                case 'series':
                    $filterName = (string) Arr::get($firstRow, 'series.0.name', '');
                    break;

                case 'category':
                    foreach ((array) Arr::get($firstRow, 'category', []) as $row) {
                        if ((string) Arr::get($row, 'data.id') === (string) $id) {
                            $filterName = (string) Arr::get($row, 'data.name', '');
                            break;
                        }
                    }
                    break;

                case 'performer':
                    foreach ((array) Arr::get($firstRow, 'performer', []) as $row) {
                        if ((string) Arr::get($row, 'data.id') === (string) $id) {
                            $filterName = (string) Arr::get($row, 'data.name', '');
                            break;
                        }
                    }
                    break;
            }
        }

        $effectiveTotal = $total > 0
            ? $total
            : ($currentCount === $perPage
                ? ($page * $perPage + 1)
                : (($page - 1) * $perPage + $currentCount));

        $paginator = new LengthAwarePaginator(
            $objects,
            $effectiveTotal,
            $perPage,
            $page,
            [
                'path'  => route('browse.filter', ['type' => $type, 'id' => $id]),
                'query' => $request->query(),
            ]
        );

        $titleMap = [
            'category'  => 'カテゴリ',
            'label'     => 'レーベル',
            'series'    => 'シリーズ',
            'performer' => '出演者',
        ];

        return view('products.list', [
            'items'      => $paginator,
            'type'       => $type,
            'typeName'   => $titleMap[$type] ?? '絞り込み',
            'filterId'   => $id,
            'filterName' => $filterName,
            'sort'       => $sort,
        ]);
    }

    public function search(Request $request)
    {
        $q       = trim((string) $request->query('q', ''));
        $page    = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(100, (int) $request->integer('per_page', 60)));
        $sort    = $request->query('sort', 'favorite');
        $offset  = ($page - 1) * $perPage + 1;

        // 空クエリなら空の結果で返す
        if ($q === '') {
            $empty = new LengthAwarePaginator([], 0, $perPage, $page, [
                'path'  => route('search'),
                'query' => $request->query(),
            ]);
            return view('products.list', [
                'items'    => $empty,
                'type'     => 'keyword',
                'typeName' => 'キーワード',
                'filterId' => $q,
                'sort'     => $sort,
                'query'    => $q,
            ]);
        }

        // ===== キャッシュキー =====
        $cacheKey = sprintf(
            "duga:search:q:%s:sort:%s:page:%d:perPage:%d",
            md5($q),  // キーワードは長くなるので md5 にする
            $sort,
            $page,
            $perPage
        );

        // ===== API 呼び出しを 30分キャッシュ =====
        $data = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($q, $sort, $perPage, $offset) {
            $endpoint = 'https://affapi.duga.jp/search';
            $resp = Http::timeout(10)->retry(2, 200)->get($endpoint, [
                'appid'    => config('duga.app_id'),
                'agentid'  => config('duga.agent_id'),
                'version'  => config('duga.version'),
                'format'   => config('duga.format'),
                'adult'    => config('duga.adult'),
                'bannerid' => config('duga.banner_id'),
                'sort'      => $sort,
                'hits'      => $perPage,
                'offset'    => $offset,
                'keyword'   => $q,   // API仕様に応じて必要なら変更
            ]);

            if ($resp->failed()) abort(502, 'Failed to fetch data from DUGA API');
            $json = $resp->json();
            if (!is_array($json)) abort(502, 'Invalid JSON response from DUGA API');

            return $json;
        });

        $itemsRaw = $this->extractItems($data);

        if (empty($itemsRaw)) {
    $maybe = Arr::get($data, 'items', []);
    if (is_array($maybe)) {
        // 連想配列の配下を values にして配列に
        $vals = array_values(array_filter($maybe, 'is_array'));
        if (!empty($vals)) {
            // 内側が行配列ならそのまま、そうでなければ更に一階層潜る
            if (isset($vals[0]) && is_array($vals[0]) && Arr::isAssoc($vals[0])) {
                $itemsRaw = $vals;
            } elseif (isset($vals[0][0]) && is_array($vals[0][0]) && Arr::isAssoc($vals[0][0])) {
                $itemsRaw = $vals[0];
            }
        }
    }
}
        if (!empty($itemsRaw) && is_array($itemsRaw) && isset($itemsRaw[0]) && is_array($itemsRaw[0]) && !Arr::isAssoc($itemsRaw[0])) {
            $itemsRaw = $itemsRaw[0];
        }

        $objects = ConvertObject::arrayToObject($itemsRaw);
        $currentCount = count($objects);

        $total    = $this->extractTotal($data);
        
        $effectiveTotal = $total > 0
        ? $total
        : ($currentCount === $perPage
            ? ($page * $perPage + 1)
            : (($page - 1) * $perPage + $currentCount));

        $paginator = new LengthAwarePaginator(
            $objects,
            $effectiveTotal,
            $perPage,
            $page,
            [
                'path'  => route('search'),
                'query' => $request->query(),
            ]
        );

        return view('products.list', [
            'items'    => $paginator,
            'type'     => 'keyword',
            'typeName' => 'キーワード',
            'filterId' => $q,
            'sort'     => $sort,
            'query'    => $q,
        ]);
    }

    /**
     * 関連作品を取得（同シリーズ > 同出演者 > 同カテゴリ の優先度）
     */
    private function fetchRelatedItems($item, int $limit = 12): array
    {
        if (!$item) return [];

        // 現在の作品ID
        $currentId = method_exists($item,'getProductid') ? (string)$item->getProductid() : null;

        // 候補となるID群
        $seriesId = null;
        if (method_exists($item,'getSeries') && $item->getSeries() && method_exists($item->getSeries(),'getId')) {
            $seriesId = (string)$item->getSeries()->getId();
        }

        $performerIds = [];
        if (method_exists($item,'getPerformer')) {
            foreach ((array)$item->getPerformer() as $p) {
                if (is_object($p) && method_exists($p,'getId')) $performerIds[] = (string)$p->getId();
            }
        }

        $categoryIds = [];
        if (method_exists($item,'getCategory')) {
            foreach ((array)$item->getCategory() as $c) {
                if (is_object($c) && method_exists($c,'getId')) $categoryIds[] = (string)$c->getId();
            }
        }

        // 優先順にパラメータを試す
        $strategies = [];
        if ($seriesId)        $strategies[] = ['seriesid'    => $seriesId];
        foreach ($performerIds as $pid) $strategies[] = ['performerid' => $pid];
        foreach ($categoryIds as $cid)  $strategies[] = ['categoryid'  => $cid];

        foreach ($strategies as $params) {
            $items = $this->callDugaSearch($params, $limit + 1); // 自分を除外するので+1
            if (empty($items)) continue;

            // オブジェクト化
            $objects = ConvertObject::arrayToObject($items);

            // 自分を除外し、先頭から $limit 件
            $filtered = [];
            foreach ($objects as $obj) {
                $pid = method_exists($obj,'getProductid') ? (string)$obj->getProductid() : null;
                if ($pid && $pid !== $currentId) {
                    $filtered[] = $obj;
                }
                if (count($filtered) >= $limit) break;
            }
            if (!empty($filtered)) {
                return $filtered;
            }
        }

        return [];
    }

    /**
     * DUGA search API 呼び出し（結果 items を返す）。30分キャッシュ。
     */
    private function callDugaSearch(array $extraParams, int $hits = 12): array
    {
        // キャッシュキー（パラメータで作成）
        $key = 'duga:search:'.md5(json_encode($extraParams).':'.$hits);

        return Cache::remember($key, now()->addMinutes(180), function () use ($extraParams, $hits) {
            $endpoint = 'https://affapi.duga.jp/search';
            $query = array_merge([
                'appid'    => config('duga.app_id'),
                'agentid'  => config('duga.agent_id'),
                'version'  => config('duga.version'),
                'format'   => config('duga.format'),
                'adult'    => config('duga.adult'),
                'bannerid' => config('duga.banner_id'),
                'sort'      => 'favorite',
                'hits'      => $hits,
                'offset'    => 1,
            ], $extraParams);

            $resp = Http::timeout(10)->retry(2, 200)->get($endpoint, $query);
            if ($resp->failed()) return [];

            $data = $resp->json();
            if (!is_array($data)) return [];

            // DUGA の items は入れ子のことがあるため整形
            $items = Arr::get($data, 'items', []);
            if (!empty($items) && is_array($items) && isset($items[0]) && is_array($items[0]) && !Arr::isAssoc($items[0])) {
                $items = $items[0];
            }
            return is_array($items) ? $items : [];
        });
    }

   private function extractItems(array $data): array
{
    // 1) よくあるパターン
    $items = Arr::get($data, 'items', []);

    // items がオブジェクト風（連想）のときのバリエーション
    if (is_array($items)) {
        // items.item 直下に配列
        if (isset($items['item']) && is_array($items['item'])) {
            $items = $items['item'];
        }
        // 連想配列だが1件分（行）っぽい → 包む
        elseif (Arr::isAssoc($items) && $this->looksLikeRow($items)) {
            $items = [$items];
        }
        // 連想配列だが下に数値キー配列が居る → values() で配列化
        elseif (Arr::isAssoc($items)) {
            $vals = array_values($items);
            if (!empty($vals) && is_array($vals[0])) {
                $items = $vals;
            }
        }
        // 2重配列（items[0] が配列かつ非連想）→ 内側を使う
        if (!empty($items) && isset($items[0]) && is_array($items[0]) && !Arr::isAssoc($items[0])) {
            $items = $items[0];
        }
    }

    // 2) items が空なら item 直下を試す
    if (empty($items)) {
        $items = Arr::get($data, 'item', []);
        if (is_array($items) && Arr::isAssoc($items)) {
            $items = [$items];
        }
    }

    // 3) まだ空なら、最初に「行配列（連想配列の配列）」に見える候補を総当たりで拾う
    if (empty($items)) {
        foreach ($data as $v) {
            if (is_array($v)) {
                // そのまま行配列
                if (!empty($v) && isset($v[0]) && is_array($v[0]) && Arr::isAssoc($v[0])) {
                    return $v;
                }
                // ネスト下に行配列
                foreach ($v as $vv) {
                    if (is_array($vv) && !empty($vv) && isset($vv[0]) && is_array($vv[0]) && Arr::isAssoc($vv[0])) {
                        return $vv;
                    }
                }
            }
        }
    }

    return is_array($items) ? $items : [];
}

/** 1レコード（行）っぽいかの簡易判定 */
private function looksLikeRow(array $row): bool
{
    // よく現れるフィールド名のいずれかがあれば行っぽいとみなす
    $keys = array_map('strtolower', array_keys($row));
    $hints = ['productid','title','originaltitle','price','releasedate','jacketimage','posterimage','url'];
    foreach ($hints as $h) {
        if (in_array($h, $keys, true)) return true;
    }
    return false;
}

    /** 総件数を多様なキーから拾う */
    private function extractTotal(array $data): int
    {
        return (int) (
            Arr::get($data, 'total')
            ?? Arr::get($data, 'count.total')
            ?? Arr::get($data, 'count')
            ?? Arr::get($data, 'results')
            ?? 0
        );
    }
}
