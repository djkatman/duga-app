<?php
// app/Http/Controllers/DugaApiController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Utils\ConvertObject;
use Illuminate\Support\Arr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DugaApiController extends Controller
{

public function index(Request $request)
{
    $page     = max(1, (int) $request->integer('page', 1));
    $perPage  = max(1, min(100, (int) $request->integer('per_page', 60)));
    $sort     = $request->input('sort', 'favorite');
    $offset   = ($page - 1) * $perPage + 1;

    // nocache=1 で一時的にキャッシュ無効化可能
    $noCache  = (bool) $request->boolean('nocache', false);

    $cacheKey = sprintf("duga:index:v1:sort:%s:page:%d:per:%d", $sort, $page, $perPage);

    $data = $noCache
        ? null
        : Cache::get($cacheKey);

    // if (!$data) {
        $endpoint = 'http://affapi.duga.jp/search';
        $resp = Http::timeout(10)->retry(2, 200)->get($endpoint, [
            'appid'    => env('APEX_APP_ID'),
            'agentid'  => env('APEX_AGENT_ID'),
            'version'  => env('APEX_API_VERSION', '1.2'),
            'format'   => env('APEX_FORMAT', 'json'),
            'adult'    => env('APEX_ADULT', 1),
            'bannerid' => env('APEX_BANNER_ID'),
            'sort'     => $sort,
            'hits'     => $perPage,
            'offset'   => $offset,
        ]);

        if ($resp->failed()) {
            abort(500, 'Failed to fetch data from DUGA API');
        }

        $json = $resp->json();

        if (!is_array($json)) {
            abort(500, 'Invalid JSON response from DUGA API');
        }

        // 取得直後の形を軽く記録（本番では log level 調整可）
        Log::debug('duga:index raw keys', ['keys' => array_keys($json)]);

        // 30分キャッシュ
        Cache::put($cacheKey, $json, now()->addMinutes(30));
        $data = $json;
    // }

    // ★ 正規化してからオブジェクト化
    $itemsRaw = $this->extractItems($data);
    $total    = $this->extractTotal($data);

    // デバッグ用：空のときは keys をログに出す
    if (empty($itemsRaw)) {
        Log::warning('duga:index items empty', [
            'sort'   => $sort,
            'page'   => $page,
            'per'    => $perPage,
            'keys'   => array_keys($data),
            'sample' => array_slice($data, 0, 3, true),
        ]);
    }

    $objects = ConvertObject::arrayToObject($itemsRaw);

    $paginator = new LengthAwarePaginator(
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

    public function show(string $id, Request $request)
    {
        // キャッシュキーを商品ID単位で作成
        $cacheKey = "duga:detail:{$id}";

        // 24時間キャッシュ
        $item = Cache::remember($cacheKey, now()->addDay(), function () use ($id) {
            $endpoint = 'https://affapi.duga.jp/search';

            $resp = Http::timeout(10)->retry(2, 200)->get($endpoint, [
                'appid'     => env('APEX_APP_ID'),
                'agentid'   => env('APEX_AGENT_ID'),
                'version'   => env('APEX_API_VERSION', '1.0'),
                'format'    => env('APEX_FORMAT', 'json'),
                'adult'     => env('APEX_ADULT', 1),
                'bannerid'  => env('APEX_BANNER_ID'),
                // productid を指定して1件だけ返す
                'keyword'   => $id,
                'hits'      => 1,
                'offset'    => 1,
            ]);

            if ($resp->failed()) {
                abort(502, 'Failed to fetch data from DUGA API');
            }

            $data = $resp->json();
            if (!is_array($data)) {
                abort(502, 'Invalid JSON response from DUGA API');
            }

            $itemsRaw = $this->extractItems($data);
            // 2重配列のケースに対応
            if (!empty($itemsRaw) && is_array($itemsRaw) && isset($itemsRaw[0]) && is_array($itemsRaw[0]) && Arr::isAssoc($itemsRaw[0]) === false) {
                $itemsRaw = $itemsRaw[0];
            }

            $first = is_array($itemsRaw) ? (reset($itemsRaw) ?: null) : null;
            if (!$first) {
                abort(404, 'Item not found');
            }

            $objects = ConvertObject::arrayToObject([$first]);
            return $objects[0] ?? null;
        });

        if (!$item) {
            abort(404, 'Item not found');
        }

        $related = $this->fetchRelatedItems($item, limit: 12);

        return view('products.show', [
            'item'    => $item,
            'related' => $related,
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
        $data = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($filterKey, $id, $sort, $perPage, $offset) {
            $endpoint = 'https://affapi.duga.jp/search';
            $resp = Http::timeout(10)->retry(2, 200)->get($endpoint, [
                'appid'     => env('APEX_APP_ID'),
                'agentid'   => env('APEX_AGENT_ID'),
                'version'   => env('APEX_API_VERSION', '1.0'),
                'format'    => env('APEX_FORMAT', 'json'),
                'adult'     => env('APEX_ADULT', 1),
                'bannerid'  => env('APEX_BANNER_ID'),
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
                'appid'     => env('APEX_APP_ID'),
                'agentid'   => env('APEX_AGENT_ID'),
                'version'   => env('APEX_API_VERSION', '1.0'),
                'format'    => env('APEX_FORMAT', 'json'),
                'adult'     => env('APEX_ADULT', 1),
                'bannerid'  => env('APEX_BANNER_ID'),
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

        return Cache::remember($key, now()->addMinutes(30), function () use ($extraParams, $hits) {
            $endpoint = 'https://affapi.duga.jp/search';
            $query = array_merge([
                'appid'     => env('APEX_APP_ID'),
                'agentid'   => env('APEX_AGENT_ID'),
                'version'   => env('APEX_API_VERSION', '1.0'),
                'format'    => env('APEX_FORMAT', 'json'),
                'adult'     => env('APEX_ADULT', 1),
                'bannerid'  => env('APEX_BANNER_ID'),
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
        // まず items を取得
        $items = Arr::get($data, 'items', []);

        // パターン3: items.item
        if (is_array($items) && isset($items['item']) && is_array($items['item'])) {
            $items = $items['item'];
        }

        // パターン2: items[0] が配列かつ非連想 → 2重配列の内側を使う
        if (is_array($items) && !empty($items) && isset($items[0]) && is_array($items[0]) && !Arr::isAssoc($items[0])) {
            $items = $items[0];
        }

        // すでに行配列なら返す
        if (is_array($items) && !empty($items) && isset($items[0]) && is_array($items[0]) && Arr::isAssoc($items[0])) {
            return $items;
        }

        // まれに root が行配列
        if (empty($items) && isset($data[0]) && is_array($data[0]) && Arr::isAssoc($data[0])) {
            return $data;
        }

        return is_array($items) ? $items : [];
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
