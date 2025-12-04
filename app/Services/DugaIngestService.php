<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

use App\Models\DugaProduct;

class DugaIngestService
{
    /** グローバル制限キー（全プロセスで共有） */
    private const RL_KEY        = 'duga:api:global';
    /** 1分当たりの許容量（暫定）。必要に応じて .env で調整 */
    // private const RL_PER_MIN    = 60;   // 60req/min = 1req/sec
    private function rlPerMin(): int { return (int) config('duga.rate_per_min', 60); }
    private const RL_DECAY      = 60;   // 秒
    
    /** 同時実行の直列化（秒） */
    private const LOCK_TTL      = 10;
    private const LOCK_KEY      = 'duga:api:lock';

    /** サーキットブレーカー & 429連続ヒット計測 */
    private const CB_KEY          = 'duga:api:circuit';       // 開いている間はAPI呼ばない
    private const RL_STREAK_KEY   = 'duga:api:rl:streak';     // 連続ヒット回数

    /** リトライ上限/初期待機(ms)/ジッター(ms) */
    private const RETRY_MAX_ATTEMPTS = 7;
    private const RETRY_BASE_MS      = 1000;
    private const RETRY_MAX_JITTER   = 800;

    private const BACKOFF_CAP_MS     = 60000; // 60s
    private const PACER_KEY          = 'duga:api:pacer:last_at';
    private const PACER_LOCK_KEY     = 'duga:api:pacer:lock';

    private function pacerIntervalMs(): int { return (int) config('duga.pacer_interval_ms', 1100); } // 1.1秒

    private function cbOpenForSeconds(): int
    {
        // 429連続ヒット時に開ける時間
        return (int) config('duga.circuit_seconds', 60);
    }

    /**
     * API から productId をキーに1件取得し、DBへ保存して返す
     */
    public function fetchAndUpsertByProductId(string $productId, bool $force = false): ?DugaProduct
    {
        $endpoint = config('duga.endpoint', 'https://affapi.duga.jp/search');

        // --- サーキットが開いている間はAPIを叩かない ---
        if (! $force && Cache::has(self::CB_KEY)) {
            return $this->returnFromDbIfExists($productId);
        }

        // --- DB鮮度チェック（直近N時間ならAPI叩かず返す） ---
        $freshHours = (int) (config('duga.fresh_hours', 6));
        if (! $force && $freshHours > 0) {
            $existing = \DB::table('duga_products')
                ->where('productid', $productId)
                ->first(['id', 'synced_at']);
            if ($existing && isset($existing->synced_at)) {
                $syncedAt = \Carbon\Carbon::parse($existing->synced_at);
                if ($syncedAt->gt(now()->subHours($freshHours))) {
                    return \App\Models\DugaProduct::with([
                        'categories:id,name','performers:id,name,kana',
                        'label:id,name','series:id,name',
                        'directors:id,name','samples','thumbnails','saleTypes',
                    ])->find((int)$existing->id);
                }
            }
        }

        // --- 同一商品クールダウン（短時間に何度も叩かない）---
        $coolKey = "duga:cooldown:{$productId}";
        $coolSec = (int) config('duga.product_cooldown', 5); // デフォルト5秒
        if (! $force && Cache::has($coolKey)) {
            return $this->returnFromDbIfExists($productId);
        }

        // --- 直列化（同時実行を1に制限）---
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);
        if (! $lock->get()) {
            // 既に誰かが叩いている → 少し待って再挑戦（軽いスピン）
            usleep(300 * 1000);
            $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);
            $lock->block(self::LOCK_TTL); // 取得できるまで最大 self::LOCK_TTL 秒待つ
        }

        try {
            // ---- 同一productIdのシングルフライト（同時刻に1回だけ実行）----
            $sfKey = "duga:singleflight:{$productId}";
            $sfLock = Cache::lock($sfKey, 15); // 15秒まで占有
            $got = $sfLock->get();
            if (! $got) {
                // 先行実行がある → 最大2秒だけ待ってDBから返す（スパイク回避）
                $sfLock->block(2);
                return $this->returnFromDbIfExists($productId);
            }

            // ---- グローバルレートリミット（全プロセス共有: 60/min）----
            $allowed = RateLimiter::attempt(self::RL_KEY, $this->rlPerMin(), function () {
                // pass
            }, self::RL_DECAY);
            if (! $allowed) {
                // 許容量オーバー時は少し待つ（スパイク抑制）
                usleep(300 * 1000);
            }

            // ---- 1秒ペーシング（全体で滑らかに1req/sec）----
            $this->acquirePacedSlot();

            // ---- HTTP実行（本文エラー含め検知 → 指数バックオフで再試行）----
            $params = [
                'appid'    => config('duga.app_id'),
                'agentid'  => config('duga.agent_id'),
                'version'  => config('duga.version', '1.2'),
                'format'   => config('duga.format', 'json'),
                'adult'    => config('duga.adult', 1),
                'bannerid' => config('duga.banner_id'),
                'keyword'  => $productId,
                'hits'     => 1,
                'offset'   => 1,
            ];

            $resp = $this->httpGetWithBackoff($endpoint, $params);
            if (! $resp) return null;

            // 呼び出し成功 → 商品ごとにクールダウンを貼る
            if ($coolSec > 0) Cache::put($coolKey, 1, $coolSec);

            $items = $this->extractItems($resp);
            $row   = $items[0] ?? null;
            if (! $row) return null;

            return $this->upsertOne($row, $productId);
        } finally {
            optional($sfLock ?? null)->release();
            optional($lock)->release();
        }
    }

    /**
     * 1秒ペーシングのスロットを取得（全プロセス共有）
     */
    private function returnFromDbIfExists(string $productId): ?DugaProduct
    {
        $existing = \DB::table('duga_products')->where('productid',$productId)->value('id');
        if (!$existing) return null;

        return \App\Models\DugaProduct::with([
            'categories:id,name','performers:id,name,kana',
            'label:id,name','series:id,name',
            'directors:id,name','samples','thumbnails','saleTypes',
        ])->find((int)$existing);
    }

    /**
     * 1秒ペーシングのスロットを取得（全プロセス共有）
     */
    private function acquirePacedSlot(): void
    {
        $lock = Cache::lock(self::PACER_LOCK_KEY, 3);
        $lock->block(3);
        try {
            $now  = (int) floor(microtime(true) * 1000);
            $last = (int) (Cache::get(self::PACER_KEY, 0));
            $need = $last + $this->pacerIntervalMs();
            if ($now < $need) {
                usleep(($need - $now) * 1000);
                $now = (int) floor(microtime(true) * 1000);
            }
            Cache::put(self::PACER_KEY, $now, 60);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * DUGA API を GET で叩く（本文に too many を含む 200 も正しく扱って指数バックオフ）
     * @return array|null json配列 or null
     */
    private function httpGetWithBackoff(string $endpoint, array $query): ?array
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $resp = Http::timeout(15)
                    // 通常のネットワーク失敗は軽く2回までリトライ（サーバ都合は下の本文判定で別管理）
                    ->retry(2, 300, throw: false)
                    ->acceptJson()
                    ->get($endpoint, $query);

                // HTTP失敗（4xx/5xx）なら例外投げる前にレート関連を解釈
                $json = $this->safeJson($resp);

                if ($this->isRateLimited($resp->status(), $json)) {
                    $waitMs = $this->computeBackoffMs($attempt, $this->retryAfterMs($resp));
                    Log::warning('duga:index api rate limited', [
                        'attempt' => $attempt,
                        'status'  => $resp->status(),
                        'body'    => $json,
                        'wait_ms' => $waitMs,
                    ]);
                    $streak = (int) Cache::increment(self::RL_STREAK_KEY);
                    if ($streak >= 3) {
                        Cache::put(self::CB_KEY, 1, $this->cbOpenForSeconds());
                        Cache::forget(self::RL_STREAK_KEY);
                    }

                    if ($attempt >= self::RETRY_MAX_ATTEMPTS) {
                        return null;
                    }
                    usleep($waitMs * 1000);
                    continue;
                }

                // その他のHTTPエラーは throw() に任せる
                if ($resp->failed()) {
                    $resp->throw();
                }

                // 200でも本文がエラー形式なら null を返却（上流で処理中断）
                if ($this->hasBodyError($json)) {
                    Log::warning('duga:index api body error', ['status'=>$resp->status(),'body'=>$json]);
                    return null;
                }
                // 成功したので連続ヒットカウンタをリセット
                Cache::forget(self::RL_STREAK_KEY);

                return is_array($json) ? $json : null;

            } catch (\Throwable $e) {
                // ネットワーク起因などは指数バックオフで再試行
                $waitMs = $this->computeBackoffMs($attempt, null);
                Log::warning('duga:index http exception', [
                    'attempt'=>$attempt, 'msg'=>$e->getMessage(), 'wait_ms'=>$waitMs
                ]);
                if ($attempt >= self::RETRY_MAX_ATTEMPTS) {
                    Log::error('duga:index exhausted attempts', ['attempts'=>$attempt]);
                    return null;
                }
                usleep($waitMs * 1000);
                continue;
            }
        }
    }

    /** JSONを安全に配列化（失敗時は空配列） */
    private function safeJson($resp): array
    {
        try {
            $j = $resp->json();
            return is_array($j) ? $j : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** DUGA特有：200でも本文に {error:{reason:'too many request'}} が来る → これもレート判定 */
    private function isRateLimited(int $status, array $json): bool
    {
        $reason = data_get($json, 'error.reason');
        return $status === 429 || $reason === 'too many request';
    }

    /** 本文一般エラーの検出（レート以外） */
    private function hasBodyError(array $json): bool
    {
        $err = data_get($json, 'error');
        if (!is_array($err)) return false;
        $reason = (string) data_get($err, 'reason', '');
        // レートは別処理。その他エラーなら true
        return $reason !== '' && $reason !== 'too many request';
    }

    /** Retry-After を ms に（秒 or 日付表現に対応） */
    private function retryAfterMs($resp): ?int
    {
        $h = method_exists($resp,'header') ? $resp->header('Retry-After') : null;
        if (!$h) return null;

        // 数値秒
        if (is_numeric($h)) return (int)$h * 1000;

        // 日付（HTTP-date）→ 現在との差
        try {
            $ts = strtotime($h);
            if ($ts) {
                $diff = max(0, $ts - time());
                return $diff * 1000;
            }
        } catch (\Throwable) { /* noop */ }

        return null;
    }

    /** 指数バックオフ＋ジッター ms */
    private function computeBackoffMs(int $attempt, ?int $retryAfterMs): int
    {
        if ($retryAfterMs && $retryAfterMs > 0) {
            return $retryAfterMs + random_int(0, self::RETRY_MAX_JITTER);
        }
        $base = (int) (self::RETRY_BASE_MS * (2 ** ($attempt - 1)));
        $jitter = random_int(0, self::RETRY_MAX_JITTER);
        // 上限を 8秒程度に丸める（必要に応じて調整）
        return min($base + $jitter, 20000);
    }

    /**
     * 1商品の upsert（メイン + 付随情報 + 多対多 pivot 同期）
     */
    public function upsertOne(array $item, string $productId): ?DugaProduct
    {
        $x   = $this->flattenItem($item);
        $now = now();

        // --- products（snake ケースで統一）---
        $p = [
            'productid'      => (string)$productId,
            'title'          => Arr::get($x,'title'),
            'original_title' => Arr::get($x,'originaltitle'),
            'caption'        => Arr::get($x,'caption'),
            'maker'          => Arr::get($x,'makername'),
            'item_no'        => Arr::get($x,'itemno'),
            'price'          => $this->intOrNull(Arr::get($x,'price')),
            'volume'         => $this->intOrNull(Arr::get($x,'volume')),
            'release_date'   => $this->toDate(Arr::get($x,'releasedate')),
            'open_date'      => $this->toDate(Arr::get($x,'opendate')),
            'rating'         => $this->intOrNull(Arr::get($x,'rating') ?? Arr::get($x,'rating.0.total')),
            'mylist_total'   => $this->intOrNull(Arr::get($x,'mylist.0.total')),
            'ranking_total'  => $this->intOrNull(Arr::get($x,'ranking.0.total')),
            'url'            => Arr::get($x,'url'),
            'affiliate_url'  => Arr::get($x,'affiliateurl') ?: Arr::get($x,'affiliateUrl'),
            'poster_small'   => Arr::get($x,'posterimage.0.small'),
            'poster_medium'  => Arr::get($x,'posterimage.1.medium') ?: Arr::get($x,'posterimage.1.midium'),
            'poster_large'   => Arr::get($x,'posterimage.2.large'),
            'jacket_small'   => Arr::get($x,'jacketimage.0.small'),
            'jacket_medium'  => Arr::get($x,'jacketimage.1.medium') ?: Arr::get($x,'jacketimage.1.midium'),
            'jacket_large'   => Arr::get($x,'jacketimage.2.large'),
            'synced_at'      => $now,
            'updated_at'     => $now,
            'created_at'     => $now,
        ];

        return DB::transaction(function () use ($x, $p, $productId, $now) {
            DB::table('duga_products')->upsert([$p], ['productid'], array_keys($p));
            $pidLocal = (int) DB::table('duga_products')->where('productid',$productId)->value('id');

            $this->upsertLabel($pidLocal, $x, $now);
            $this->upsertSeries($pidLocal, $x, $now);

            if ($rv = (Arr::get($x,'review.0') ?: Arr::get($x,'review'))) {
                $rating = $this->numOrNull(Arr::get($rv,'rating'));
                $count  = $this->intOrNull(Arr::get($rv,'reviewer'));
                DB::table('duga_products')->where('id',$pidLocal)->update([
                    'review_rating'=>$rating,'review_count'=>$count,'updated_at'=>$now
                ]);
            }

            $this->upsertSample($pidLocal, $x, $now);

            Log::info('thumb payload', ['raw' => Arr::get($x,'thumbnail')]);
            $this->upsertThumbnails($pidLocal, (array)Arr::get($x,'thumbnail',[]), $now);

            $this->upsertSaleTypes($pidLocal, (array)Arr::get($x,'saletype',[]), $now);

            $this->syncManyToMany($pidLocal, [
                'categories' => $this->normalizeEntities(Arr::get($x,'category',[])),
                'performers' => $this->normalizePeople(Arr::get($x,'performer',[])),
                'directors'  => $this->normalizeEntities(Arr::get($x,'director',[])),
            ], $now);

            return DugaProduct::with([
                'categories:id,name','performers:id,name,kana',
                'label:id,name','series:id,name',
                'directors:id,name','samples','thumbnails','saleTypes',
            ])->find($pidLocal);
        });
    }

    /* ======== 以下はあなたの元コード（変更なし or 体裁のみ） ======== */

    private function upsertLabel(int $pidLocal, array $x, $now): void { /* 省略（元のまま） */ }
    private function upsertSeries(int $pidLocal, array $x, $now): void { /* 省略（元のまま） */ }

    private function upsertSample(int $pidLocal, array $x, $now): void
    {
        $samp = (Arr::get($x,'samplemovie.0.midium') ?: Arr::get($x,'samplemovie.0.medium'));
        if (!$samp && is_array(Arr::get($x,'samplemovie'))) {
            $samp = Arr::get($x,'samplemovie');
        }
        if ($samp) {
            $movie   = Arr::get($samp,'movie');
            $capture = Arr::get($samp,'capture');
            if ($movie || $capture) {
                DB::table('duga_product_samples')->upsert([[
                    'duga_product_id'=>$pidLocal,
                    'movie_url'=>$movie,
                    'capture_url'=>$capture,
                    'created_at'=>$now,'updated_at'=>$now
                ]], ['duga_product_id','movie_url'], ['capture_url','updated_at']);
            }
        }
    }

    private function upsertThumbnails(int $pidLocal, array $thumbnailPayload, $now): void
    {
        $urls = $this->normalizeThumbnailPayload($thumbnailPayload);
        if (empty($urls)) return;

        $hasCreated = Schema::hasColumn('duga_product_thumbnails', 'created_at');
        $hasUpdated = Schema::hasColumn('duga_product_thumbnails', 'updated_at');

        $rows = [];
        foreach (array_values($urls) as $i => $url) {
            $row = [
                'duga_product_id' => $pidLocal,
                'thumb_url'       => $url,
                'full_url'        => str_replace('/noauth/scap/','/cap/',$url),
                'sort_order'      => $i,
            ];
            if ($hasCreated) $row['created_at'] = $now;
            if ($hasUpdated) $row['updated_at'] = $now;
            $rows[] = $row;
        }

        DB::table('duga_product_thumbnails')->upsert(
            $rows,
            ['duga_product_id','thumb_url'],
            array_filter(['full_url','sort_order', $hasUpdated ? 'updated_at' : null])
        );
    }

    /**
     * どんな形でも URL の一次元配列にする
     * - ["https://...jpg", ...]
     * - [{"image":"..."}, {"url":"..."}, {"src":"..."}]
     * - [{"data":{"image":"..."}}]
     * - [(object)["image"=>"..."]]
     * - {"image":"..."} の単体もOK
     */
    private function normalizeThumbnailPayload($raw): array
    {
        $out = [];

        // 単体で来た場合（連想 or オブジェクト一つ）
        if (is_array($raw) && isset($raw['image']) || is_object($raw) && isset($raw->image)) {
            $raw = [$raw];
        }

        foreach ((array)$raw as $item) {
            // 文字列URLの配列
            if (is_string($item) && $item !== '') {
                $out[] = $item;
                continue;
            }

            // 連想配列
            if (is_array($item)) {
                // 直接キー
                $u = $item['image'] ?? $item['url'] ?? $item['src'] ?? null;
                // data 配下
                if (!$u && isset($item['data']) && is_array($item['data'])) {
                    $u = $item['data']['image'] ?? $item['data']['url'] ?? $item['data']['src'] ?? null;
                }
                if (is_string($u) && $u !== '') {
                    $out[] = $u;
                    continue;
                }
            }

            // オブジェクト（stdClass等）
            if (is_object($item)) {
                $u = $item->image ?? $item->url ?? $item->src ?? null;
                if (!$u && isset($item->data) && is_object($item->data)) {
                    $u = $item->data->image ?? $item->data->url ?? $item->data->src ?? null;
                }
                if (is_string($u) && $u !== '') {
                    $out[] = $u;
                    continue;
                }
            }
        }

        // 空/重複/空白を除去
        $out = array_values(array_unique(array_filter(array_map('trim', $out))));
        return $out;
    }

    private function upsertSaleTypes(int $pidLocal, array $saleTypes, $now): void
    {
        $rows = [];
        foreach ($saleTypes as $s) {
            $d = Arr::get($s,'data', $s);
            $type = Arr::get($d,'type'); if(!$type) continue;
            $price= $this->intOrNull(Arr::get($d,'price'));
            $rows[] = [
                'duga_product_id'=>$pidLocal,
                'type'           =>$type,
                'price'          =>$price,
                'created_at'     =>$now,
                'updated_at'     =>$now,
            ];
        }
        if ($rows) {
            DB::table('duga_product_sale_types')->upsert(
                $rows, ['duga_product_id','type'], ['price','updated_at']
            );
        }
    }

    /**
     * 多対多：マスタ upsert → pivot を完全同期
     *
     * @param array{categories?:array<array{duga_id?:string|int,name?:?string}>, performers?:array<array{duga_id?:string|int,name?:?string,kana?:?string}>, directors?:array<array{duga_id?:string|int,name?:?string}>} $relations
     */
    private function syncManyToMany(int $pidLocal, array $relations, $now): void
    {
        // --- Categories ---
        $catIds = $this->upsertMastersAndGetIds(
            table: 'duga_categories',
            rows:  array_map(fn($r)=>[
                        'duga_id' => $r['duga_id'] ?? null,
                        'name'    => $r['name']    ?? null,
                        'created_at'=>$now,'updated_at'=>$now
                    ], $relations['categories'] ?? []),
            uniqueKeys: ['duga_id','name'], // duga_id 優先、なければ name で同一視
            updateCols: ['name','updated_at']
        );
        $this->syncPivot('duga_category_product', 'duga_category_id', $pidLocal, $catIds, $now);

        // --- Performers ---
        $perfIds = $this->upsertMastersAndGetIds(
            table: 'duga_performers',
            rows:  array_map(fn($r)=>[
                        'duga_id' => $r['duga_id'] ?? null,
                        'name'    => $r['name']    ?? null,
                        'kana'    => $r['kana']    ?? null,
                        'created_at'=>$now,'updated_at'=>$now
                    ], $relations['performers'] ?? []),
            uniqueKeys: ['duga_id','name'],
            updateCols: ['name','kana','updated_at']
        );
        $this->syncPivot('duga_performer_product', 'duga_performer_id', $pidLocal, $perfIds, $now);

        // --- Directors ---
        $dirIds = $this->upsertMastersAndGetIds(
            table: 'duga_directors',
            rows:  array_map(fn($r)=>[
                        'duga_id' => $r['duga_id'] ?? null,
                        'name'    => $r['name']    ?? null,
                        'created_at'=>$now,'updated_at'=>$now
                    ], $relations['directors'] ?? []),
            uniqueKeys: ['duga_id','name'],
            updateCols: ['name','updated_at']
        );
        $this->syncPivot('duga_director_product', 'duga_director_id', $pidLocal, $dirIds, $now);
    }

    /**
     * マスタを upsert し、DB のローカル PK(id) の配列を返す
     * - uniqueKeys のうち、値が入っているものを優先して同一性を判定（duga_id > name）
     */
    private function upsertMastersAndGetIds(string $table, array $rows, array $uniqueKeys, array $updateCols): array
    {
        if (empty($rows)) return [];

        // upsert 用のキーを決定（最初に値が入っている unique key を使う）
        $hasKey = function(string $key) use ($rows): bool {
            foreach ($rows as $r) {
                if (array_key_exists($key,$r) && !is_null($r[$key]) && $r[$key] !== '') return true;
            }
            return false;
        };
        $chosenKey = null;
        foreach ($uniqueKeys as $key) {
            if ($hasKey($key)) { $chosenKey = $key; break; }
        }
        if (!$chosenKey) {
            // どの unique key にも値がない → name で作るか、スキップ
            $chosenKey = 'name';
        }

        // upsert 実行
        DB::table($table)->upsert($rows, [$chosenKey], $updateCols);

        // id 取得：chosenKey でまとめて select → id 抽出
        $values = array_values(array_filter(array_map(fn($r)=>$r[$chosenKey] ?? null, $rows), fn($v)=>!is_null($v) && $v!==''));
        if (empty($values)) return [];

        $ids = DB::table($table)
            ->whereIn($chosenKey, $values)
            ->pluck('id')
            ->map(fn($v)=>(int)$v)
            ->all();

        return $ids;
    }

    /**
     * pivot を「完全同期」（現状を全置換）
     * - 既存と欲しいIDの差分を見て、INSERT / DELETE を実施
     */
    private function syncPivot(string $pivotTable, string $relatedFk, int $pidLocal, array $desiredIds, $now): void
    {
        $current = DB::table($pivotTable)
            ->where('duga_product_id', $pidLocal)
            ->pluck($relatedFk)
            ->map(fn($v)=>(int)$v)
            ->all();

        $desired   = array_values(array_unique(array_map('intval', $desiredIds)));
        sort($desired);

        $toInsert = array_values(array_diff($desired, $current));
        $toDelete = array_values(array_diff($current, $desired));

        if (!empty($toInsert)) {
            $rows = array_map(fn($rid)=>[
                'duga_product_id'=>$pidLocal,
                $relatedFk       =>$rid,
            ], $toInsert);
            DB::table($pivotTable)->insert($rows);
        }
        if (!empty($toDelete)) {
            DB::table($pivotTable)
                ->where('duga_product_id', $pidLocal)
                ->whereIn($relatedFk, $toDelete)
                ->delete();
        }
    }

    /* =========================================================
     * 正規化（APIの揺れ吸収）
     * =======================================================*/

    /**
     * エンティティ（カテゴリ/監督など）を {duga_id, name} の配列へ正規化
     */
    private function normalizeEntities($raw): array
    {
        $out = [];
        foreach ((array)$raw as $e) {
            $d = is_array($e) ? $e : (array)$e;
            $id = Arr::get($d,'id') ?? Arr::get($d,'data.id') ?? null;
            $nm = Arr::get($d,'name') ?? Arr::get($d,'data.name') ?? null;
            if ($id === null && $nm === null) continue;
            $out[] = ['duga_id' => $id, 'name' => $nm];
        }
        return $out;
    }

    /**
     * 人物（出演者）を {duga_id, name, kana} の配列へ正規化
     */
    private function normalizePeople($raw): array
    {
        $out = [];
        foreach ((array)$raw as $e) {
            $d = is_array($e) ? $e : (array)$e;
            $id = Arr::get($d,'id') ?? Arr::get($d,'data.id') ?? null;
            $nm = Arr::get($d,'name') ?? Arr::get($d,'data.name') ?? null;
            $ka = Arr::get($d,'kana') ?? Arr::get($d,'data.kana') ?? null;
            if ($id === null && $nm === null) continue;
            $out[] = ['duga_id' => $id, 'name' => $nm, 'kana' => $ka];
        }
        return $out;
    }

    private function extractItems(array $data): array
    {
        $items = Arr::get($data,'items',[]);
        if (isset($items['item'])) $items = $items['item'];
        if (isset($items[0]) && is_array($items[0]) && !Arr::isAssoc($items[0])) $items = $items[0];
        return is_array($items) ? $items : [];
    }

    private function flattenItem(array $row): array
    {
        if (Arr::get($row,'productid') || Arr::get($row,'productId') || Arr::get($row,'id')) return $row;
        if (is_array($row['item']??null)) return $row['item'];
        if (is_array($row['data']??null)) return $row['data'];
        foreach ($row as $v) if (is_array($v) && (Arr::get($v,'productid')||Arr::get($v,'id'))) return $v;
        return $row;
    }

    private function toDate($v)
    {
        try { return $v? Carbon::parse($v)->toDateString():null; }
        catch(\Throwable) { return null; }
    }
    private function intOrNull($v)
    {
        if ($v===null) return null;
        $v=preg_replace('/\D+/', '', (string)$v);
        return $v===''?null:(int)$v;
    }
    private function numOrNull($v)
    {
        if($v===null||$v==='') return null;
        return is_numeric($v)?0+$v:null;
    }
}