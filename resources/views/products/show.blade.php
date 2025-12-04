{{-- resources/views/products/show.blade.php（安全版＋視聴ガイド＆FAQ追加） --}}
@extends('layouts.app')

@php
  // ===== ユーティリティ（安全版） =====
  $has  = fn($obj, $m) => is_object($obj) && method_exists($obj, $m);
  $hasP = fn($obj, $p) => is_object($obj) && property_exists($obj, $p);

  // HTMLエンティティをデコードするヘルパー（&#039; → ' など）
  $dec = fn($v) => is_string($v) ? html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $v;

  // $item が未定義 / null の場合のガード
  $item = $item ?? null;

  // メイン情報（すべて $has 経由）
  $titleRaw      = $has($item,'getTitle')         ? $item->getTitle()         : '';
  $origTitleRaw  = $has($item,'getOriginaltitle') ? $item->getOriginaltitle()
                    : ($has($item,'getOriginalTitle') ? $item->getOriginalTitle() : '');
  $makerRaw      = $has($item,'getMakername')     ? $item->getMakername()
                    : ($has($item,'getMakerName') ? $item->getMakerName() : '');
  $caption      = $has($item,'getCaption')       ? $item->getCaption()       : '';
  $url          = $has($item,'getUrl')           ? $item->getUrl()           : '';
  $affUrl       = $has($item,'getAffiliateurl')  ? $item->getAffiliateurl()
                  : ($has($item,'getAffiliateUrl') ? $item->getAffiliateUrl() : '');
  $openDate     = $has($item,'getOpendate')      ? $item->getOpendate()
                  : ($has($item,'getOpenDate') ? $item->getOpenDate() : '');
  $releaseDate  = $has($item,'getReleasedate')   ? $item->getReleasedate()   : ($has($item,'getReleaseDate') ? $item->getReleaseDate() : '');
  $itemNo       = $has($item,'getItemno')        ? $item->getItemno()
                  : ($has($item,'getItemNo') ? $item->getItemNo() : '');
  $price        = $has($item,'getPrice')         ? $item->getPrice()         : null;
  $volume       = $has($item,'getVolume')        ? $item->getVolume()        : '';

  // ★ デコードしてから使う
  $title     = $dec($titleRaw);
  $origTitle = $dec($origTitleRaw);
  $maker     = $dec($makerRaw);

  // ランキング/マイリスト（property_exists は is_object 前提の $hasP 経由）
  $rankingTotal = $has($item,'getRanking') ? $item->getRanking()
                  : ($hasP($item,'rankingTotal') ? $item->rankingTotal : null);
  $mylistTotal  = $has($item,'getMylist')  ? $item->getMylist()
                  : ($hasP($item,'mylistTotal')  ? $item->mylistTotal  : null);

  // 画像（中間オブジェクトにも is_object ガード）
  $poster = $has($item,'getPosterImage') ? $item->getPosterImage() : null;
  $posterSmall  = (is_object($poster) && method_exists($poster,'getSmall'))  ? $poster->getSmall()  : null;
  $posterMedium = (is_object($poster) && (method_exists($poster,'getMedium') || method_exists($poster,'getMidium')))
                  ? (method_exists($poster,'getMedium') ? $poster->getMedium() : $poster->getMidium())
                  : null;
  $posterLarge  = (is_object($poster) && method_exists($poster,'getLarge'))  ? $poster->getLarge()  : null;

  $jacket = $has($item,'getJacketImage') ? $item->getJacketImage() : null;
  $jSmall  = (is_object($jacket) && method_exists($jacket,'getSmall'))  ? $jacket->getSmall()  : null;
  $jMedium = (is_object($jacket) && (method_exists($jacket,'getMedium') || method_exists($jacket,'getMidium')))
             ? (method_exists($jacket,'getMedium') ? $jacket->getMedium() : $jacket->getMidium())
             : null;
  $jLarge  = (is_object($jacket) && method_exists($jacket,'getLarge'))  ? $jacket->getLarge()  : null;

  $thumbs = $has($item,'getThumbnail') ? (array) $item->getThumbnail() : [];

  $sample = $has($item,'getSampleMovie') ? $item->getSampleMovie() : null;
  $sampleMovie   = (is_object($sample) && method_exists($sample,'getMovie'))   ? $sample->getMovie()   : null;
  $sampleCapture = (is_object($sample) && method_exists($sample,'getCapture')) ? $sample->getCapture() : null;

  // 関連
  $label = $has($item,'getLabel') ? $item->getLabel() : null;
  $series = $has($item,'getSeries') ? $item->getSeries() : null;
  $categories = $has($item,'getCategory') ? (array) $item->getCategory() : [];
  $performers = $has($item,'getPerformer') ? (array) $item->getPerformer() : [];
  $directors  = $has($item,'getDirector')  ? (array) $item->getDirector()  : [];
  $saleTypes  = $has($item,'getSaleType')  ? (array) $item->getSaleType()  : [];
  $review     = $has($item,'getReview')    ? $item->getReview() : null;

  // レビューの星用（安全化）
  $rawRating =
    (is_object($review) && method_exists($review,'getRating')) ? $review->getRating()
    : ($has($item,'getRating') ? $item->getRating() : null);
  $rating = is_numeric($rawRating) ? max(0, min(5, (float)$rawRating)) : null;
  $reviewer = (is_object($review) && method_exists($review,'getReviewer')) ? $review->getReviewer() : null;
  $reviewCount = is_numeric($reviewer) ? (int)$reviewer : null;

  // ===== SEO 変数 =====
  $siteName  = 'DUGAサンプル動画見放題';
  $name      = $title ?: '作品詳細';
  $desc      = trim(mb_strimwidth(preg_replace("/\s+/u", ' ', (string)$caption), 0, 180, '…'));
  if ($desc === '') {
    $desc = $maker ? "{$maker} の作品。サンプル動画・画像、出演者・カテゴリ情報を掲載。" : "サンプル動画・画像、出演者・カテゴリ情報を掲載。";
  }
  $canonical = url()->current();
  $ogImage   = $posterLarge ?? $posterMedium ?? $posterSmall ?? $jLarge ?? $jMedium ?? $jSmall ?? asset('favicon.ico');

  // 価格・通貨
  $priceValue = is_numeric($price) ? (float)$price : null;
  $currency   = 'JPY';

  // saleTypes の価格配列を収集（要 is_object）
  $salePrices = [];
  $saleOffers = [];
  foreach ($saleTypes as $s) {
    if (!is_object($s)) continue;
    $stype  = method_exists($s,'getType')  ? $s->getType()  : null;
    $sprice = method_exists($s,'getPrice') ? (int)$s->getPrice() : null;
    if (is_numeric($sprice)) {
      $salePrices[] = (int)$sprice;
      $saleOffers[] = [
        '@type'         => 'Offer',
        'name'          => $stype,
        'price'         => number_format((float)$sprice, 0, '.', ''),
        'priceCurrency' => $currency,
        'url'           => $affUrl ?: $canonical,
        'availability'  => 'https://schema.org/InStock',
      ];
    }
  }
  $lowPrice  = !empty($salePrices) ? min($salePrices) : null;
  $highPrice = !empty($salePrices) ? max($salePrices) : null;

  // ISO8601
  $releaseISO  = $releaseDate ? \Carbon\Carbon::parse($releaseDate)->toDateString() : null;
  $durationISO = (is_numeric($volume) && (int)$volume > 0) ? ('PT'.(int)$volume.'M') : null;

  // カテゴリ名
  $categoryNames = [];
  foreach ($categories as $c) {
    if (is_object($c) && method_exists($c,'getName')) $categoryNames[] = $c->getName();
  }

  // レーベル名
  $labelName = (is_object($label) && method_exists($label,'getName')) ? $label->getName() : null;

  // 出演者/監督（JSON-LD用）
  $actorList = [];
  foreach ($performers as $p) {
    if (!is_object($p)) continue;
    $nm = method_exists($p,'getName') ? $p->getName() : null;
    if ($nm) $actorList[] = ['@type'=>'Person','name'=>$nm];
  }
  $directorList = [];
  foreach ($directors as $d) {
    if (!is_object($d)) continue;
    $nm = method_exists($d,'getName') ? $d->getName() : null;
    if ($nm) $directorList[] = ['@type'=>'Person','name'=>$nm];
  }

  // offers の決定ロジック
  $offers = null;
  if (!is_null($priceValue)) {
    $offers = [
      '@type'         => 'Offer',
      'price'         => number_format($priceValue, 0, '.', ''),
      'priceCurrency' => $currency,
      'url'           => $affUrl ?: $canonical,
      'availability'  => 'https://schema.org/InStock',
    ];
  } elseif (!empty($salePrices)) {
    $offers = [
      '@type'         => 'AggregateOffer',
      'offerCount'    => count($salePrices),
      'lowPrice'      => number_format((float)$lowPrice, 0, '.', ''),
      'priceCurrency' => $currency,
      'highPrice'     => number_format((float)$highPrice, 0, '.', ''),
      'url'           => $affUrl ?: $canonical,
      // 'offers'     => $saleOffers, // 必要なら展開
    ];
  }

  // aggregateRating
  $aggregateRating = null;
  if (!is_null($rating) && !is_null($reviewCount)) {
    $aggregateRating = [
      '@type'       => 'AggregateRating',
      'ratingValue' => $rating,
      'bestRating'  => 5,
      'worstRating' => 0,
      'ratingCount' => $reviewCount,
    ];
  }

  // Product JSON-LD は「offers か aggregateRating がある時だけ」
  $shouldEmitProductLd = !is_null($offers) || !is_null($aggregateRating);

  if ($shouldEmitProductLd) {
    $productLd = [
      '@context'      => 'https://schema.org',
      '@type'         => 'Product',
      'name'          => $name,
      'description'   => $desc,
      'image'         => array_values(array_filter([$posterLarge,$posterMedium,$posterSmall,$jLarge,$jMedium,$jSmall])),
      'url'           => $canonical,
      'brand'         => $labelName ? ['@type'=>'Brand','name'=>$labelName] : ($maker ? ['@type'=>'Brand','name'=>$maker] : null),
      'category'      => !empty($categoryNames) ? implode(', ', $categoryNames) : null,
      'releaseDate'   => $releaseISO,
      'productionCompany' => $maker ?: null,
      'actor'         => !empty($actorList) ? $actorList : null,
      'director'      => !empty($directorList) ? $directorList : null,
      'duration'      => $durationISO,
      'offers'        => $offers,
      'aggregateRating'=> $aggregateRating,
    ];
    $productLd = array_filter($productLd, fn($v) => !is_null($v));
  }

  // パンくず JSON-LD（カテゴリ1つ目があれば中継）
  $firstCat   = (!empty($categories) && isset($categories[0]) && is_object($categories[0]) && method_exists($categories[0],'getId')) ? $categories[0] : null;
  $crumbsLd   = [
    [
      '@type'   => 'ListItem',
      'position'=> 1,
      'name'    => 'トップ',
      'item'    => url('/')
    ]
  ];
  $pos = 2;
  if ($firstCat && method_exists($firstCat,'getName') && method_exists($firstCat,'getId')) {
    $crumbsLd[] = [
      '@type'   => 'ListItem',
      'position'=> $pos++,
      'name'    => 'カテゴリ: '.$firstCat->getName(),
      'item'    => route('browse.filter',['type'=>'category','id'=>$firstCat->getId()])
    ];
  }
  $crumbsLd[] = [
    '@type'   => 'ListItem',
    'position'=> $pos,
    'name'    => $name,
    'item'    => $canonical
  ];
  $breadcrumbLd = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => $crumbsLd
  ];

  // VideoObject JSON-LD 準備
  $thumbList = array_values(array_filter([$posterLarge,$posterMedium,$posterSmall,$jLarge,$jMedium,$jSmall]));
  $uploadDateISO = $releaseISO ?: ($openDate ? \Carbon\Carbon::parse($openDate)->toDateString() : null);

  // VideoObjectを出す条件：タイトル＋説明＋サムネが取れているとき
  $shouldEmitVideoLd = ($name && $desc && !empty($thumbList));

  if ($shouldEmitVideoLd) {
      $videoLd = [
        '@context'       => 'https://schema.org',
        '@type'          => 'VideoObject',
        'name'           => $name,
        'description'    => $desc,
        'thumbnailUrl'   => $thumbList,              // 1つでもOK、配列で複数可
        'uploadDate'     => $uploadDateISO,          // ISO8601 (YYYY-MM-DD)
        'duration'       => $durationISO,            // 例: PT120M
        'url'            => $canonical,
        // サンプル動画がある場合のみ（無ければ自動でdrop）
        'contentUrl'     => $sampleMovie ?: null,    // 直リンク or mp4
        'embedUrl'       => $sampleMovie ?: null,    // 直接再生できるURLなら同じでも可
        'actor'          => !empty($actorList)    ? $actorList    : null,
        'director'       => !empty($directorList) ? $directorList : null,
        'genre'          => !empty($categoryNames) ? $categoryNames : null,
        'publisher'      => [
          '@type' => 'Organization',
          'name'  => $siteName,
          'logo'  => [
            '@type' => 'ImageObject',
            'url'   => asset('favicon.ico'),
          ],
        ],
        'isFamilyFriendly' => false,
        // 視聴アクション（公式 or アフィ先へ）
        'potentialAction' => [
          '@type' => 'WatchAction',
          'target'=> $affUrl ?: ($url ?: $canonical),
        ],
        // 連動していれば評価も付与
        'aggregateRating' => $aggregateRating ?: null,
      ];

      // nullを除去（第2引数でnullのみ落とす）
      $videoLd = array_filter($videoLd, fn($v) => !is_null($v));
    }

  // SEO タイトル
  $seoTitle = $title ?: '作品詳細';
  if (!empty($performers) && isset($performers[0]) && is_object($performers[0]) && method_exists($performers[0],'getName')) {
    $seoTitle .= '（'.$performers[0]->getName().' 出演）';
  } elseif (!empty($categories) && isset($categories[0]) && is_object($categories[0]) && method_exists($categories[0],'getName')) {
    $seoTitle .= '｜'.$categories[0]->getName();
  }
  $seoTitle .= ' | 無料サンプル動画あり | DUGAサンプル動画見放題';

    // 作品固有の自動リード文（独自テキスト）
  // --- 主なカテゴリ・出演者名を整理 ---
  $primaryCategoryName = $categoryNames[0] ?? null;

  $performerNames = [];
  foreach ($performers as $p) {
    if (!is_object($p)) continue;
    $nm = method_exists($p,'getName') ? $p->getName() : null;
    if ($nm) $performerNames[] = $nm;
  }
  $performerNames = array_values(array_unique($performerNames));
  $mainPerformerText = '';
  if (!empty($performerNames)) {
    if (count($performerNames) === 1) {
      $mainPerformerText = $performerNames[0];
    } elseif (count($performerNames) === 2) {
      $mainPerformerText = $performerNames[0].'・'.$performerNames[1];
    } else {
      $mainPerformerText = $performerNames[0].' ほか';
    }
  }

  // --- 自動生成する本文（本サイト独自の説明）---
  $autoLeadText = '';
  if ($title) {
    $parts = [];

    // 1行目：作品の基本情報
    if ($maker && $primaryCategoryName && $mainPerformerText) {
      $parts[] = "『{$title}』は、{$maker}が手掛ける{$primaryCategoryName}作品で、{$mainPerformerText}が出演するタイトルです。";
    } elseif ($maker && $mainPerformerText) {
      $parts[] = "『{$title}』は、{$maker}制作の作品で、{$mainPerformerText}がメインで登場します。";
    } elseif ($maker && $primaryCategoryName) {
      $parts[] = "『{$title}』は、{$maker}がリリースする{$primaryCategoryName}ジャンルのタイトルです。";
    } elseif ($primaryCategoryName) {
      $parts[] = "『{$title}』は、{$primaryCategoryName}ジャンルに分類される作品です。";
    } else {
      $parts[] = "『{$title}』は、DUGAで配信されているデジタル動画作品です。";
    }

    // 2行目：尺・公開時期など
    if ($volume && $releaseDate) {
      $parts[] = "収録時間は約{$volume}分で、{$releaseDate}ごろにリリースされたタイトルとなっています。";
    } elseif ($volume) {
      $parts[] = "収録時間は約{$volume}分で、じっくりと楽しめるボリューム感です。";
    } elseif ($releaseDate) {
      $parts[] = "{$releaseDate}ごろにリリースされた作品で、ファンからも長く視聴されているタイトルです。";
    }

    // 3行目：カテゴリ・出演者を絡めた視聴イメージ
    if ($primaryCategoryName && $mainPerformerText) {
      $parts[] = "{$primaryCategoryName}系の作品や、{$mainPerformerText}出演作が好きな方にとって、チェックしておきたい1本と言えるでしょう。";
    } elseif ($primaryCategoryName) {
      $parts[] = "{$primaryCategoryName}ジャンルの作品を探している方におすすめです。";
    } elseif ($mainPerformerText) {
      $parts[] = "{$mainPerformerText}の出演作をまとめて視聴したい方にも、ラインナップの一つとして押さえておきたい作品です。";
    }

    // 4行目：サンプル動画・購入導線への言及
    if ($sampleMovie || $sampleCapture) {
      $parts[] = "まずは無料サンプル動画で雰囲気を確認してから、本編の購入や視聴プランを検討してみてください。";
    } else {
      $parts[] = "作品ページでは詳細な内容紹介や関連タイトルも確認できるので、興味があればチェックしてみましょう。";
    }

    $autoLeadText = implode("\n", array_filter($parts));
  }
@endphp

@section('title', $seoTitle)

@section('meta')
  <meta name="description" content="{{ $desc }}">
  <link rel="canonical" href="{{ $canonical }}">

  <meta property="og:site_name" content="{{ $siteName }}">
  <meta property="og:type" content="product">
  <meta property="og:title" content="{{ $seoTitle }}">
  <meta property="og:description" content="{{ $desc }}">
  <meta property="og:url" content="{{ $canonical }}">
  <meta property="og:image" content="{{ $ogImage }}">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $seoTitle }}">
  <meta name="twitter:description" content="{{ $desc }}">
  <meta name="twitter:image" content="{{ $ogImage }}">

  @if(!empty($shouldEmitProductLd) && $shouldEmitProductLd)
    <script type="application/ld+json">{!! json_encode($productLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES, 512) !!}</script>
  @endif

  @if(!empty($shouldEmitVideoLd) && $shouldEmitVideoLd)
    <script type="application/ld+json">{!! json_encode($videoLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES, 512) !!}</script>
  @endif
  <script type="application/ld+json">{!! json_encode($breadcrumbLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES, 512) !!}</script>

  {{-- ▼ 追加：FAQPage JSON-LD（視聴ガイドのQ&A） --}}
  @php
    $faqPairs = [
      [
        'q' => 'DUGAの作品はどの端末で視聴できますか？',
        'a' => 'パソコン・スマホ・タブレットに対応し、ブラウザからストリーミング再生できます。購入済み作品はマイページの「購入履歴」からいつでも再生できます。'
      ],
      [
        'q' => '無料サンプルはありますか？',
        'a' => 'ほとんどの作品で無料サンプル動画が用意されています。画質や内容を確認してから購入や見放題プランをご検討ください。'
      ],
    ];
    $faqLd = [
      '@context' => 'https://schema.org',
      '@type'    => 'FAQPage',
      'mainEntity' => array_map(function ($qa) {
        return [
          '@type'          => 'Question',
          'name'           => $qa['q'],
          'acceptedAnswer' => [
            '@type' => 'Answer',
            'text'  => $qa['a'],
          ],
        ];
      }, $faqPairs),
    ];
  @endphp
  <script type="application/ld+json">{!! json_encode($faqLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES, 512) !!}</script>

    @if($affUrl)
        {{-- 画面下固定のCTAバー --}}
        <div id="stickyCta"
            class="fixed inset-x-0 bottom-0 z-40 bg-white/95 border-t border-gray-200 shadow-lg backdrop-blur
                    translate-y-full opacity-0 pointer-events-none
                    transition-all duration-300">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-3">

            {{-- 左側：作品名などのテキスト --}}
            <div class="min-w-0 flex-1">
                <p class="text-[11px] text-gray-500 hidden sm:block">当サイトのリンクの一部は広告（アフィリエイトリンク）です。</p>
                <p class="text-xs sm:text-sm font-medium text-gray-800 truncate">
                『{{ $title }}』をDUGA公式サイトで視聴できます。
                </p>
                @if(!empty($performerNames))
                <p class="text-[11px] text-gray-600 hidden sm:block">
                    主な出演：{{ implode('、', array_slice($performerNames, 0, 2)) }}
                </p>
                @endif
            </div>

            {{-- 右側：ボタン --}}
            <div class="shrink-0">
                <a href="{{ $affUrl }}"
                target="_blank"
                rel="sponsored nofollow noopener"
                class="inline-flex items-center justify-center px-4 sm:px-6 py-2.5 rounded-full text-xs sm:text-sm font-bold
                        text-white bg-gradient-to-r from-rose-500 via-pink-500 to-red-600
                        shadow-lg hover:shadow-xl hover:brightness-110 hover:scale-[1.03]
                        transition-transform duration-200">
                🎬 今すぐ公式で視聴する
                </a>
            </div>
            </div>
        </div>
    @endif

@section('content')
  @php
    $firstCatForUi = (!empty($categories) && isset($categories[0]) && is_object($categories[0]) && method_exists($categories[0],'getName')) ? $categories[0] : null;
    $crumbs = [
      ['label' => 'トップ', 'url' => route('home')],
    ];
    if ($firstCatForUi && method_exists($firstCatForUi,'getId')) {
      $crumbs[] = [
        'label' => 'カテゴリ: '.$firstCatForUi->getName(),
        'url'   => route('browse.filter',['type'=>'category','id'=>$firstCatForUi->getId()]),
      ];
    }
    $crumbs[] = ['label' => $title ?: '作品詳細'];
  @endphp
  @include('partials.breadcrumbs', ['crumbs' => $crumbs])

  <div class="mb-4 flex items-center justify-between">
    <p class="mt-3 mb-3 text-xs text-gray-500">※ 当サイトのリンクの一部は広告（アフィリエイトリンク）です。</p>
  </div>

  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">作品詳細</h1>
    <a href="{{ url()->previous() }}" class="text-sm text-indigo-600 hover:underline">← 一覧に戻る</a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    {{-- 左カラム --}}
    <div class="space-y-4">
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="aspect-[12/7] bg-gray-100">
          @if($posterLarge || $posterMedium || $posterSmall)
            <img
              class="h-full w-full object-cover"
              src="{{ $posterLarge ?? $posterMedium ?? $posterSmall }}"
              alt="{{ $title }} のポスター"
              width="1200" height="700"
              loading="eager" decoding="async" fetchpriority="high">
          @elseif($jLarge || $jMedium || $jSmall)
            <img
              class="h-full w-full object-cover"
              src="{{ $jLarge ?? $jMedium ?? $jSmall }}"
              alt="{{ $title }} のジャケット"
              width="1200" height="700"
              loading="eager" decoding="async" fetchpriority="high">
          @else
            <div class="h-full w-full flex items-center justify-center text-gray-400" role="img" aria-label="画像なし">No Image</div>
          @endif
        </div>

        <div class="p-3 text-xs text-gray-500">
          @if($jSmall || $jMedium || $jLarge)
            <div class="mt-1">パッケージ:
              @if($jSmall)<a class="underline" href="{{ $jSmall }}" target="_blank" rel="noopener">S</a>@endif
              @if($jMedium)<span class="mx-1">/</span><a class="underline" href="{{ $jMedium }}" target="_blank" rel="noopener">M</a>@endif
              @if($jLarge)<span class="mx-1">/</span><a class="underline" href="{{ $jLarge }}" target="_blank" rel="noopener">L</a>@endif
            </div>
          @endif
        </div>
      </div>

      @if($sampleMovie || $sampleCapture)
        <div class="bg-white rounded-lg shadow p-3 space-y-3">
          <h2 class="text-sm font-semibold">サンプル動画</h2>
          @if($sampleMovie)
            <video controls preload="none" poster="{{ $sampleCapture }}" class="w-full rounded">
              <source src="{{ $sampleMovie }}" type="video/mp4">
            </video>
          @endif
        </div>
      @endif

      @if(!empty($thumbs))
        <div class="bg-white rounded-lg shadow p-3">
          <h2 class="text-sm font-semibold mb-2">サンプル画像</h2>
          <div id="thumbGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
            @foreach($thumbs as $idx => $t)
              @php $fullUrl = is_string($t) ? str_replace('/noauth/scap/', '/cap/', $t) : ''; @endphp
              @if($fullUrl)
                <button type="button"
                  class="group block rounded overflow-hidden bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  data-index="{{ $idx }}"
                  data-src="{{ $fullUrl }}"
                  aria-label="サンプル画像を拡大表示">
                  <img src="{{ $t }}"
                    alt="{{ $title }} のサンプル画像 {{ $idx + 1 }}"
                    width="480" height="270"
                    loading="lazy" decoding="async"
                    class="w-full aspect-video object-cover transition-transform duration-200 group-hover:scale-[1.02]">
                </button>
              @endif
            @endforeach
          </div>
        </div>
      @endif
    </div>

    {{-- 右カラム --}}
    <div class="md:col-span-2 space-y-6">
      <div class="bg-white rounded-lg shadow p-4">
        <h1 class="text-2xl font-bold">{{ $title }}</h1>
        @if($origTitle)
          <div class="text-sm text-gray-500 mt-1">原題：{{ $origTitle }}</div>
        @endif

        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
          @if($releaseDate) <div><span class="text-gray-500">発売日：</span>{{ $releaseDate }}</div>@endif
          @if($openDate)    <div><span class="text-gray-500">公開日：</span>{{ $openDate }}</div>@endif
          @if($itemNo)      <div><span class="text-gray-500">品番：</span>{{ $itemNo }}</div>@endif
          @if($maker)       <div><span class="text-gray-500">メーカー：</span>{{ $maker }}</div>@endif
          @if(!is_null($price)) <div><span class="text-gray-500">価格：</span>¥{{ $price }}</div>@endif
          @if($volume)      <div><span class="text-gray-500">収録：</span>{{ $volume }}分</div>@endif
          @if($rankingTotal !== null) <div><span class="text-gray-500">ランキング：</span>{{ $rankingTotal }}位</div>@endif
          @if($mylistTotal  !== null) <div><span class="text-gray-500">マイリスト：</span>{{ $mylistTotal }}件</div>@endif
        </div>

        @if($caption)
          <div class="mt-4 whitespace-pre-line leading-relaxed">{{ $caption }}</div>
        @endif

        {{-- ▼ 作品ごとの自動生成テキスト（本サイト独自の解説ゾーン） --}}
        @if(!empty($autoLeadText))
          <section class="mt-6 border-t border-dashed border-gray-200 pt-4 space-y-2">
            <h2 class="text-base font-semibold text-gray-800">
              この作品の見どころ・おすすめポイント
            </h2>
            <p class="text-sm leading-relaxed whitespace-pre-line text-gray-800">
              {{ $autoLeadText }}
            </p>

            @if(!empty($performerNames) || !empty($categoryNames))
              <ul class="mt-2 text-xs text-gray-600 list-disc list-inside space-y-1">
                @if(!empty($performerNames))
                  <li>主な出演者：{{ implode('、', array_slice($performerNames, 0, 3)) }}</li>
                @endif
                @if(!empty($categoryNames))
                  <li>主なカテゴリ：{{ implode('／', array_slice($categoryNames, 0, 3)) }}</li>
                @endif
                @if($maker)
                  <li>メーカー：{{ $maker }}</li>
                @endif
              </ul>
            @endif
          </section>
        @endif

        <div class="mt-6 flex justify-center">
          @if($affUrl)
            <a href="{{ $affUrl }}"
              target="_blank"
              rel="sponsored nofollow noopener"
              class="relative block w/full md:w-auto px-8 py-4 text-center text-lg font-extrabold
                      text-white rounded-2xl shadow-xl bg-gradient-to-r from-rose-500 via-pink-500 to-red-600
                      transition-all duration-300 ease-out hover:scale-110 hover:shadow-2xl hover:brightness-110
                      animate-[pulse_2s_infinite]">
              🎬 <span class="ml-1">今すぐ公式サイトで見る</span>
              <span class="absolute inset-0 rounded-2xl bg-white/10 opacity-0 hover:opacity-20 transition"></span>
            </a>
          @endif
        </div>
      </div>

      {{-- ▼ 追加：視聴ガイド --}}
      <section class="bg-white rounded-lg shadow p-4 space-y-4">
        <h2 class="text-lg font-semibold">DUGAアダルト動画の視聴ガイド【初めての方向け】</h2>

        <div class="space-y-3 text-sm leading-relaxed">
          <div>
            <h3 class="font-semibold text-gray-800">1. 作品を探す</h3>
            <p class="text-gray-700">
              検索バーに「女優名」「ジャンル」「シリーズ名」を入力し、表示された作品を
              <span class="font-medium">人気順・新着順</span>で並び替えると話題作を効率よく見つけられます。
            </p>
          </div>

          <div>
            <h3 class="font-semibold text-gray-800">2. サンプル動画を見る</h3>
            <p class="text-gray-700">
              多くの作品で<span class="font-medium">無料サンプル再生</span>が可能です。画質や出演者、内容を確認し、
              気になればお気に入りに保存して比較検討しましょう。
            </p>
          </div>

          <div>
            <h3 class="font-semibold text-gray-800">3. 視聴プランを選ぶ</h3>
            <p class="text-gray-700">
              短期で1本だけ見たい場合は<span class="font-medium">単品購入</span>、継続的に楽しみたい場合は
              <span class="font-medium">見放題プラン</span>がおすすめです。支払いはクレジットカードや電子マネー等に対応。
            </p>
          </div>

          <div>
            <h3 class="font-semibold text-gray-800">4. 視聴方法を選ぶ</h3>
            <p class="text-gray-700">
              パソコン・スマホ・タブレットのブラウザで<span class="font-medium">ストリーミング再生</span>できます。
              購入済み作品はマイページの「購入履歴」からいつでも再生可能。高画質モードでは1080p対応の作品もあります。
            </p>
          </div>

          <div>
            <h3 class="font-semibold text-gray-800">5. 見終わったらレビュー投稿を</h3>
            <p class="text-gray-700">
              購入者レビューで「満足度」「画質」「見どころ」を共有しましょう。評価は他ユーザーの参考になり、
              サイト全体の品質向上（E-E-A-T）にもつながります。
            </p>
          </div>
        </div>

        <div class="mt-6 flex justify-center">
          @if($affUrl)
            <a href="{{ $affUrl }}" target="_blank" rel="sponsored nofollow noopener"
               class="inline-flex items-center justify-center px-6 py-3 rounded-xl text-white font-bold
                      bg-gradient-to-r from-indigo-600 to-fuchsia-600 hover:brightness-110 hover:scale-[1.02]
                      transition-all duration-200">
              🎬 今すぐ公式サイトで視聴する
            </a>
          @else
            <a href="{{ $url ?: $canonical }}" target="_blank" rel="nofollow noopener"
               class="inline-flex items-center justify-center px-6 py-3 rounded-xl text-white font-bold
                      bg-gradient-to-r from-indigo-600 to-fuchsia-600 hover:brightness-110 hover:scale-[1.02]
                      transition-all duration-200">
              🎬 今すぐ作品ページを開く
            </a>
          @endif
        </div>
      </section>

      @if($label || $series || !empty($categories))
        <div class="bg-white rounded-lg shadow p-4 space-y-3">
          <h2 class="text-lg font-semibold">作品情報</h2>

          @if(is_object($label))
            @php
              $labelId = method_exists($label,'getId') ? $label->getId() : null;
              $labelName = method_exists($label,'getName') ? $label->getName() : null;
            @endphp
            <div class="text-sm flex items-center gap-2">
              <span class="text-gray-500">レーベル：</span>
              <span>{{ $labelName }}</span>
              @if($labelId)
                <a href="{{ route('browse.filter', ['type'=>'label','id'=>$labelId]) }}"
                   class="inline-flex items-center px-2 py-1 rounded text-xs bg-indigo-600 text-white hover:bg-indigo-700">
                  このレーベルで絞り込み
                </a>
              @endif
            </div>
          @endif

          @if(is_object($series))
            @php
              $seriesId = method_exists($series,'getId') ? $series->getId() : null;
              $seriesName = method_exists($series,'getName') ? $series->getName() : null;
            @endphp
            <div class="text-sm flex items-center gap-2">
              <span class="text-gray-500">シリーズ：</span>
              <span>{{ $seriesName }}</span>
              @if($seriesId)
                <a href="{{ route('browse.filter', ['type'=>'series','id'=>$seriesId]) }}"
                   class="inline-flex items-center px-2 py-1 rounded text-xs bg-indigo-600 text-white hover:bg-indigo-700">
                  このシリーズで絞り込み
                </a>
              @endif
            </div>
          @endif

          @if(!empty($categories))
            <div>
              <div class="text-sm text-gray-500 mb-1">カテゴリ：</div>
              <div class="flex flex-wrap gap-2">
                @foreach($categories as $c)
                  @php
                    $cid = (is_object($c) && method_exists($c,'getId')) ? $c->getId() : null;
                    $cname = (is_object($c) && method_exists($c,'getName')) ? $c->getName() : null;
                  @endphp
                  @if($cname)
                    <a href="{{ $cid ? route('browse.filter', ['type'=>'category','id'=>$cid]) : '#' }}"
                       class="inline-flex items-center text-base bg-gray-100 hover:bg-gray-200 rounded-full px-2 py-1">
                      {{ $cname }}
                    </a>
                  @endif
                @endforeach
              </div>
            </div>
          @endif
        </div>
      @endif

      @if(!empty($performers) || !empty($directors))
        <div class="bg-white rounded-lg shadow p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          @if(!empty($performers))
            <div>
              <h2 class="text-lg font-semibold mb-2">出演者</h2>
              <ul class="divide-y">
                @foreach($performers as $p)
                  @php
                    if (!is_object($p)) { continue; }
                    $pid = method_exists($p,'getId') ? $p->getId() : null;
                    $pname = method_exists($p,'getName') ? $p->getName() : null;
                    $pkana = method_exists($p,'getKana') ? $p->getKana() : null;
                  @endphp
                  @if($pname)
                    <li class="py-2 text-sm flex items-center justify-between gap-2">
                      <div>
                        <div class="font-medium">{{ $pname }}</div>
                        @if($pkana)<div class="text-gray-500">{{ $pkana }}</div>@endif
                      </div>
                      @if($pid)
                        <a href="{{ route('browse.filter', ['type'=>'performer','id'=>$pid]) }}"
                           class="shrink-0 inline-flex items-center px-2 py-1 rounded text-xs bg-indigo-600 text-white hover:bg-indigo-700">
                          この出演者で絞り込み
                        </a>
                      @endif
                    </li>
                  @endif
                @endforeach
              </ul>
            </div>
          @endif

          @if(!empty($directors))
            <div>
              <h2 class="text-lg font-semibold mb-2">監督</h2>
              <ul class="divide-y">
                @foreach($directors as $d)
                  @php
                    if (!is_object($d)) { continue; }
                    $did = method_exists($d,'getId') ? $d->getId() : null;
                    $dname = method_exists($d,'getName') ? $d->getName() : null;
                  @endphp
                  @if($dname)
                    <li class="py-2 text-sm">
                      <div class="font-medium">{{ $dname }}</div>
                      @if($did)<div class="text-gray-400">#{{ $did }}</div>@endif
                    </li>
                  @endif
                @endforeach
              </ul>
            </div>
          @endif
        </div>
      @endif

      @if(!empty($saleTypes))
        <div class="bg-white rounded-lg shadow p-4">
          <h2 class="text-lg font-semibold mb-2">販売形態</h2>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-gray-500 border-b">
                  <th class="py-2 pr-4">タイプ</th>
                  <th class="py-2">価格</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                @foreach($saleTypes as $s)
                  @php
                    if (!is_object($s)) { continue; }
                    $stype = method_exists($s,'getType') ? $s->getType() : '';
                    $sprice = method_exists($s,'getPrice') ? (int)$s->getPrice() : null;
                  @endphp
                  <tr>
                    <td class="py-2 pr-4">{{ $stype }}</td>
                    <td class="py-2">@if(!is_null($sprice)) ¥{{ number_format($sprice) }} @endif</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif

      @if(is_object($review) || (!is_null($rating)))
        <div class="bg-white rounded-lg shadow p-4">
          <h2 class="text-lg font-semibold mb-1">レビュー</h2>
          @if(!is_null($rating))
            <div class="mt-3 flex items-center gap-2">
              <div class="flex">
                @for($i = 1; $i <= 5; $i++)
                  @php $filled = $i <= $rating; @endphp
                  <svg viewBox="0 0 20 20" class="w-5 h-5 {{ $filled ? 'fill-yellow-400 text-yellow-400' : 'fill-gray-300 text-gray-300' }}">
                    <path d="M10 15.27l-5.18 3.05 1.4-5.98L1.64 8.63l6.05-.52L10 2.5l2.31 5.61 6.05.52-4.58 3.71 1.4 5.98L10 15.27z"/>
                  </svg>
                @endfor
              </div>
              <span class="text-sm text-gray-600">{{ $rating }} / 5</span>
              @if($reviewCount !== null) <span class="ml-3 text-gray-500">評価数: {{ $reviewCount }}件</span>@endif
            </div>
          @endif
        </div>
      @endif

      {{-- 右カラムなどに差し込み --}}
      {{-- 人気作品TOP10（タイトル＋サムネ） --}}
      @if(!empty($top7))
        <div class="bg-white rounded-lg shadow p-4">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold">人気作品TOP10</h2>
            <div class="text-xs text-gray-500">直近7日</div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
            @foreach($top7 as $i => $row)
              @php
                // 配列/オブジェクト両対応
                $pid   = is_array($row) ? ($row['productid'] ?? null) : ($row->productid ?? null);
                $ttl   = is_array($row) ? ($row['title']     ?? '')   : ($row->title     ?? '');
                $thumb = is_array($row) ? ($row['thumb']     ?? '')   : ($row->thumb     ?? '');
                $url   = is_array($row) ? ($row['url']       ?? '#')  : ($row->url       ?? '#');
                $views = (int)(is_array($row) ? ($row['views'] ?? 0)  : ($row->views ?? 0));
              @endphp

              <a href="{{ $url }}" class="group block rounded overflow-hidden border hover:shadow transition bg-white">
                <div class="relative">
                  <img src="{{ $thumb }}" alt="{{ $ttl }}" class="w-full aspect-[12/7] object-cover">
                  <span class="absolute left-2 top-2 inline-flex items-center justify-center rounded-full text-[11px] font-bold px-2 py-0.5
                              {{ $i<3 ? 'bg-rose-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                    {{ $i+1 }}
                  </span>
                </div>
                <div class="p-2">
                  <div class="text-xs font-medium line-clamp-2 group-hover:underline">{{ $ttl ?: '#'.$pid }}</div>
                  <div class="mt-1 text-[11px] text-gray-500">👀 {{ number_format($views) }}</div>
                </div>
              </a>
            @endforeach
          </div>
        </div>
      @endif


    </div>
  </div>

  {{-- 画像ビューア --}}
  <div id="lightbox"
       class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4"
       aria-hidden="true" role="dialog" aria-modal="true">
    <div id="lightboxBackdrop" class="absolute inset-0"></div>
    <div id="lightboxContent" class="relative z-10 max-w-5xl w-full">
      <div class="relative bg-black rounded-lg shadow overflow-hidden">
        <img id="lightboxImg" src="" alt="sample" class="mx-auto max-h-[80vh] w-auto select-none" draggable="false" />
        <button id="lbClose"
                class="absolute top-2 right-2 inline-flex items-center justify-center rounded-full bg-black/60 text-white w-9 h-9 hover:bg-black/80 focus:outline-none focus:ring-2 focus:ring-white"
                aria-label="閉じる">✕</button>
        <button id="lbPrev"
                class="absolute left-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center rounded-full bg-black/60 text-white w-10 h-10 hover:bg-black/80 focus:outline-none focus:ring-2 focus:ring-white"
                aria-label="前の画像">‹</button>
        <button id="lbNext"
                class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center rounded-full bg-black/60 text-white w-10 h-10 hover:bg-black/80 focus:outline-none focus:ring-2 focus:ring-white"
                aria-label="次の画像">›</button>
      </div>
      <div class="mt-2 text-center text-xs text-gray-200">
        <span id="lbCounter">1 / 1</span>
      </div>
    </div>
  </div>

  {{-- 関連作品 --}}
  @if(!empty($related ?? []))
    <div class="mt-8 bg-white rounded-lg shadow p-4">
      <h2 class="text-lg font-semibold mb-3">関連作品</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @foreach($related as $it)
          @php
            if (!is_object($it)) { continue; }
            $pid    = method_exists($it,'getProductid') ? $it->getProductid() : null;
            $ttl    = method_exists($it,'getTitle') ? $it->getTitle() : '';
            $rank   = method_exists($it,'getRanking') ? (int)$it->getRanking() : null;

            $posterUrl = null;
            if (method_exists($it,'getPosterImage') && ($pi = $it->getPosterImage()) && is_object($pi)) {
              if (method_exists($pi,'getSmall'))  $posterUrl = $posterUrl ?: $pi->getSmall();
              if (method_exists($pi,'getMedium')) $posterUrl = $posterUrl ?: $pi->getMedium();
              if (method_exists($pi,'getMidium')) $posterUrl = $posterUrl ?: $pi->getMidium();
            }

            $rankBadgeClass = 'bg-gray-500 text-white';
            if (!is_null($rank)) {
              if ($rank === 1)      $rankBadgeClass = 'bg-yellow-400 text-yellow-900';
              elseif ($rank === 2)  $rankBadgeClass = 'bg-gray-300 text-gray-800';
              elseif ($rank === 3)  $rankBadgeClass = 'bg-amber-700 text-amber-100';
            }
          @endphp

          @if($pid)
            <a href="{{ route('products.show', ['id' => $pid]) }}"
               class="group block overflow-hidden rounded-lg bg-white border hover:shadow-md transition relative">
              @if(!is_null($rank))
                <div class="absolute left-2 top-2 z-10">
                  <span class="inline-flex items-center justify-center rounded-full text-xs font-bold px-2 py-1 shadow {{ $rankBadgeClass }}">
                    {{ $rank }}位
                  </span>
                </div>
              @endif

              <div class="aspect-[12/7] bg-gray-100">
                @if($posterUrl)
                  <img
                    src="{{ $posterUrl }}"
                    alt="{{ $ttl }} のサムネイル"
                    width="240" height="140"
                    loading="lazy" decoding="async" fetchpriority="low"
                    class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]">
                @else
                  <div class="h-full w-full flex items-center justify-center text-gray-400">No Image</div>
                @endif
              </div>
              <div class="p-2">
                <div class="line-clamp-2 text-xs font-medium leading-snug">{{ $ttl }}</div>
              </div>
            </a>
          @endif
        @endforeach
      </div>
    </div>
  @endif

  {{-- ライトボックス JS --}}
  <script>
    (function () {
      const grid     = document.getElementById('thumbGrid');
      if (!grid) return;

      const buttons  = Array.from(grid.querySelectorAll('[data-index][data-src]'));
      const modal    = document.getElementById('lightbox');
      const imgEl    = document.getElementById('lightboxImg');
      const closeBtn = document.getElementById('lbClose');
      const prevBtn  = document.getElementById('lbPrev');
      const nextBtn  = document.getElementById('lbNext');
      const counter  = document.getElementById('lbCounter');
      const backdrop = document.getElementById('lightboxBackdrop');

      let current = 0;
      const total = buttons.length;
      const sources = buttons.map(b => b.getAttribute('data-src')).filter(Boolean);

      function openModal(index) {
        if (!sources.length) return;
        current = (index + total) % total;
        loadImage(current);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
      }

      function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
      }

      function loadImage(idx) {
        const src = sources[idx];
        if (!src) return;
        imgEl.src = src;
        counter.textContent = (idx + 1) + ' / ' + total;
        const preloadNext = new Image(); preloadNext.src = sources[(idx + 1) % total];
        const preloadPrev = new Image(); preloadPrev.src = sources[(idx - 1 + total) % total];
      }

      function next() { current = (current + 1) % total; loadImage(current); }
      function prev() { current = (current - 1 + total) % total; loadImage(current); }

      buttons.forEach((btn, i) => {
        btn.addEventListener('click', () => openModal(i));
        btn.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault(); openModal(i);
          }
        });
      });

      nextBtn?.addEventListener('click', next);
      prevBtn?.addEventListener('click', prev);
      closeBtn?.addEventListener('click', closeModal);
      backdrop?.addEventListener('click', closeModal);

      document.addEventListener('keydown', (e) => {
        if (modal.classList.contains('hidden')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowRight') next();
        if (e.key === 'ArrowLeft')  prev();
      });
    })();
  </script>
  <script>
  (function () {
    const bar = document.getElementById('stickyCta');
    if (!bar) return;

    const showThreshold = 400; // この高さ(px)以上スクロールしたら表示

    function onScroll() {
      const y = window.scrollY || window.pageYOffset || 0;
      if (y > showThreshold) {
        bar.classList.remove('translate-y-full', 'opacity-0', 'pointer-events-none');
      } else {
        bar.classList.add('translate-y-full', 'opacity-0', 'pointer-events-none');
      }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // 読み込み時にも一回判定
  })();
</script>
@endsection
