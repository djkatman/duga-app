{{-- resources/views/products/show.blade.php --}}
@extends('layouts.app')

@php
  // ===== ユーティリティ =====
  $has = fn($obj, $m) => is_object($obj) && method_exists($obj, $m);

  // メイン情報
  $title        = $has($item,'getTitle')         ? $item->getTitle()         : '';
  $origTitle    = $has($item,'getOriginaltitle') ? $item->getOriginaltitle() : ($has($item,'getOriginalTitle') ? $item->getOriginalTitle() : '');
  $caption      = $has($item,'getCaption')       ? $item->getCaption()       : '';
  $maker        = $has($item,'getMakername')     ? $item->getMakername()     : ($has($item,'getMakerName') ? $item->getMakerName() : '');
  $url          = $has($item,'getUrl')           ? $item->getUrl()           : '';
  $affUrl       = $has($item,'getAffiliateurl')  ? $item->getAffiliateurl()  : ($has($item,'getAffiliateUrl') ? $item->getAffiliateUrl() : '');
  $openDate     = $has($item,'getOpendate')      ? $item->getOpendate()      : ($has($item,'getOpenDate') ? $item->getOpenDate() : '');
  $releaseDate  = $has($item,'getReleasedate')   ? $item->getReleasedate()   : ($has($item,'getReleaseDate') ? $item->getReleaseDate() : '');
  $itemNo       = $has($item,'getItemno')        ? $item->getItemno()        : ($has($item,'getItemNo') ? $item->getItemNo() : '');
  $price        = $has($item,'getPrice')         ? $item->getPrice()         : null;
  $volume       = $has($item,'getVolume')        ? $item->getVolume()        : '';
  $rankingTotal = $has($item,'getRanking')       ? $item->getRanking()       : (property_exists($item,'rankingTotal') ? $item->rankingTotal : null);
  $mylistTotal  = $has($item,'getMylist')        ? $item->getMylist()        : (property_exists($item,'mylistTotal') ? $item->mylistTotal : null);

  // 画像
  $poster = $has($item,'getPosterImage') ? $item->getPosterImage() : null;
  $posterSmall  = $poster && method_exists($poster,'getSmall')  ? $poster->getSmall()  : null;
  $posterMedium = $poster && method_exists($poster,'getMedium') ? $poster->getMedium() : (method_exists($poster,'getMidium') ? $poster->getMidium() : null);
  $posterLarge  = $poster && method_exists($poster,'getLarge')  ? $poster->getLarge()  : null;

  $jacket = $has($item,'getJacketImage') ? $item->getJacketImage() : null;
  $jSmall  = $jacket && method_exists($jacket,'getSmall')  ? $jacket->getSmall()  : null;
  $jMedium = $jacket && method_exists($jacket,'getMedium') ? $jacket->getMedium() : (method_exists($jacket,'getMidium') ? $jacket->getMidium() : null);
  $jLarge  = $jacket && method_exists($jacket,'getLarge')  ? $jacket->getLarge()  : null;

  $thumbs = $has($item,'getThumbnail') ? (array) $item->getThumbnail() : [];

  $sample = $has($item,'getSampleMovie') ? $item->getSampleMovie() : null;
  $sampleMovie   = $sample && method_exists($sample,'getMovie')   ? $sample->getMovie()   : null;
  $sampleCapture = $sample && method_exists($sample,'getCapture') ? $sample->getCapture() : null;

  // 関連
  $label = $has($item,'getLabel') ? $item->getLabel() : null;
  $series = $has($item,'getSeries') ? $item->getSeries() : null;
  $categories = $has($item,'getCategory') ? (array) $item->getCategory() : [];
  $performers = $has($item,'getPerformer') ? (array) $item->getPerformer() : [];
  $directors  = $has($item,'getDirector')  ? (array) $item->getDirector()  : [];
  $saleTypes  = $has($item,'getSaleType')  ? (array) $item->getSaleType()  : [];
  $review     = $has($item,'getReview')    ? $item->getReview() : null;

  // レビューの星用
  $rawRating =
    ($review && method_exists($review,'getRating')) ? $review->getRating()
    : ($has($item,'getRating') ? $item->getRating() : null);
  $rating = is_numeric($rawRating) ? max(0, min(5, (int)$rawRating)) : null;
  $reviewer = $review && method_exists($review,'getReviewer') ? $review->getReviewer() : null;

  // ===== SEO 変数 =====
  $siteName  = 'DUGAサンプル動画見放題';
  $name      = $title ?: '商品詳細';
  $desc      = trim(mb_strimwidth(preg_replace("/\s+/u", ' ', (string)$caption), 0, 180, '…'));
  if ($desc === '') {
    $desc = $maker ? "{$maker} の作品。サンプル動画・画像、出演者・カテゴリ情報を掲載。" : "サンプル動画・画像、出演者・カテゴリ情報を掲載。";
  }
  $canonical = url()->current();
  $ogImage   = $posterLarge ?? $posterMedium ?? $posterSmall ?? $jLarge ?? $jMedium ?? $jSmall ?? asset('favicon.ico');

  // 価格・通貨
  $priceValue = is_numeric($price) ? (float)$price : null;
  $currency   = 'JPY';

  // ISO8601
  $releaseISO = $releaseDate ? \Carbon\Carbon::parse($releaseDate)->toDateString() : null;
  $durationISO = (is_numeric($volume) && (int)$volume > 0) ? ('PT'.(int)$volume.'M') : null;

  // カテゴリ名
  $categoryNames = [];
  foreach ($categories as $c) {
    if (is_object($c) && method_exists($c,'getName')) $categoryNames[] = $c->getName();
  }

  // レーベル名
  $labelName = ($label && method_exists($label,'getName')) ? $label->getName() : null;

  // 出演者/監督
  $actorList = [];
  foreach ($performers as $p) {
    $nm = method_exists($p,'getName') ? $p->getName() : null;
    if ($nm) $actorList[] = ['@type'=>'Person','name'=>$nm];
  }
  $directorList = [];
  foreach ($directors as $d) {
    $nm = method_exists($d,'getName') ? $d->getName() : null;
    if ($nm) $directorList[] = ['@type'=>'Person','name'=>$nm];
  }

  // Product JSON-LD
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
    'offers'        => $priceValue ? [
      '@type'         => 'Offer',
      'price'         => number_format($priceValue, 0, '.', ''),
      'priceCurrency' => $currency,
      'url'           => $affUrl ?: $canonical,
      'availability'  => 'https://schema.org/InStock'
    ] : null,
    'aggregateRating'=> (!is_null($rating)) ? [
      '@type'       => 'AggregateRating',
      'ratingValue' => (string)$rating,
      'bestRating'  => '5',
      'worstRating' => '0',
      'ratingCount' => (is_numeric($reviewer) ? (int)$reviewer : null),
    ] : null,
  ];
  $productLd = array_filter($productLd, fn($v) => !is_null($v));

  // パンくず JSON-LD（カテゴリ1つ目があれば中継）
  $firstCat   = (!empty($categories) && isset($categories[0]) && method_exists($categories[0],'getId')) ? $categories[0] : null;
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
@endphp

@section('title', ($item?->getTitle() ?? '商品詳細').' | DUGAサンプル動画見放題')

@section('meta')
  {{-- 基本メタ --}}
  <meta name="description" content="{{ $desc }}">
  <link rel="canonical" href="{{ $canonical }}">

  {{-- OGP / Twitter --}}
  <meta property="og:site_name" content="{{ $siteName }}">
  <meta property="og:type" content="product">
  <meta property="og:title" content="{{ $name }} | {{ $siteName }}">
  <meta property="og:description" content="{{ $desc }}">
  <meta property="og:url" content="{{ $canonical }}">
  <meta property="og:image" content="{{ $ogImage }}">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $name }} | {{ $siteName }}">
  <meta name="twitter:description" content="{{ $desc }}">
  <meta name="twitter:image" content="{{ $ogImage }}">

  {{-- 構造化データ --}}
  <script type="application/ld+json">@json($productLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</script>
  <script type="application/ld+json">@json($breadcrumbLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</script>
@endsection

@section('content')
  {{-- パンくず UI --}}
  @php
    $firstCatForUi = (!empty($categories) && isset($categories[0]) && method_exists($categories[0],'getName')) ? $categories[0] : null;
    $crumbs = [
      ['label' => 'トップ', 'url' => route('home')],
    ];
    if ($firstCatForUi && method_exists($firstCatForUi,'getId')) {
      $crumbs[] = [
        'label' => 'カテゴリ: '.$firstCatForUi->getName(),
        'url'   => route('browse.filter',['type'=>'category','id'=>$firstCatForUi->getId()]),
      ];
    }
    $crumbs[] = ['label' => $title ?: '商品詳細'];
  @endphp
  @include('partials.breadcrumbs', ['crumbs' => $crumbs])

  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">商品詳細</h1>
    <a href="{{ url()->previous() }}" class="text-sm text-indigo-600 hover:underline">← 一覧に戻る</a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    {{-- 左：メイン画像＆サンプル --}}
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

      {{-- サンプル画像（ライトボックス/URL変換） --}}
      @if(!empty($thumbs))
        <div class="bg-white rounded-lg shadow p-3">
          <h2 class="text-sm font-semibold mb-2">サンプル画像</h2>
          <div id="thumbGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
            @foreach($thumbs as $idx => $t)
              @php $fullUrl = str_replace('/noauth/scap/', '/cap/', $t); @endphp
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
            @endforeach
          </div>
        </div>
      @endif
    </div>

    {{-- 右：テキスト情報 --}}
    <div class="md:col-span-2 space-y-6">
      <div class="bg-white rounded-lg shadow p-4">
        <h1 class="text-2xl font-bold">{{ $title }}</h1>
        @if($origTitle)
          <div class="text-sm text-gray-500 mt-1">原題：{{ $origTitle }}</div>
        @endif

        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
          @if($releaseDate)<div><span class="text-gray-500">発売日：</span>{{ $releaseDate }}</div>@endif
          @if($openDate)   <div><span class="text-gray-500">公開日：</span>{{ $openDate }}</div>@endif
          @if($itemNo)     <div><span class="text-gray-500">品番：</span>{{ $itemNo }}</div>@endif
          @if($maker)      <div><span class="text-gray-500">メーカー：</span>{{ $maker }}</div>@endif
          @if(!is_null($price)) <div><span class="text-gray-500">価格：</span>¥{{ number_format((int)$price) }}</div>@endif
          @if($volume)     <div><span class="text-gray-500">収録：</span>{{ $volume }}分</div>@endif
          @if($rankingTotal !== null)<div><span class="text-gray-500">ランキング：</span>{{ $rankingTotal }}位</div>@endif
          @if($mylistTotal !== null) <div><span class="text-gray-500">マイリスト：</span>{{ $mylistTotal }}件</div>@endif
        </div>

        @if($caption)
          <div class="mt-4 whitespace-pre-line leading-relaxed">{{ $caption }}</div>
        @endif

        <div class="mt-4 flex flex-wrap gap-3 text-lg">
          @if($affUrl)
            <a href="{{ $affUrl }}" target="_blank" rel="noopener sponsored"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg
                      bg-gradient-to-r from-indigo-500 to-indigo-600
                      text-white font-semibold shadow-md
                      transform transition duration-200
                      hover:from-indigo-600 hover:to-indigo-700 hover:scale-[1.03] hover:shadow-lg
                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 7h6m0 0v6m0-6L10 16m-6 5h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span>公式ページで詳細を見る</span>
            </a>
          @endif
        </div>
      </div>

      @if($label || $series || !empty($categories))
        <div class="bg-white rounded-lg shadow p-4 space-y-3">
          <h2 class="text-lg font-semibold">作品情報</h2>

          @if($label)
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

          @if($series)
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
                    $cid = method_exists($c,'getId') ? $c->getId() : null;
                    $cname = method_exists($c,'getName') ? $c->getName() : null;
                  @endphp
                  <a href="{{ $cid ? route('browse.filter', ['type'=>'category','id'=>$cid]) : '#' }}"
                     class="inline-flex items-center text-base bg-gray-100 hover:bg-gray-200 rounded-full px-2 py-1">
                    {{ $cname }}
                  </a>
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
                    $pid = method_exists($p,'getId') ? $p->getId() : null;
                    $pname = method_exists($p,'getName') ? $p->getName() : null;
                    $pkana = method_exists($p,'getKana') ? $p->getKana() : null;
                  @endphp
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
                    $did = method_exists($d,'getId') ? $d->getId() : null;
                    $dname = method_exists($d,'getName') ? $d->getName() : null;
                  @endphp
                  <li class="py-2 text-sm">
                    <div class="font-medium">{{ $dname }}</div>
                    @if($did)<div class="text-gray-400">#{{ $did }}</div>@endif
                  </li>
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

      @if($review)
        <div class="bg-white rounded-lg shadow p-4">
          <h2 class="text-lg font-semibold mb-1">レビュー</h2>
          <div class="text-sm">
            @if(!is_null($rating))
              <div class="mt-3 flex items-center gap-2">
                <div class="flex">
                  @for ($i = 1; $i <= 5; $i++)
                    @php $filled = $i <= $rating; @endphp
                    <svg viewBox="0 0 20 20" class="w-5 h-5 {{ $filled ? 'fill-yellow-400 text-yellow-400' : 'fill-gray-300 text-gray-300' }}">
                      <path d="M10 15.27l-5.18 3.05 1.4-5.98L1.64 8.63l6.05-.52L10 2.5l2.31 5.61 6.05.52-4.58 3.71 1.4 5.98L10 15.27z"/>
                    </svg>
                  @endfor
                </div>
                <span class="text-sm text-gray-600">{{ $rating }} / 5</span>
                @if($reviewer) <span class="ml-3 text-gray-500">評価数: {{ $reviewer }}件</span>@endif
              </div>
            @endif
          </div>
        </div>
      @endif
    </div>
  </div>

  {{-- ライトボックス: モーダル本体 --}}
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
  @if(!empty($related))
    <div class="mt-8 bg-white rounded-lg shadow p-4">
      <h2 class="text-lg font-semibold mb-3">関連作品</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @foreach($related as $it)
          @php
            $pid    = method_exists($it,'getProductid') ? $it->getProductid() : null;
            $ttl    = method_exists($it,'getTitle') ? $it->getTitle() : '';
            $rank   = method_exists($it,'getRanking') ? (int)$it->getRanking() : null;

            $posterUrl = null;
            if (method_exists($it,'getPosterImage') && ($pi=$it->getPosterImage())) {
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
                @if ($posterUrl)
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

  {{-- ライトボックス用スクリプト --}}
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
      const sources = buttons.map(b => b.getAttribute('data-src'));

      function openModal(index) {
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

      nextBtn.addEventListener('click', next);
      prevBtn.addEventListener('click', prev);
      closeBtn.addEventListener('click', closeModal);
      backdrop.addEventListener('click', closeModal);

      document.addEventListener('keydown', (e) => {
        if (modal.classList.contains('hidden')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowRight') next();
        if (e.key === 'ArrowLeft')  prev();
      });
    })();
  </script>
@endsection
