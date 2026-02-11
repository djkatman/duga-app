{{-- resources/views/products/list.blade.php --}}
@extends('layouts.app')

{{-- ====== SEO Meta / OGP / JSON-LD ====== --}}
@php
  // 基本
  $siteName   = 'DUGAサンプル動画見放題';

  // ★ 現在のルートがトップかどうか
  $isHome = request()->routeIs('home');

  // ★ 並び替えパラメータ
  $sort = request()->get('sort', 'favorite');

  // 総件数
  $isPaginated = $items instanceof \Illuminate\Contracts\Pagination\Paginator;
  $pageNum     = $isPaginated ? (int)$items->currentPage() : 1;

  $total       = $isPaginated ? (int)$items->total() : (is_countable($items) ? count($items) : 0);

  // ラベル/語
  $labelBase = ($type === 'keyword') ? 'キーワード' : ($typeName ?? '絞り込み');
  $term      = ($type === 'keyword')
      ? ($query ?? $filterName ?? $filterId ?? '')
      : ($filterName ?? $filterId ?? '');

  // ページタイトル（件数入り）
  $pageTitle  = $labelBase.': '.($term !== '' ? $term : '未指定').'（'.number_format($total).'件） | '.$siteName;

  // カード列挙名
  $labelText  = $labelBase.': '.(($term !== '') ? $term : '未指定');

     // ページネーションURL
  $pageNum = $isPaginated ? $items->currentPage() : 1;

  // フィルタ判定（browse.filter）
  $isFilterPage = request()->routeIs('browse.filter') && !empty($type) && !empty($filterId);

  // 検索（keyword）判定：type=keyword を検索扱いにする
  $isKeyword = ($type === 'keyword') && !empty($filterId);

  // ===== canonical は「常にベースへ集約」=====
  if ($isHome) {
      $canonicalUrl = route('home');
  } elseif ($isFilterPage) {
      // page / sort は canonical に入れない（重複対策）
      $canonicalUrl = route('browse.filter', ['type' => $type, 'id' => $filterId]);
  } else {
      // 念のため：今のURLを置く（ここに来ない設計が理想）
      $canonicalUrl = url()->current();
  }

  // prev / next はユーザー体験重視なので、今のままでもOK
  $prevUrl      = $isPaginated ? $items->previousPageUrl() : null;
  $nextUrl      = $isPaginated ? $items->nextPageUrl()     : null;

  // 説明
  $desc = $labelText.' の作品一覧。サンプル動画や画像、出演者情報までチェックできます。';

  // OGP画像
  $ogImage = asset('favicon.ico');

  // ItemList JSON-LD
  $itemList = [];
  if ($items && ($isPaginated ? $items->count() > 0 : count($items) > 0)) {
    $pos = 1;
    foreach ($items as $it) {
      $pid   = method_exists($it,'getProductid') ? $it->getProductid() : null;
      $ttl   = method_exists($it,'getTitle') ? $it->getTitle() : '';
      $url   = $pid ? route('products.show', ['id'=>$pid]) : url()->current();

      $img = null;
      if (method_exists($it,'getPosterImage') && ($pi = $it->getPosterImage())) {
        if (method_exists($pi,'getSmall'))  $img = $img ?: $pi->getSmall();
        if (method_exists($pi,'getMedium')) $img = $img ?: $pi->getMedium();
        if (method_exists($pi,'getMidium')) $img = $img ?: $pi->getMidium();
      }

      $itemList[] = [
        '@type'    => 'ListItem',
        'position' => $pos++,
        'url'      => $url,
        'name'     => $ttl,
        'image'    => $img,
      ];
    }
  }

  $jsonLdList = [
    '@context'        => 'https://schema.org',
    '@type'           => 'ItemList',
    'name'            => $labelText,
    'itemListElement' => $itemList,
  ];

    $base = '';

  switch ($type) {
    case 'category':
      $base = $filterName ? "{$filterName}の動画一覧" : "カテゴリ別動画一覧";
      break;
    case 'label':
      $base = $filterName ? "{$filterName}作品の動画一覧" : "レーベル別動画一覧";
      break;
    case 'series':
      $base = $filterName ? "シリーズ「{$filterName}」の動画一覧" : "シリーズ動画一覧";
      break;
    case 'performer':
      $base = $filterName ? "{$filterName} 出演の動画一覧" : "出演者別動画一覧";
      break;
    case 'keyword':
      $base = $filterId ? "「{$filterId}」の検索結果" : "動画検索結果";
      break;
    default:
      $base = "動画一覧";
  }

  // SEO訴求ワード追加
  $seoTitle = $base.' | 無料サンプル動画あり | DUGAサンプル動画見放題';

  // ====== 一覧ページ用の動的説明文（SEO用 100〜200字） ======
  $introText = '';

  // 種類ごとに説明文のテンプレートを作る
  switch ($type) {
      case 'category':
          if (!empty($filterName)) {
              $introText = "「{$filterName}」カテゴリの動画一覧です。DUGAで配信されている{$filterName}ジャンルの人気作品・新着タイトルをまとめてチェックできます。無料サンプル動画つきで内容を確認でき、出演者情報や関連作品も比較しやすくなっています。";
          }
          break;

      case 'performer':
          if (!empty($filterName)) {
              $introText = "{$filterName}さんが出演する作品の一覧です。出演作の傾向や新着タイトル、人気順での並べ替えができ、{$filterName}さんの出演作品をまとめて探したい方に最適です。サンプル動画つきで内容も確認できます。";
          }
          break;

      case 'series':
          if (!empty($filterName)) {
              $introText = "シリーズ「{$filterName}」の作品一覧です。過去作から最新作までまとめてチェックでき、シリーズの特徴や出演者、各話の違いを比較しながら視聴作品を選ぶことができます。";
          }
          break;

      case 'label':
          if (!empty($filterName)) {
              $introText = "レーベル「{$filterName}」の作品一覧です。{$filterName}が提供する人気作品・新着作品をまとめて確認でき、ジャンルや出演者の傾向を把握しながら作品選びができます。";
          }
          break;

      case 'keyword':
          if (!empty($filterId)) {
              $introText = "「{$filterId}」の検索結果一覧です。キーワードに関連する作品を人気順・新着順で絞り込みながら探すことができ、無料サンプル動画で内容をチェックしつつ自分に合ったタイトルを選べます。";
          }
          break;
  }

  // ★ noindex を付ける条件
  $shouldNoindex = false;

  // 1 page=2以降は noindex
  if ($pageNum > 1) {
      $shouldNoindex = true;
  }

  // 2 sort がデフォルト(favorite)以外は noindex
  if ($sort !== 'favorite') {
      $shouldNoindex = true;
  }

  // 3 検索（keyword）は noindex（無限増殖対策）
  if ($isKeyword) {
      $shouldNoindex = true;
  }

  // 4 念のため page が異常に大きい場合
  if ($pageNum > 50) {
      $shouldNoindex = true;
  }
@endphp

@section('title', $seoTitle)

@section('meta')
  <meta name="description" content="{{ $desc }}">
  <link rel="canonical" href="{{ $canonicalUrl }}">
  @if($prevUrl)<link rel="prev" href="{{ $prevUrl }}">@endif
  @if($nextUrl)<link rel="next" href="{{ $nextUrl }}">@endif
  {{-- ★ 並び替えページ等は noindex,follow --}}
  @if($shouldNoindex)
    <meta name="robots" content="noindex,follow">
  @endif
  <meta property="og:site_name" content="{{ $siteName }}">
  <meta property="og:type" content="website">
  <meta property="og:title" content="{{ $seoTitle }}">
  <meta property="og:description" content="{{ $desc }}">
  <meta property="og:url" content="{{ $canonicalUrl }}">
  <meta property="og:image" content="{{ $ogImage }}">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $seoTitle }}">
  <meta name="twitter:description" content="{{ $desc }}">
  <meta name="twitter:image" content="{{ $ogImage }}">

  {{-- noindex時は ItemList を出さない（信号を揃える） --}}
  @if(!$shouldNoindex)
    <script type="application/ld+json">
      @json($jsonLdList, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    </script>
  @endif
@endsection

@section('content')
  {{-- パンくず --}}
  @php
    $crumbs = [
      ['label' => 'トップ', 'url' => route('home')],
      ['label' => $labelText],
    ];
  @endphp
  @include('partials.breadcrumbs', ['crumbs' => $crumbs])

  {{-- 見出し（件数入り）＋ 並び替え --}}

  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">
      {{ $base }}（無料サンプル動画あり）
      ：<span class="text-gray-700 text-base">{{ $term !== '' ? $term : '未指定' }}</span>
      <span class="ml-2 text-sm text-gray-500">（{{ number_format($total) }}件）</span>
    </h1>
    <div class="hidden sm:flex items-center gap-2 text-sm">
      <a href="{{ route('browse.filter', ['type'=>$type,'id'=>$filterId,'sort'=>'favorite']) }}"
        class="px-3 py-1 rounded border hover:bg-gray-50 {{ request('sort','favorite')==='favorite' ? 'bg-gray-100' : '' }}">
        人気順
      </a>
      <a href="{{ route('browse.filter', ['type'=>$type,'id'=>$filterId,'sort'=>'new']) }}"
        class="px-3 py-1 rounded border hover:bg-gray-50 {{ request('sort')==='new' ? 'bg-gray-100' : '' }}">
        新着順
      </a>
    </div>
  </div>

  {{-- ▼ 一覧ページの冒頭説明（SEO強化） --}}
    @if(!empty($introText))
    <p class="mt-3 mb-4 text-sm text-gray-700 leading-relaxed">
        {{ $introText }}
    </p>
    @endif
  @if($items->isEmpty())
    <div class="rounded border border-dashed p-8 text-center text-gray-500">条件に一致する商品がありません。</div>
  @else
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
      @foreach ($items as $it)
        @php
          $id      = method_exists($it,'getProductid') ? $it->getProductid() : null;
          $title   = method_exists($it,'getTitle') ? $it->getTitle() : '';
          $release = method_exists($it,'getReleasedate') ? $it->getReleasedate() : '';

          // 画像
          $posterUrl = null;
          if (method_exists($it,'getPosterImage') && ($pi=$it->getPosterImage())) {
            if (method_exists($pi,'getSmall'))  $posterUrl = $posterUrl ?: $pi->getSmall();
            if (method_exists($pi,'getMedium')) $posterUrl = $posterUrl ?: $pi->getMedium();
            if (method_exists($pi,'getMidium')) $posterUrl = $posterUrl ?: $pi->getMidium();
          }

          // ランキング
          $rank = method_exists($it,'getRanking') ? (int)$it->getRanking() : null;

          // バッジ色（PHP7互換）
          $rankBadgeClass = 'bg-gray-500 text-white';
          if (!is_null($rank)) {
            if ($rank === 1)      $rankBadgeClass = 'bg-yellow-400 text-yellow-900'; // 金
            elseif ($rank === 2)  $rankBadgeClass = 'bg-gray-300 text-gray-800';     // 銀
            elseif ($rank === 3)  $rankBadgeClass = 'bg-amber-700 text-amber-100';   // 銅
          }
        @endphp

        @if($id)
          <a href="{{ route('products.show', ['id' => $id]) }}"
             class="group block overflow-hidden rounded-lg bg-white shadow hover:shadow-md transition relative">
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
                  alt="{{ $title }} のサムネイル"
                  width="240" height="140"
                  loading="lazy" decoding="async" fetchpriority="low"
                  class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]">
              @else
                <div class="h-full w-full flex items-center justify-center text-gray-400">No Image</div>
              @endif
            </div>

            <div class="p-3">
              <div class="line-clamp-2 text-sm font-medium leading-snug">{{ $title }}</div>
              <div class="mt-1 text-xs text-gray-500">発売日: {{ $release ?: '―' }}</div>
            </div>
          </a>
        @endif
      @endforeach
    </div>

    <div class="mt-6">
      @include('partials.pagination', ['paginator' => $items])
    </div>
  @endif
@endsection
