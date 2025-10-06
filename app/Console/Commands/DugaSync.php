<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DugaSync extends Command
{
    protected $signature   = 'duga:sync {--sort=new} {--pages=5} {--hits=60}';
    protected $description = 'Fetch DUGA API and upsert to local DB (products, taxonomies, pivots, and detail tables)';

    public function handle()
    {
        $sort  = $this->option('sort');
        $pages = (int)$this->option('pages');
        $hits  = (int)$this->option('hits');

        $this->line('database: ' . (DB::connection()->getDatabaseName() ?? '(null)'));

        // duga_products の命名（スネーク or API名キャメル）を推定
        $snakeNames = Schema::hasColumn('duga_products', 'original_title')
                 || Schema::hasColumn('duga_products', 'affiliate_url')
                 || Schema::hasColumn('duga_products', 'item_no')
                 || Schema::hasColumn('duga_products', 'maker');

        $this->info("Sync sort={$sort} pages={$pages} hits={$hits} (snakeNames=" . ($snakeNames ? 'yes' : 'no') . ")");

        $totalProducts = 0;
        $totalCats     = 0;
        $totalPers     = 0;
        $totalDirs     = 0;
        $totalLabels   = 0;
        $totalSeries   = 0;

        $totalSyncPC   = 0; // product-category
        $totalSyncPP   = 0; // product-performer
        $totalPivotDP  = 0; // director pivot

        $totalSamples  = 0;
        $totalThumbs   = 0;
        $totalSales    = 0;
        $totalPatched  = 0;

        for ($page = 1; $page <= $pages; $page++) {
            $offset = ($page - 1) * $hits + 1;

            // ===== API =====
            $resp = Http::timeout(20)->retry(2, 300)->get(config('duga.endpoint', 'https://affapi.duga.jp/search'), [
                'appid'    => config('duga.app_id'),
                'agentid'  => config('duga.agent_id'),
                'version'  => config('duga.version', '1.2'),
                'format'   => config('duga.format', 'json'),
                'adult'    => config('duga.adult', 1),
                'bannerid' => config('duga.banner_id'),
                'sort'     => $sort,
                'hits'     => $hits,
                'offset'   => $offset,
            ]);

            if ($resp->failed()) {
                $this->error("API failed page {$page} status=" . $resp->status());
                continue;
            }

            $items = $this->extractItems($resp->json());
            $this->line("page {$page}: " . count($items) . " items");
            if (empty($items)) continue;

            // ===== 1) products upsert =====
            $now   = now();
            $rows  = [];
            $skippedNoPid = 0;

            foreach ($items as $row) {
                $flat = $this->flattenItem($row);
                $pid  = Arr::get($flat, 'productid') ?? Arr::get($flat, 'productId') ?? Arr::get($flat, 'id');
                if (!$pid) { $skippedNoPid++; continue; }

                $mapped = $snakeNames ? $this->mapRowToSnake($flat) : $this->mapRowToCamel($flat);
                $mapped['productid']  = (string)$pid;
                $mapped['synced_at']  = $now;
                $mapped['updated_at'] = $now;
                $mapped['created_at'] = $now;
                $rows[] = $mapped;
            }

            if ($skippedNoPid > 0) {
                $this->warn("  skipped rows without productid: {$skippedNoPid}");
            }

            if (!empty($rows)) {
                $updateCols = array_values(array_diff(array_keys($rows[0]), ['productid','created_at']));
                DB::table('duga_products')->upsert($rows, ['productid'], $updateCols);
                $this->info("  upserted products: " . count($rows));
                $totalProducts += count($rows);
            }

            // ===== 2) categories / performers upsert =====
            [$catSeeds, $perSeeds] = $this->collectCatPerSeeds($items);
            if (!empty($catSeeds)) {
                DB::table('duga_categories')->upsert($catSeeds, ['duga_id'], ['name','updated_at']);
                $totalCats += count($catSeeds);
            }
            if (!empty($perSeeds)) {
                DB::table('duga_performers')->upsert($perSeeds, ['duga_id'], ['name','kana','updated_at']);
                $totalPers += count($perSeeds);
            }

            // ===== 3) labels / series / directors / review patch seeds =====
            [$labelSeeds, $seriesSeeds, $directorSeeds, $productPatch] = $this->collectLabelSeriesDirectorAndReview($items);
            if (!empty($labelSeeds)) {
                DB::table('duga_labels')->upsert($labelSeeds, ['duga_id'], ['name','updated_at']);
                $totalLabels += count($labelSeeds);
            }
            if (!empty($seriesSeeds)) {
                DB::table('duga_series')->upsert($seriesSeeds, ['duga_id'], ['name','updated_at']);
                $totalSeries += count($seriesSeeds);
            }
            if (!empty($directorSeeds)) {
                DB::table('duga_directors')->upsert($directorSeeds, ['duga_id'], ['name','updated_at']);
                $totalDirs += count($directorSeeds);
            }

            // ===== 4) id maps =====
            $catIdMap     = $this->pluckIdMap('duga_categories', 'duga_id');
            $perIdMap     = $this->pluckIdMap('duga_performers', 'duga_id');
            $productIdMap = $this->pluckIdMap('duga_products', 'productid');
            $labelIdMap   = $this->pluckIdMap('duga_labels', 'duga_id');
            $seriesIdMap  = $this->pluckIdMap('duga_series', 'duga_id');
            $dirIdMap     = $this->pluckIdMap('duga_directors', 'duga_id');

            // ===== 5) products に label_id / series_id / review_* をパッチ =====
            $patchRows = [];
            foreach ($productPatch as $pid => $patch) {
                $row = [
                    'productid'     => (string)$pid,
                    'label_id'      => null,
                    'series_id'     => null,
                    'review_rating' => null,
                    'review_count'  => null,
                    'updated_at'    => $now,
                ];
                if (!empty($patch['label_duga_id']))  $row['label_id']  = $labelIdMap[$patch['label_duga_id']]  ?? null;
                if (!empty($patch['series_duga_id'])) $row['series_id'] = $seriesIdMap[$patch['series_duga_id']] ?? null;
                if (array_key_exists('review_rating', $patch)) $row['review_rating'] = $patch['review_rating'];
                if (array_key_exists('review_count',  $patch)) $row['review_count']  = $patch['review_count'];
                $patchRows[] = $row;
            }

            // ← upsertせずに、productid がある行だけ個別 update
            foreach ($patchRows as $row) {
                DB::table('duga_products')
                ->where('productid', $row['productid'])
                ->update(Arr::except($row, ['productid']));
            }
            $totalPatched += count($patchRows);

            // ===== 6) SYNC: categories / performers pivots（置換） =====
            [$finalCatIds, $finalPerIds, $touchedProducts] = $this->buildPerProductFinalIds(
                $items, $productIdMap, $catIdMap, $perIdMap
            );

            DB::transaction(function () use ($finalCatIds, $finalPerIds, $touchedProducts, &$totalSyncPC, &$totalSyncPP) {
                foreach ($touchedProducts as $prdId) {
                    // categories
                    DB::table('duga_category_product')->where('duga_product_id', $prdId)->delete();
                    $catIds = array_values(array_unique($finalCatIds[$prdId] ?? []));
                    if (!empty($catIds)) {
                        $ins = array_map(fn($cid)=>['duga_product_id'=>$prdId,'duga_category_id'=>$cid], $catIds);
                        DB::table('duga_category_product')->insertOrIgnore($ins);
                        $totalSyncPC += count($ins);
                    }
                    // performers
                    DB::table('duga_performer_product')->where('duga_product_id', $prdId)->delete();
                    $perIds = array_values(array_unique($finalPerIds[$prdId] ?? []));
                    if (!empty($perIds)) {
                        $ins = array_map(fn($pid)=>['duga_product_id'=>$prdId,'duga_performer_id'=>$pid], $perIds);
                        DB::table('duga_performer_product')->insertOrIgnore($ins);
                        $totalSyncPP += count($ins);
                    }
                }
            });

            // ===== 7) directors pivot（追加型 upsert / 主キー順: product -> director） =====
            $pivotDP = [];
            $seenDP  = [];
            foreach ($items as $row) {
                $flat  = $this->flattenItem($row);
                $pidStr = (string) (Arr::get($flat,'productid') ?? Arr::get($flat,'productId') ?? Arr::get($flat,'id'));
                $prdId  = $productIdMap[$pidStr] ?? null;
                if (!$pidStr || !$prdId) continue;

                foreach ((array) ($flat['director'] ?? []) as $d) {
                    $didStr = (string) (Arr::get($d,'data.id') ?? Arr::get($d,'id'));
                    $dirId  = $dirIdMap[$didStr] ?? null;
                    if (!$didStr || !$dirId) continue;
                    $k = $prdId.':'.$dirId;
                    if (isset($seenDP[$k])) continue;
                    $seenDP[$k] = true;
                    $pivotDP[] = ['duga_product_id'=>$prdId,'duga_director_id'=>$dirId];
                }
            }
            if (!empty($pivotDP)) {
                // 既存があれば静かにスキップ（重複エラーを出さない）
                DB::table('duga_director_product')->insertOrIgnore($pivotDP);
                $totalPivotDP += count($pivotDP);
            }

            // ===== 8) samples（動画） =====
            $sampleRows = [];
            foreach ($items as $row) {
                $flat  = $this->flattenItem($row);
                $pidStr = (string) (Arr::get($flat,'productid') ?? Arr::get($flat,'productId') ?? Arr::get($flat,'id'));
                $prdId  = $productIdMap[$pidStr] ?? null;
                if (!$pidStr || !$prdId) continue;

                $sv = $flat['samplemovie'] ?? $flat['sampleMovie'] ?? null;
                if (!$sv) continue;

                $list = (is_array($sv) && array_is_list($sv)) ? $sv : [$sv];
                foreach ($list as $one) {
                    // midium / medium 下に movie / capture
                    $container = Arr::get($one,'midium') ?? Arr::get($one,'medium') ?? $one;
                    $movie   = Arr::get($container,'movie');
                    $capture = Arr::get($container,'capture');
                    if ($movie || $capture) {
                        $sampleRows[] = [
                            'duga_product_id'=>$prdId,
                            'movie_url'=>$movie,
                            'capture_url'=>$capture,
                            'created_at'=>$now,'updated_at'=>$now,
                        ];
                    }
                }
            }
            if (!empty($sampleRows)) {
                DB::table('duga_product_samples')->upsert(
                    $sampleRows, ['duga_product_id','movie_url'], ['capture_url','updated_at']
                );
                $totalSamples += count($sampleRows);
            }

            // ===== 9) thumbnails（サンプル画像） =====
            $thumbRows = [];
            $thumbs = (array) (Arr::get($flat,'thumbnail') ?? Arr::get($flat,'thumb') ?? []);
            $i = 0;
            foreach ($thumbs as $t) {
                $thumb = is_string($t) ? $t : (Arr::get($t, 'image') ?? Arr::get($t,'url'));
                if (!$thumb) continue;
                $full  = str_replace('/noauth/scap/', '/cap/', $thumb);
                $thumbRows[] = [
                    'duga_product_id'=>$prdId,
                    'thumb_url'=>$thumb,
                    'full_url'=>$full,
                    'sort_order'=>$i++,
                    'created_at'=>$now,'updated_at'=>$now,
                ];
            }
            if (!empty($thumbRows)) {
                DB::table('duga_product_thumbnails')->upsert(
                    $thumbRows, ['duga_product_id','thumb_url'], ['full_url','sort_order','updated_at']
                );
                $totalThumbs += count($thumbRows);
            }

            // ===== 10) sale types（販売形態） =====
            // $saleRows = [];
            // $minPriceByProduct = [];

            // foreach ($items as $row) {
            //     $flat   = $this->flattenItem($row);
            //     $pidStr = (string) (Arr::get($flat,'productid') ?? Arr::get($flat,'productId') ?? Arr::get($flat,'id'));
            //     $prdId  = $productIdMap[$pidStr] ?? null;
            //     if (!$prdId) continue;

            //     $sales = (array) (Arr::get($flat,'saletype') ?? Arr::get($flat,'saleType') ?? []);
            //     foreach ($sales as $s) {
            //         // { data: { type, price } } / { type, price } 両対応
            //         $node  = (is_array($s) && isset($s['data'])) ? $s['data'] : $s;

            //         $type  = Arr::get($node, 'type');
            //         $priceRaw = Arr::get($node, 'price');
            //         if (!$type) continue;

            //         // 価格文字列（例: "1,480円～"）→ intへ
            //         $price = $this->parsePriceToInt($priceRaw);

            //         $saleRows[] = [
            //             'duga_product_id' => $prdId,
            //             'type'            => $type,
            //             'price'           => $price,
            //             'created_at'      => $now,
            //             'updated_at'      => $now,
            //         ];

            //         // 最安値を記録
            //         if (!is_null($price)) {
            //             if (!isset($minPriceByProduct[$prdId])) {
            //                 $minPriceByProduct[$prdId] = $price;
            //             } else {
            //                 $minPriceByProduct[$prdId] = min($minPriceByProduct[$prdId], $price);
            //             }
            //         }
            //     }
            // }

            // if (!empty($saleRows)) {
            //     // （duga_product_id, type）でユニークなので1:N対応
            //     DB::table('duga_product_sale_types')->upsert(
            //         $saleRows,
            //         ['duga_product_id', 'type'],  // この組み合わせが一意
            //         ['price','updated_at']
            //     );
            //     $totalSales += count($saleRows);
            // }

            // // ====== products.price（作品の最安値）をパッチ ======
            // if (!empty($minPriceByProduct)) {
            //     $patchRows = [];
            //     foreach ($minPriceByProduct as $pid => $minPrice) {
            //         $patchRows[] = [
            //             'id'         => $pid,
            //             'price'      => $minPrice,
            //             'updated_at' => $now,
            //         ];
            //     }
            //     DB::table('duga_products')->upsert(
            //         $patchRows,
            //         ['id'],
            //         ['price','updated_at']
            //     );
            // }
            $saleRows = [];
            $sales = (array) (Arr::get($flat,'saletype') ?? Arr::get($flat,'saleType') ?? []);
            foreach ($sales as $s) {
                $type  = Arr::get($s,'data.type') ?? Arr::get($s,'type');
                $price = $this->parsePriceToInt(Arr::get($s,'data.price') ?? Arr::get($s,'price'));
                if (!$type) continue;
                $saleRows[] = [
                    'duga_product_id' => $prdId,
                    'type'            => $type,
                    'price'           => $price,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
            if (!empty($saleRows)) {
                DB::table('duga_product_sale_types')->upsert(
                    $saleRows,
                    ['duga_product_id','type'],
                    ['price','updated_at']
                );
                $totalSales += count($saleRows);
            }

            $this->info(
                "  synced pivots: PC+{$totalSyncPC} / PP+{$totalSyncPP}; dirs={$totalDirs}, dpivot+{$totalPivotDP}; "
                ."lbl={$totalLabels}, ser={$totalSeries}; samples+{$totalSamples}, thumbs+{$totalThumbs}, sales+{$totalSales}; patched+{$totalPatched}"
            );
        }

        $this->info(
            "done: products={$totalProducts}, categories={$totalCats}, performers={$totalPers}, "
            ."synced(PC)={$totalSyncPC}, synced(PP)={$totalSyncPP}, directors={$totalDirs}, dpivots={$totalPivotDP}, "
            ."labels={$totalLabels}, series={$totalSeries}, samples={$totalSamples}, thumbs={$totalThumbs}, "
            ."sales={$totalSales}, patched_products={$totalPatched}"
        );
        return Command::SUCCESS;
    }

    /* ========= helpers ========= */

    private function toDate($v)
    {
        try { return $v ? Carbon::parse($v)->toDateString() : null; }
        catch (\Throwable) { return null; }
    }

    private function extractItems(array $data): array
    {
        $items = Arr::get($data,'items',[]);
        if (is_array($items) && isset($items['item']) && is_array($items['item'])) $items = $items['item'];
        if (is_array($items) && !empty($items) && isset($items[0]) && is_array($items[0]) && !Arr::isAssoc($items[0])) $items = $items[0];
        if (is_array($items) && !empty($items) && isset($items[0]) && is_array($items[0]) && Arr::isAssoc($items[0])) return $items;
        if (empty($items) && isset($data[0]) && is_array($data[0]) && Arr::isAssoc($data[0])) return $data;
        return is_array($items) ? $items : [];
    }

    private function getTableColumns(string $table): array
    {
        try {
            // SQLite 用
            $cols = DB::select("PRAGMA table_info({$table})");
            if ($cols) return array_map(fn($c)=>$c->name, $cols);
        } catch (\Throwable $e) {}

        try {
            // Doctrine が入っていれば
            $schema = DB::getDoctrineSchemaManager();
            $cols = $schema->listTableColumns($table);
            if ($cols) return array_keys($cols);
        } catch (\Throwable $e) {}

        try {
            // MySQL フォールバック
            $rows = DB::select('SHOW COLUMNS FROM '.$table);
            if ($rows) return array_map(fn($r)=>$r->Field, $rows);
        } catch (\Throwable $e) {}

        return [];
    }

    /* ---------- flatten item ---------- */
    private function flattenItem(array $row): array
    {
        if (Arr::get($row,'productid') || Arr::get($row,'productId') || Arr::get($row,'id')) return $row;

        if (is_array($row['data'] ?? null)) {
            $d=$row['data'];
            if (Arr::get($d,'productid')||Arr::get($d,'productId')||Arr::get($d,'id')) return $d;
        }
        if (is_array($row['item']['data'] ?? null)) {
            $d=$row['item']['data'];
            if (Arr::get($d,'productid')||Arr::get($d,'productId')||Arr::get($d,'id')) return $d;
        }
        foreach ($row as $v) {
            if (is_array($v)) {
                if (Arr::get($v,'productid')||Arr::get($v,'productId')||Arr::get($v,'id')) return $v;
                foreach ($v as $vv) {
                    if (is_array($vv) && (Arr::get($vv,'productid')||Arr::get($vv,'productId')||Arr::get($vv,'id'))) return $vv;
                }
            }
        }
        return $row;
    }

    private function collectCatPerSeeds(array $items): array
    {
        $now  = now(); $cats = []; $pers = [];
        foreach ($items as $row) {
            $flat = $this->flattenItem($row);

            foreach ((array)Arr::get($flat,'category',[]) as $c) {
                $cid=Arr::get($c,'data.id')??Arr::get($c,'id');
                $nm =Arr::get($c,'data.name')??Arr::get($c,'name');
                if(!$cid||!$nm) continue;
                $cats[$cid]=['duga_id'=>(string)$cid,'name'=>(string)$nm,'updated_at'=>$now,'created_at'=>$now];
            }

            foreach ((array)Arr::get($flat,'performer',[]) as $p) {
                $pid=Arr::get($p,'data.id')??Arr::get($p,'id');
                $name=Arr::get($p,'data.name')??Arr::get($p,'name');
                $kana=Arr::get($p,'data.kana')??Arr::get($p,'kana');
                if(!$pid||!$name) continue;
                $pers[$pid]=['duga_id'=>(string)$pid,'name'=>(string)$name,'kana'=>$kana,'updated_at'=>$now,'created_at'=>$now];
            }
        }
        return [array_values($cats), array_values($pers)];
    }

    private function collectLabelSeriesDirectorAndReview(array $items): array
    {
        $now=now(); $labels=[]; $series=[]; $directors=[]; $productPatch=[];

        foreach ($items as $row) {
            $x = $this->flattenItem($row);
            $pid = (string)($x['productid'] ?? $x['productId'] ?? $x['id'] ?? '');
            if (!$pid) continue;

            // label: 単体 or 配列 [{id,name}] 両対応
            $labs = $x['label'] ?? null;
            if ($labs) {
                $labs = is_array($labs) && array_is_list($labs) ? $labs : [$labs];
                foreach ($labs as $l) {
                    $lid = (string)($l['data']['id'] ?? $l['id'] ?? '');
                    $ln  = $l['data']['name'] ?? $l['name'] ?? null;
                    if ($lid && $ln) {
                        $labels[$lid] = ['duga_id'=>$lid,'name'=>$ln,'created_at'=>$now,'updated_at'=>$now];
                        // 代表ラベル1件だけパッチ（最初のものを使う）
                        $productPatch[$pid]['label_duga_id'] = $productPatch[$pid]['label_duga_id'] ?? $lid;
                    }
                }
            }

            // series: 単体 or 配列 [{id,name}] 両対応
            $sers = $x['series'] ?? null;
            if ($sers) {
                $sers = is_array($sers) && array_is_list($sers) ? $sers : [$sers];
                foreach ($sers as $s) {
                    $sid = (string)($s['data']['id'] ?? $s['id'] ?? '');
                    $sn  = $s['data']['name'] ?? $s['name'] ?? null;
                    if ($sid && $sn) {
                        $series[$sid] = ['duga_id'=>$sid,'name'=>$sn,'created_at'=>$now,'updated_at'=>$now];
                        $productPatch[$pid]['series_duga_id'] = $productPatch[$pid]['series_duga_id'] ?? $sid;
                    }
                }
            }

            // review: 配列 or オブジェクト両対応
            $rev = $x['review'] ?? null;
            if ($rev) {
                // 配列なら先頭要素、オブジェクトならそのまま
                $r = (is_array($rev) && array_is_list($rev)) ? ($rev[0] ?? []) : (array)$rev;

                $rating = $this->normalizeNumber($r['rating'] ?? null, true);
                $count  = $this->normalizeNumber($r['reviewer'] ?? null, false);

                if ($rating !== null) $productPatch[$pid]['review_rating'] = max(0, min(5, (float)$rating));
                if ($count  !== null) $productPatch[$pid]['review_count']  = max(0, (int)$count);
            }

            // directors: 既存ロジック（単体/配列両対応にしておく）
            $dirs = $x['director'] ?? null;
            if ($dirs) {
                $dirs = is_array($dirs) && array_is_list($dirs) ? $dirs : [$dirs];
                foreach ($dirs as $d) {
                    $did=(string)($d['data']['id']??$d['id']??''); $dn=$d['data']['name']??$d['name']??null;
                    if($did && $dn) $directors[$did]=['duga_id'=>$did,'name'=>$dn,'created_at'=>$now,'updated_at'=>$now];
                }
            }
        }

        return [array_values($labels), array_values($series), array_values($directors), $productPatch];
    }

    private function buildPerProductFinalIds(array $items, array $productIdMap, array $catIdMap, array $perIdMap): array
    {
        $finalCatIds = []; $finalPerIds = []; $touched = [];
        foreach ($items as $row) {
            $flat=$this->flattenItem($row);
            $pid = Arr::get($flat,'productid')??Arr::get($flat,'productId')??Arr::get($flat,'id');
            $prdId = $productIdMap[(string)$pid] ?? null;
            if(!$pid || !$prdId) continue;

            $touched[$prdId] = true;

            foreach ((array)Arr::get($flat,'category',[]) as $c) {
                $cid=Arr::get($c,'data.id')??Arr::get($c,'id');
                $catId=$catIdMap[(string)$cid]??null;
                if(!$cid||!$catId) continue;
                $finalCatIds[$prdId][$catId] = $catId;
            }
            foreach ((array)Arr::get($flat,'performer',[]) as $p) {
                $aid=Arr::get($p,'data.id')??Arr::get($p,'id');
                $perId=$perIdMap[(string)$aid]??null;
                if(!$aid||!$perId) continue;
                $finalPerIds[$prdId][$perId] = $perId;
            }
        }
        return [$finalCatIds, $finalPerIds, array_keys($touched)];
    }

    /* ---------- mapping ---------- */
    private function mapRowToSnake(array $row): array
    {
        return [
            'title'           => Arr::get($row,'title'),
            'original_title'  => Arr::get($row,'originaltitle'),
            'caption'         => Arr::get($row,'caption'),
            'maker'           => Arr::get($row,'makername'),
            'item_no'         => Arr::get($row,'itemno'),
            'price' => $this->parsePriceToInt(Arr::get($row,'price')),
            'volume'          => $this->numOrNull(Arr::get($row,'volume')),
            'release_date'    => $this->toDate(Arr::get($row,'releasedate')),
            'open_date'       => $this->toDate(Arr::get($row,'opendate')),
            'rating'        => ($r = $this->normalizeNumber(Arr::get($row,'rating'), true)) !== null ? (float)$r : null,
            'mylist_total'  => $this->getTotalFlexible($row,'mylist'),
            'ranking_total' => $this->getTotalFlexible($row,'ranking'),

            'url'             => Arr::get($row,'url'),
            'affiliate_url'   => Arr::get($row,'affiliateurl')?:Arr::get($row,'affiliateUrl'),

            // 画像（配列対応）
            'poster_small'    => $this->imageFromList($row,'posterimage','small'),
            'poster_medium'   => $this->imageFromList($row,'posterimage','medium'),
            'poster_large'    => $this->imageFromList($row,'posterimage','large'),
            'jacket_small'    => $this->imageFromList($row,'jacketimage','small'),
            'jacket_medium'   => $this->imageFromList($row,'jacketimage','medium'),
            'jacket_large'    => $this->imageFromList($row,'jacketimage','large'),
        ];
    }

    private function mapRowToCamel(array $row): array
    {
        return [
            'title'           => Arr::get($row,'title'),
            'originaltitle'   => Arr::get($row,'originaltitle'),
            'caption'         => Arr::get($row,'caption'),
            'makername'       => Arr::get($row,'makername'),
            'itemno'          => Arr::get($row,'itemno'),
            'price' => $this->parsePriceToInt(Arr::get($row,'price')),
            'volume'          => $this->numOrNull(Arr::get($row,'volume')),
            'releasedate'     => $this->toDate(Arr::get($row,'releasedate')),
            'opendate'        => $this->toDate(Arr::get($row,'opendate')),
            'rating'  => ($r = $this->normalizeNumber(Arr::get($row,'rating'), true)) !== null ? (float)$r : null,
            'mylist'  => $this->getTotalFlexible($row,'mylist'),
            'ranking' => $this->getTotalFlexible($row,'ranking'),
            'url'             => Arr::get($row,'url'),
            'affiliateurl'    => Arr::get($row,'affiliateurl')?:Arr::get($row,'affiliateUrl'),

            'poster_small'    => $this->imageFromList($row,'posterimage','small'),
            'poster_medium'   => $this->imageFromList($row,'posterimage','medium'),
            'poster_large'    => $this->imageFromList($row,'posterimage','large'),
            'jacket_small'    => $this->imageFromList($row,'jacketimage','small'),
            'jacket_medium'   => $this->imageFromList($row,'jacketimage','medium'),
            'jacket_large'    => $this->imageFromList($row,'jacketimage','large'),
        ];
    }

    private function pickUrl(array $row, array $paths): ?string
    {
        foreach ($paths as $p) {
            $v = Arr::get($row, $p);
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '' && $v !== '?') return $v;
            }
        }
        return null;
    }

    private function numOrNull($v): ?int {
        if ($v === null) return null;
        // 数字だけ抜く（"400円～" → 400）
        if (is_string($v)) {
            if (preg_match('/\d+/', $v, $m)) return (int)$m[0];
            return null;
        }
        return is_numeric($v) ? (int)$v : null;
    }

    private function firstTotal(array $row, string $key): ?int {
        // ranking.total / mylist.total の取り出し
        $a = Arr::get($row, $key);
        if (is_array($a)) {
            // 1) そのまま {total:..}
            $t = Arr::get($a, 'total');
            if ($t !== null) return $this->numOrNull($t);
            // 2) 配列 [ {total:..} , ... ]
            $first = $a[0] ?? null;
            if (is_array($first)) {
                $t = Arr::get($first, 'total');
                if ($t !== null) return $this->numOrNull($t);
                // data.total も一応
                $t = Arr::get($first, 'data.total');
                if ($t !== null) return $this->numOrNull($t);
            }
        }
        return $this->numOrNull($a);
    }

    private function imageFromList(array $row, string $key, string $size): ?string {
        // posterimage / jacketimage が配列のとき用
        $imgs = Arr::get($row, $key, []);
        if (!is_array($imgs)) return null;
        foreach ($imgs as $one) {
            if (!is_array($one)) continue;
            $v = Arr::get($one, $size) ?? Arr::get($one, $size === 'medium' ? 'midium' : $size);
            if (is_string($v) && $v !== '') return $v;
        }
        // 旧形式（連想オブジェクト）も試す
        $v = Arr::get($row, "{$key}.{$size}") ?? Arr::get($row, "{$key}.".($size==='medium'?'midium':$size));
        return is_string($v) ? $v : null;
    }

    private function firstOf(array $row, string $keyPath) {
        $v = Arr::get($row, $keyPath);
        if (is_array($v)) {
            // 先頭要素 or data ラップ
            if (isset($v['id']) || isset($v['name'])) return $v;
            $first = $v[0] ?? null;
            if (is_array($first)) return $first;
        }
        return $v;
    }

    private function getTotalFlexible(array $row, string $key): ?int {
        // そのまま数値
        $v = Arr::get($row, $key);
        $n = $this->normalizeNumber($v, false);
        if ($n !== null) return (int)$n;

        // 配列 [ { total: "..." } ]
        foreach (["{$key}.0.total", "{$key}.total"] as $path) {
            $n = $this->normalizeNumber(Arr::get($row, $path), false);
            if ($n !== null) return (int)$n;
        }
        return null;
    }

    private function normalizeNumber($v, bool $allowFloat = false): ?float {
        if ($v === null) return null;
        if (is_string($v)) {
            // カンマや全角などを落として数値判定
            $v = preg_replace('/[^\d\.\-]/u', '', $v);
        }
        if ($allowFloat) {
            return is_numeric($v) ? (float)$v : null;
        }
        return is_numeric($v) ? (float)$v : null; // intにしたい場合は後で (int) キャスト
    }

    private function parseYenPrice($raw): ?int
    {
        if ($raw === null) return null;
        if (!is_string($raw)) $raw = (string)$raw;

        $s = trim($raw);

        // よくある表記への先手
        if ($s === '' || $s === '-') return null;

        // “無料”相当（必要なら 0 に）
        if (preg_match('/^無\s*料$/u', $s)) return 0;

        // 全角数字 → 半角数字、全角カンマ → 半角
        if (function_exists('mb_convert_kana')) {
            $s = mb_convert_kana($s, 'n', 'UTF-8'); // 数字・記号を半角へ
        }
        $s = str_replace(['，'], [','], $s); // 全角カンマを半角へ

        // 先頭に現れる “数字（カンマ区切り可）” を1つ抜き出す
        if (!preg_match('/([0-9][0-9,]*)/', $s, $m)) {
            return null;
        }
        $n = str_replace(',', '', $m[1]); // 3桁区切り除去
        if ($n === '' || !ctype_digit($n)) return null;

        return (int)$n; // 円の整数
    }

    private function parsePriceToInt($v): ?int
    {
        if (is_null($v)) return null;
        if (is_int($v))  return $v;
        if (is_numeric($v)) return (int)$v;

        // "1,800円～" などから数字だけを抽出して int に
        $digits = preg_replace('/\D+/', '', (string)$v);
        return $digits !== '' ? (int)$digits : null;
    }

    private function pluckIdMap(string $table,string $col): array
    {
        return DB::table($table)->pluck('id',$col)->all();
    }
}