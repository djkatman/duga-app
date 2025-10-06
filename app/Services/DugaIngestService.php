<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use App\Models\DugaProduct;

class DugaIngestService
{
    public function fetchAndUpsertByProductId(string $productId): ?DugaProduct
    {
        $endpoint = config('duga.endpoint', 'https://affapi.duga.jp/search');

        $resp = Http::timeout(15)->retry(2, 300)->get($endpoint, [
            'appid'    => config('duga.app_id'),
            'agentid'  => config('duga.agent_id'),
            'version'  => config('duga.version', '1.2'),
            'format'   => config('duga.format', 'json'),
            'adult'    => config('duga.adult', 1),
            'bannerid' => config('duga.banner_id'),
            'keyword'  => $productId,
            'hits'     => 1,
            'offset'   => 1,
        ]);

        if ($resp->failed()) return null;

        $items = $this->extractItems($resp->json() ?? []);
        $row   = $items[0] ?? null;
        if (!$row) return null;

        return $this->upsertOne($row, $productId);
    }

    /** DugaSyncと同じロジックを1点集約（必要に応じて共通化） */
    public function upsertOne(array $item, string $productId): ?DugaProduct
    {
        $x   = $this->flattenItem($item);
        $now = now();

        // --- products（スネーク想定。キャメル版が必要なら分岐）---
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
            $pidLocal = DB::table('duga_products')->where('productid',$productId)->value('id');

            // label
            if ($l = (Arr::get($x,'label.0') ?: Arr::get($x,'label'))) {
                $lid=(string)(Arr::get($l,'id') ?? Arr::get($l,'data.id'));
                $ln =Arr::get($l,'name') ?? Arr::get($l,'data.name');
                if ($lid && $ln) {
                    DB::table('duga_labels')->upsert([[
                        'duga_id'=>$lid,'name'=>$ln,'created_at'=>$now,'updated_at'=>$now
                    ]], ['duga_id'], ['name','updated_at']);
                    $labelId = DB::table('duga_labels')->where('duga_id',$lid)->value('id');
                    DB::table('duga_products')->where('id',$pidLocal)->update(['label_id'=>$labelId,'updated_at'=>$now]);
                }
            }

            // series
            if ($s = (Arr::get($x,'series.0') ?: Arr::get($x,'series'))) {
                $sid=(string)(Arr::get($s,'id') ?? Arr::get($s,'data.id'));
                $sn =Arr::get($s,'name') ?? Arr::get($s,'data.name');
                if ($sid && $sn) {
                    DB::table('duga_series')->upsert([[
                        'duga_id'=>$sid,'name'=>$sn,'created_at'=>$now,'updated_at'=>$now
                    ]], ['duga_id'], ['name','updated_at']);
                    $seriesId = DB::table('duga_series')->where('duga_id',$sid)->value('id');
                    DB::table('duga_products')->where('id',$pidLocal)->update(['series_id'=>$seriesId,'updated_at'=>$now]);
                }
            }

            // review
            if ($rv = (Arr::get($x,'review.0') ?: Arr::get($x,'review'))) {
                $rating = $this->numOrNull(Arr::get($rv,'rating'));
                $count  = $this->intOrNull(Arr::get($rv,'reviewer'));
                DB::table('duga_products')->where('id',$pidLocal)->update([
                    'review_rating'=>$rating,'review_count'=>$count,'updated_at'=>$now
                ]);
            }

            // sample movie
            if ($samp = (Arr::get($x,'samplemovie.0.midium') ?: Arr::get($x,'samplemovie.0.medium'))) {
                DB::table('duga_product_samples')->upsert([[
                    'duga_product_id'=>$pidLocal,
                    'movie_url'=>Arr::get($samp,'movie'),
                    'capture_url'=>Arr::get($samp,'capture'),
                    'created_at'=>$now,'updated_at'=>$now
                ]], ['duga_product_id','movie_url'], ['capture_url','updated_at']);
            }

            // thumbnails
            $thumbs = (array)Arr::get($x,'thumbnail',[]);
            $rowsT=[]; $i=0;
            foreach ($thumbs as $t) {
                $u = Arr::get($t,'image'); if(!$u) continue;
                $rowsT[] = [
                    'duga_product_id'=>$pidLocal,
                    'thumb_url'=>$u,
                    'full_url'=>str_replace('/noauth/scap/','/cap/',$u),
                    'sort_order'=>$i++,
                    'created_at'=>$now,'updated_at'=>$now,
                ];
            }
            if ($rowsT) {
                DB::table('duga_product_thumbnails')->upsert(
                    $rowsT, ['duga_product_id','thumb_url'], ['full_url','sort_order','updated_at']
                );
            }

            // sale types
            $rowsS=[];
            foreach ((array)Arr::get($x,'saletype',[]) as $s) {
                $d = Arr::get($s,'data', $s);
                $type = Arr::get($d,'type'); if(!$type) continue;
                $price= $this->intOrNull(Arr::get($d,'price'));
                $rowsS[]=['duga_product_id'=>$pidLocal,'type'=>$type,'price'=>$price,
                          'created_at'=>$now,'updated_at'=>$now];
            }
            if ($rowsS) {
                DB::table('duga_product_sale_types')->upsert(
                    $rowsS, ['duga_product_id','type'], ['price','updated_at']
                );
            }

            return DugaProduct::with([
                'categories:id,name','performers:id,name,kana',
                'label:id,name','series:id,name',
                'directors:id,name','samples','thumbnails','saleTypes',
            ])->find($pidLocal);
        });
    }

    /* ====== 共通小物 & 形状吸収 ====== */
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
    private function toDate($v){ try{ return $v? Carbon::parse($v)->toDateString():null; }catch(\Throwable){ return null; } }
    private function intOrNull($v){ if ($v===null) return null; $v=preg_replace('/\D+/', '', (string)$v); return $v===''?null:(int)$v; }
    private function numOrNull($v){ if($v===null||$v==='') return null; return is_numeric($v)?0+$v:null; }
}