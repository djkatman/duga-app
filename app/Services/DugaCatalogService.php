<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Utils\ConvertObject;
use App\Models\DugaProduct;
use App\ViewModels\DugaProductView;

class DugaCatalogService
{
    public function __construct(private DugaIngestService $ingest) {}

    /** トップ一覧 */
    public function listItems(string $sort, int $page, int $perPage, bool $noCache = false): array
    {
        $sort     = $this->normalizeSort($sort);
        $offset   = ($page - 1) * $perPage + 1;
        $cacheKey = sprintf("duga:index:v2:sort:%s:page:%d:per:%d", $sort, $page, $perPage);

        $data = $noCache ? null : Cache::get($cacheKey);
        if (!$data) {
            $data = $this->callAffWithCache($cacheKey, [
                'sort'   => $sort,
                'hits'   => $perPage,
                'offset' => $offset,
            ], ttlMinutes: 120);
        }

        $items = $this->extractItems($data);
        $total = $this->extractTotal($data);

        return [
            'items' => ConvertObject::arrayToObject($items),
            'total' => $total,
            'raw'   => $data,
        ];
    }

    /** 絞り込み（カテゴリ/レーベル/シリーズ/出演者） */
    public function browse(string $type, string $id, string $sort, int $page, int $perPage): array
    {
        $sort   = $this->normalizeSort($sort);
        $offset = ($page - 1) * $perPage + 1;

        $paramMap = [
            'category'  => 'category',
            'label'     => 'labelid',
            'series'    => 'seriesid',
            'performer' => 'performerid',
        ];
        if (!isset($paramMap[$type])) abort(404);
        $filterKey = $paramMap[$type];

        $cacheKey = sprintf("duga:browse:%s:%s:sort:%s:page:%d:per:%d", $type, $id, $sort, $page, $perPage);
        $data = $this->callAffWithCache($cacheKey, [
            'sort'     => $sort,
            'hits'     => $perPage,
            'offset'   => $offset,
            $filterKey => $id,
        ], 180);

        $items   = $this->extractItems($data);
        $objects = ConvertObject::arrayToObject($items);
        $total   = $this->extractTotal($data);

        return [
            'items'      => $objects,
            'total'      => $total,
            'filterName' => $this->resolveFilterName($type, $id, $objects, $items),
        ];
    }

    /** 検索 */
    public function search(string $q, string $sort, int $page, int $perPage): array
    {
        $sort     = $this->normalizeSort($sort);
        $offset   = ($page - 1) * $perPage + 1;
        $cacheKey = sprintf("duga:search:q:%s:sort:%s:page:%d:per:%d", md5($q), $sort, $page, $perPage);

        $data = $this->callAffWithCache($cacheKey, [
            'sort'    => $sort,
            'hits'    => $perPage,
            'offset'  => $offset,
            'keyword' => $q,
        ], 120);

        $items   = $this->extractItems($data);
        $objects = ConvertObject::arrayToObject($items);
        $total   = $this->extractTotal($data);

        return [
            'items' => $objects,
            'total' => $total,
        ];
    }

    /** 詳細画面: ViewModel と 関連作品（必要なら DB へ取り込み） */
    public function ensureProductView(string $productId, int $relatedLimit = 12): array
    {
        $cacheKey = "duga:vm:v2:{$productId}";
        if ($vm = Cache::get($cacheKey)) {
            if (!$this->existsInDb($productId)) {
                $this->ingest->fetchAndUpsertByProductId($productId);
            }
            return [
                'vm'      => $vm,
                'related' => $this->fetchRelatedItems($vm, $relatedLimit),
            ];
        }

        $product = $this->findProduct($productId);
        if (!$product) {
            $this->ingest->fetchAndUpsertByProductId($productId);
            $product = $this->findProduct($productId);
            if (!$product) abort(404, '商品が見つかりません。');
        }

        $vm = $this->toViewModel($product);
        Cache::put($cacheKey, $vm, now()->addDay());

        return [
            'vm'      => $vm,
            'related' => $this->fetchRelatedItems($vm, $relatedLimit),
        ];
    }

    // =========================
    // 内部ユーティリティ
    // =========================

    /** sort の正規化（アプリ→API） */
    private function normalizeSort(string $sort): string
    {
        $s = strtolower(trim($sort));
        $map = [
            'new'       => 'new',
            'favorite'  => 'favorite',
            'release'   => 'releasedate',
            'ranking'   => 'ranking',
        ];
        return $map[$s] ?? $s;
    }

    private function callAffWithCache(string $cacheKey, array $extraParams, int $ttlMinutes): array
    {
        // 1) fresh があれば即返す
        if ($fresh = Cache::get($cacheKey)) return $fresh;

        // 2) stale があれば即返しつつ裏で更新
        if ($stale = Cache::get($cacheKey.':stale')) {
            $this->refreshAsync($cacheKey, $extraParams, $ttlMinutes);
            return $stale;
        }

        // 3) 誰かが更新中なら「待たずに」手持ち（fresh/stale）を返す
        $lock = Cache::lock('lock:'.$cacheKey, 10);
        if (!$lock->get()) {
            // ※ ここで block() は使わない（LockTimeoutException 回避）
            return Cache::get($cacheKey) ?: (Cache::get($cacheKey.':stale') ?: []);
        }

        try {
            $data = $this->callAff($extraParams);

            if (!empty($data)) {
                $staleFactor = (int) config('duga.stale_factor', 12);
                Cache::put($cacheKey, $data, now()->addMinutes($ttlMinutes));
                Cache::put($cacheKey.':stale', $data, now()->addMinutes($ttlMinutes * $staleFactor));
                return $data;
            }

            // API 失敗時: 手持ちの fresh/stale を返す（なければ空配列）
            return Cache::get($cacheKey) ?: (Cache::get($cacheKey.':stale') ?: []);
        } finally {
            optional($lock)->release();
        }
    }

    private function refreshAsync(string $cacheKey, array $extraParams, int $ttlMinutes): void
    {
        $lock = Cache::lock('refresh:'.$cacheKey, 10);
        if (!$lock->get()) return;

        try {
            $data = $this->callAff($extraParams);
            if (!empty($data)) {
                Cache::put($cacheKey, $data, now()->addMinutes($ttlMinutes));
                Cache::put($cacheKey.':stale', $data, now()->addMinutes($ttlMinutes * 6));
            }
        } finally {
            $lock->release();
        }
    }

    /** DUGA 検索 API 呼び出し（200でも本文で "too many" を検出） */
    private function callAff(array $extraParams, int $maxAttempts = 6): array
    {
        $endpoint = config('duga.endpoint', 'https://affapi.duga.jp/search');
        $base = [
            'appid'    => config('duga.app_id'),
            'agentid'  => config('duga.agent_id'),
            'version'  => config('duga.version', '1.2'),
            'format'   => config('duga.format', 'json'),
            'adult'    => config('duga.adult', 1),
            'bannerid' => config('duga.banner_id'),
        ];

        // sort 正規化を強制
        if (isset($extraParams['sort'])) {
            $extraParams['sort'] = $this->normalizeSort((string)$extraParams['sort']);
        }

        $query = array_merge($base, $extraParams);

        // 例外を出さないペーサー
        try { $this->acquirePacedSlotCatalog(); } catch (\Throwable $e) {
            Log::warning('catalog pacer unexpected error', ['msg' => $e->getMessage()]);
        }

        // 直近レート超過フラグ（軽く待って群れを崩す）
        if (Cache::has('duga:cooldown')) {
            usleep(300 * 1000);
        }
        $cooldownSec = (int) config('duga.cooldown', 5);

        $attempt = 0;
        $baseWaitMs = 500;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $resp = null;
                Redis::throttle('duga:aff')
                    ->allow((int)config('duga.qps', 2))->every(1)
                    ->block(5, function () use ($endpoint, $query, &$resp) {
                        $resp = Http::timeout(15)->acceptJson()->get($endpoint, $query);
                    });
            } catch (\Illuminate\Redis\Limiters\LimiterTimeoutException $e) {
                usleep(250 * 1000);
                continue;
            } catch (\Throwable $e) {
                Log::warning('duga:aff throttle/http exception', ['attempt'=>$attempt,'msg'=>$e->getMessage()]);
                usleep(($baseWaitMs + random_int(0, 200)) * 1000);
                continue;
            }

            if (!$resp) {
                usleep(($baseWaitMs + random_int(0, 200)) * 1000);
                continue;
            }

            $status = $resp->status();
            $body   = (string) $resp->getBody();
            $json   = $this->safeJsonCatalog($resp);

            $reason = (string) data_get($json, 'error.reason', '');
            $bodyRateHit =
                stripos($body, 'rate') !== false && stripos($body, 'limit') !== false
                || stripos($reason, 'too many') !== false;

            $isRateLimited = ($status === 429 || $status === 503 || $bodyRateHit);

            if ($isRateLimited) {
                $waitMs = $this->retryAfterMsCatalog($resp)
                    ?? min(20000, (int)($baseWaitMs * (2 ** ($attempt - 1))) + random_int(0, 400));

                Cache::put('duga:cooldown', 1, now()->addSeconds(max($cooldownSec, 3)));
                Log::warning('duga:aff rate limited', [
                    'attempt' => $attempt,
                    'status'  => $status,
                    'wait_ms' => $waitMs,
                    'x_rl_limit'     => $resp->header('X-RateLimit-Limit'),
                    'x_rl_remaining' => $resp->header('X-RateLimit-Remaining'),
                    'x_rl_reset'     => $resp->header('X-RateLimit-Reset'),
                ]);

                usleep($waitMs * 1000);
                continue;
            }

            if ($resp->serverError() || $resp->clientError()) {
                Log::warning('duga:aff http failed', ['status'=>$status, 'body'=>Str::limit($body, 400)]);
                usleep(($baseWaitMs + random_int(0, 200)) * 1000);
                continue;
            }

            if ($this->hasBodyErrorCatalog($json)) {
                Log::warning('duga:aff api body error', ['status'=>$status, 'reason'=>data_get($json,'error.reason')]);
                return [];
            }

            return is_array($json) ? $json : [];
        }

        return [];
    }

    // -------- Catalog 小物（Ingest 相当）--------
    private function safeJsonCatalog($resp): array
    {
        try {
            $j = $resp->json();
            return is_array($j) ? $j : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function hasBodyErrorCatalog(array $json): bool
    {
        $err = data_get($json, 'error');
        if (!is_array($err)) return false;
        $reason = (string) data_get($err, 'reason', '');
        return $reason !== '' && stripos($reason, 'too many') === false; // レートは別処理
    }

    private function retryAfterMsCatalog($resp): ?int
    {
        $h = method_exists($resp,'header') ? $resp->header('Retry-After') : null;
        if ($h) {
            if (is_numeric($h)) return (int)$h * 1000;
            $ts = @strtotime($h);
            if ($ts) return max(0, $ts - time()) * 1000;
        }
        $reset = method_exists($resp,'header') ? $resp->header('X-RateLimit-Reset') : null;
        if ($reset && is_numeric($reset)) {
            $sec = (int)$reset;
            $sec = $sec > 10_000_000_000 ? (int)round($sec/1000) : $sec; // ms→s 補正
            $diff = max(0, $sec - time());
            if ($diff > 0) return $diff * 1000;
        }
        return null;
    }

    /** カタログ用ペーサー：例外を出さない */
    private function acquirePacedSlotCatalog(): void
    {
        $lock = Cache::lock('duga:catalog:pacer', 2);
        if (! $lock->get()) {
            usleep(150 * 1000);
            return;
        }

        try {
            $now      = (int) floor(microtime(true) * 1000);
            $last     = (int) (Cache::get('duga:catalog:pacer:last', 0));
            $interval = (int) config('duga.catalog_pacer_ms', 300);

            $need = $last + $interval;
            if ($now < $need) {
                usleep(($need - $now) * 1000);
                $now = (int) floor(microtime(true) * 1000);
            }
            Cache::put('duga:catalog:pacer:last', $now, 60);
        } finally {
            optional($lock)->release();
        }
    }

    private function extractItems(array $data): array
    {
        $items = Arr::get($data, 'items', []);

        if (is_array($items)) {
            if (isset($items['item']) && is_array($items['item'])) {
                $items = $items['item'];
            } elseif (Arr::isAssoc($items) && $this->looksLikeRow($items)) {
                $items = [$items];
            } elseif (Arr::isAssoc($items)) {
                $vals = array_values(array_filter($items, 'is_array'));
                if (!empty($vals)) $items = $vals;
            }
            if (!empty($items) && isset($items[0]) && is_array($items[0]) && !Arr::isAssoc($items[0])) {
                $items = $items[0];
            }
        }

        if (empty($items)) {
            $items = Arr::get($data, 'item', []);
            if (is_array($items) && Arr::isAssoc($items)) $items = [$items];
        }

        if (empty($items)) {
            foreach ($data as $v) {
                if (is_array($v)) {
                    if (!empty($v) && isset($v[0]) && is_array($v[0]) && Arr::isAssoc($v[0])) return $v;
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

    private function looksLikeRow(array $row): bool
    {
        $keys  = array_map('strtolower', array_keys($row));
        $hints = ['productid','title','originaltitle','price','releasedate','jacketimage','posterimage','url'];
        foreach ($hints as $h) if (in_array($h, $keys, true)) return true;
        return false;
    }

    // ---------- show 用 ----------
    private function findProduct(string $productId): ?DugaProduct
    {
        return DugaProduct::with([
            'label','series','categories','performers','directors',
            'samples','thumbnails','saleTypes'
        ])->where('productid', $productId)->first();
    }

    private function existsInDb(string $productId): bool
    {
        return DugaProduct::where('productid', $productId)->exists();
    }

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
            'poster_small'   => $p->poster_small,
            'poster_medium'  => $p->poster_medium,
            'poster_large'   => $p->poster_large,
            'jacket_small'   => $p->jacket_small,
            'jacket_medium'  => $p->jacket_medium,
            'jacket_large'   => $p->jacket_large,
            'sample'         => $firstSample ? [
                'movie'   => $firstSample->movie_url,
                'capture' => $firstSample->capture_url,
            ] : null,
            'thumbs'         => $p->thumbnails->pluck('thumb_url')->filter()->values()->all(),
            'label'          => $p->label  ? ['id'=>$p->label->duga_id,  'name'=>$p->label->name]  : null,
            'series'         => $p->series ? ['id'=>$p->series->duga_id, 'name'=>$p->series->name] : null,
            'categories'     => $p->categories->map(fn($c)=>['id'=>$c->duga_id,'name'=>$c->name])->all(),
            'performers'     => $p->performers->map(fn($a)=>['id'=>$a->duga_id,'name'=>$a->name,'kana'=>$a->kana])->all(),
            'directors'      => $p->directors->map(fn($d)=>['id'=>$d->duga_id,'name'=>$d->name])->all(),
            'sale_types'     => $p->saleTypes->map(fn($s)=>['type'=>$s->type,'price'=>$s->price])->all(),
        ]);
    }

    /** 関連取得（同シリーズ → 出演者 → カテゴリ → ラベル/メーカー） */
    private function fetchRelatedItems(DugaProductView $vm, int $limit = 12): array
    {
        $currentId = method_exists($vm,'getProductid') ? (string)$vm->getProductid() : null;

        $seriesId = null;
        if (method_exists($vm,'getSeries') && $vm->getSeries() && method_exists($vm->getSeries(),'getId')) {
            $seriesId = (string)$vm->getSeries()->getId();
        }

        $perObjs = method_exists($vm,'getPerformers') ? (array)$vm->getPerformers()
                 : (method_exists($vm,'getPerformer')  ? (array)$vm->getPerformer() : []);
        $catObjs = method_exists($vm,'getCategories') ? (array)$vm->getCategories()
                 : (method_exists($vm,'getCategory')   ? (array)$vm->getCategory()  : []);

        $performerIds = $this->extractIdsFromList($perObjs);
        $categoryIds  = $this->extractIdsFromList($catObjs);

        $strategies = [];
        if ($seriesId) $strategies[] = ['seriesid' => $seriesId];
        foreach ($performerIds as $pid) $strategies[] = ['performerid' => $pid];
        foreach ($categoryIds as $cid) {
            $strategies[] = ['category'   => $cid];
            $strategies[] = ['categoryid' => $cid];
        }

        if (empty($strategies)) {
            if (method_exists($vm,'getLabel') && $vm->getLabel() && method_exists($vm->getLabel(),'getId')) {
                $strategies[] = ['labelid' => (string)$vm->getLabel()->getId()];
            } elseif (method_exists($vm,'getMaker') && ($mk = trim((string)$vm->getMaker()))) {
                $strategies[] = ['keyword' => $mk];
            }
        }

        foreach ($strategies as $params) {
            // SWR つきキャッシュ（成功: 30分、新鮮; stale: 3時間）
            $ck   = 'duga:related:v2:'.md5(json_encode([$currentId,$params,$limit]));
            $data = $this->callAffWithCache($ck, array_merge($params, [
                'sort'   => 'favorite',
                'hits'   => $limit + 1,
                'offset' => 1,
            ]), ttlMinutes: 30);

            $rows = $this->extractItems(is_array($data)?$data:[]);
            if (empty($rows)) continue;

            $objects  = ConvertObject::arrayToObject($rows);
            $filtered = [];
            foreach ($objects as $obj) {
                $pid = method_exists($obj,'getProductid') ? (string)$obj->getProductid() : null;
                if ($pid && $pid !== $currentId) $filtered[] = $obj;
                if (count($filtered) >= $limit) break;
            }
            if (!empty($filtered)) return $filtered;
        }

        return [];
    }

    /** フィルター名の推定 */
    private function resolveFilterName(string $type, string $id, array $objects, array $itemsRaw): string
    {
        $filterName = '';
        if (!empty($objects)) {
            $first = $objects[0];
            switch ($type) {
                case 'label':
                    $label = method_exists($first, 'getLabel') ? $first->getLabel() : null;
                    if ($label && method_exists($label, 'getName')) $filterName = (string) $label->getName();
                    break;
                case 'series':
                    $series = method_exists($first, 'getSeries') ? $first->getSeries() : null;
                    if ($series && method_exists($series, 'getName')) $filterName = (string) $series->getName();
                    break;
                case 'category':
                    $cats = method_exists($first, 'getCategory') ? (array) $first->getCategory() : [];
                    foreach ($cats as $c) {
                        $cid = method_exists($c, 'getId') ? (string) $c->getId() : null;
                        if ($cid === (string) $id && method_exists($c, 'getName')) {
                            $filterName = (string) $c->getName();
                            break;
                        }
                    }
                    break;
                case 'performer':
                    $pers = method_exists($first, 'getPerformer') ? (array) $first->getPerformer() : [];
                    foreach ($pers as $p) {
                        $pid = method_exists($p, 'getId') ? (string) $p->getId() : null;
                        if ($pid === (string) $id && method_exists($p, 'getName')) {
                            $filterName = (string) $p->getName();
                            break;
                        }
                    }
                    break;
            }
        }

        if ($filterName !== '' || empty($itemsRaw)) return $filterName;

        $firstRow = is_array($itemsRaw) ? (is_array(reset($itemsRaw)) ? reset($itemsRaw) : $itemsRaw) : [];
        return match ($type) {
            'label'     => (string) Arr::get($firstRow, 'label.0.name', ''),
            'series'    => (string) Arr::get($firstRow, 'series.0.name', ''),
            'category'  => $this->resolveNameById($firstRow, 'category', $id),
            'performer' => $this->resolveNameById($firstRow, 'performer', $id),
            default     => '',
        };
    }

    private function resolveNameById(array $row, string $key, string $id): string
    {
        foreach ((array) Arr::get($row, $key, []) as $v) {
            if ((string) Arr::get($v, 'data.id') === (string) $id) {
                return (string) Arr::get($v, 'data.name', '');
            }
        }
        return '';
    }

    private function extractIdsFromList($list): array
    {
        $ids = [];
        foreach ((array)$list as $e) {
            if (is_object($e) && method_exists($e,'getId')) {
                $ids[] = (string)$e->getId();
            } elseif (is_array($e) && isset($e['id'])) {
                $ids[] = (string)$e['id'];
            } elseif (is_array($e) && isset($e['data']['id'])) {
                $ids[] = (string)$e['data']['id'];
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }
}