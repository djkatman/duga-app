{{-- resources/views/products/list.blade.php --}}
@extends('layouts.app')

{{-- ====== SEO Meta / OGP / JSON-LD ====== --}}
@php
  // 基本
  $siteName   = 'DUGAサンプル動画見放題';

  // 総件数
  $isPaginated = $items instanceof \Illuminate\Contracts\Pagination\Paginator;
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
  $pageNum      = $isPaginated ? $items->currentPage() : 1;
  $canonicalUrl = $isPaginated ? $items->url($pageNum) : url()->current();
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
@endphp

@section('title', $pageTitle)

@section('meta')
  <meta name="description" content="{{ $desc }}">
  <link rel="canonical" href="{{ $canonicalUrl }}">
  @if($prevUrl)<link rel="prev" href="{{ $prevUrl }}">@endif
  @if($nextUrl)<link rel="next" href="{{ $nextUrl }}">@endif

  <meta property="og:site_name" content="{{ $siteName }}">
  <meta property="og:type" content="website">
  <meta property="og:title" content="{{ $pageTitle }}">
  <meta property="og:description" content="{{ $desc }}">
  <meta property="og:url" content="{{ $canonicalUrl }}">
  <meta property="og:image" content="{{ $ogImage }}">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $pageTitle }}">
  <meta name="twitter:description" content="{{ $desc }}">
  <meta name="twitter:image" content="{{ $ogImage }}">

  <script type="application/ld+json">@json($jsonLdList, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</script>
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
      {{ $labelBase }}
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
