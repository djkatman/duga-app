<?php
// app/ViewModels/DugaProductView.php
namespace App\ViewModels;

class DugaProductView
{
    /** @var array<string,mixed> */
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /* ====== 汎用アクセス ====== */
    public function __get(string $key) { return $this->data[$key] ?? null; }
    public function toArray(): array   { return $this->data; }

    /* ====== API互換のgetter ====== */
    public function getProductid()     { return $this->data['productid'] ?? null; }
    public function getTitle()         { return $this->data['title'] ?? null; }
    public function getOriginalTitle() { return $this->data['original_title'] ?? null; }
    public function getCaption()       { return $this->data['caption'] ?? null; }
    public function getMakerName()     { return $this->data['maker'] ?? null; }
    public function getUrl()           { return $this->data['url'] ?? null; }
    public function getAffiliateUrl()  { return $this->data['affiliate_url'] ?? null; }
    public function getOpenDate()      { return $this->data['open_date'] ?? null; }
    public function getReleaseDate()   { return $this->data['release_date'] ?? null; }
    public function getItemNo()        { return $this->data['item_no'] ?? null; }
    public function getPrice()         { return $this->data['price'] ?? null; }
    public function getVolume()        { return $this->data['volume'] ?? null; }
    public function getRanking()       { return $this->data['ranking_total'] ?? null; }
    public function getMylist()        { return $this->data['mylist_total'] ?? null; }
    public function getRating()        { return $this->data['review_rating'] ?? null; }

    // 画像セット
    public function getPosterImage(): ?ImageSet {
        $s = $this->data['poster_small'] ?? null;
        $m = $this->data['poster_medium'] ?? null;
        $l = $this->data['poster_large'] ?? null;
        return ($s || $m || $l) ? new ImageSet($s, $m, $l) : null;
    }
    public function getJacketImage(): ?ImageSet {
        $s = $this->data['jacket_small'] ?? null;
        $m = $this->data['jacket_medium'] ?? null;
        $l = $this->data['jacket_large'] ?? null;
        return ($s || $m || $l) ? new ImageSet($s, $m, $l) : null;
    }

    // サンプル動画
    public function getSampleMovie(): ?SampleMovie {
        $s = $this->data['sample'] ?? null;
        if (is_array($s) && (isset($s['movie']) || isset($s['capture']))) {
            return new SampleMovie($s['movie'] ?? null, $s['capture'] ?? null);
        }
        return null;
    }

    /** Blade で使う: サムネイル配列（URLの一次元配列） */
    public function getThumbnail(): array
    {
        // よくあるキー名をカバー
        $v = $this->data['thumbnail']
            ?? $this->data['thumbnails']
            ?? $this->data['thumbs']
            ?? $this->data['sample_images']
            ?? null;

        $out = [];

        if (is_array($v)) {
            foreach ($v as $item) {
                // 文字列URLの配列
                if (is_string($item) && $item !== '') {
                    $out[] = $item;
                    continue;
                }

                // 連想配列の配列: ['image'|'url'|'src' => '...']
                if (is_array($item)) {
                    $u = $item['image'] ?? $item['url'] ?? $item['src'] ?? null;
                    if (is_string($u) && $u !== '') {
                        $out[] = $u;
                    }
                    continue;
                }

                // stdClass 等の配列: ->image / ->url / ->src
                if (is_object($item)) {
                    $u = $item->image ?? $item->url ?? $item->src ?? null;
                    if (is_string($u) && $u !== '') {
                        $out[] = $u;
                    }
                }
            }
        } elseif (is_string($v) && $v !== '') {
            // 単一URL
            $out[] = $v;
        }

        return $out;
    }

    /** Blade 互換: レビューオブジェクト（任意） */
    public function getReview(): ?Review
    {
        // review オブジェクトがそのまま入るケース
        $r = $this->data['review'] ?? null;

        $rating = null;
        $count  = null;

        if (is_array($r)) {
            $rating = $r['rating'] ?? $r['review_rating'] ?? null;
            $count  = $r['count']  ?? $r['reviewer']      ?? $r['review_count'] ?? null;
        }

        // 直下キーにもフォールバック
        $rating = $rating ?? ($this->data['review_rating'] ?? null);
        $count  = $count  ?? ($this->data['review_count']  ?? null);

        if ($rating === null && $count === null) return null;
        return new Review($rating, $count);
    }

    // 関連（単体）
    public function getLabel(): ?Entity {
        $x = $this->data['label'] ?? null;
        return (is_array($x) && isset($x['name'])) ? new Entity($x['id'] ?? null, $x['name']) : null;
    }
    public function getSeries(): ?Entity {
        $x = $this->data['series'] ?? null;
        return (is_array($x) && isset($x['name'])) ? new Entity($x['id'] ?? null, $x['name']) : null;
    }

    // 関連（配列）
    /** @return Entity[] */
    public function getCategory(): array {
        $arr = [];
        foreach (($this->data['categories'] ?? []) as $c) {
            $arr[] = new Entity($c['id'] ?? null, $c['name'] ?? null);
        }
        return $arr;
    }

    /** @return Person[] */
    public function getPerformer(): array {
        $arr = [];
        foreach (($this->data['performers'] ?? []) as $p) {
            $arr[] = new Person($p['id'] ?? null, $p['name'] ?? null, $p['kana'] ?? null);
        }
        return $arr;
    }

    /** @return Entity[] */
    public function getDirector(): array {
        $arr = [];
        foreach (($this->data['directors'] ?? []) as $d) {
            $arr[] = new Entity($d['id'] ?? null, $d['name'] ?? null);
        }
        return $arr;
    }

    /** @return SaleType[] */
    public function getSaleType(): array {
        $arr = [];
        foreach (($this->data['sale_types'] ?? []) as $s) {
            $arr[] = new SaleType($s['type'] ?? null, $s['price'] ?? null);
        }
        return $arr;
    }
}

/* ====== サブクラス群 ====== */
class ImageSet {
    public function __construct(
        private ?string $small,
        private ?string $medium,
        private ?string $large
    ) {}
    public function getSmall(){ return $this->small; }
    public function getMedium(){ return $this->medium; }
    public function getLarge(){ return $this->large; }
}

class SampleMovie {
    public function __construct(
        private ?string $movie,
        private ?string $capture
    ) {}
    public function getMovie(){ return $this->movie; }
    public function getCapture(){ return $this->capture; }
}

class Review {
    public function __construct(
        private $rating, // 数値 or 数値文字列
        private $reviewer // 件数（数値 or 数値文字列）
    ) {}
    public function getRating(){ return $this->rating; }
    public function getReviewer(){ return $this->reviewer; }
}

class Entity {
    public function __construct(
        private $id,
        private ?string $name
    ) {}
    public function getId(){ return $this->id; }
    public function getName(){ return $this->name; }
}

class Person extends Entity {
    public function __construct($id, ?string $name, private ?string $kana = null)
    { parent::__construct($id, $name); }
    public function getKana(){ return $this->kana; }
}

class SaleType {
    public function __construct(
        private ?string $type,
        private ?int $price
    ) {}
    public function getType(){ return $this->type; }
    public function getPrice(){ return $this->price; }
}