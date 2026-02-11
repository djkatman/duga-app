{{-- resources/views/index.blade.php --}}
@extends('layouts.app')

{{-- ====== SEO Meta / OGP / JSON-LD ====== --}}
@php
  // ページ情報
  $siteName = 'DUGAサンプル動画見放題';
  $seoTitle = "DUGAアダルト動画を見るならここ！無料・割引・人気作まとめ【最新版】";
  $seoDesc  = "DUGAの人気アダルト動画を毎日更新。無料サンプル動画・画像あり。出演者・カテゴリ・シリーズから検索可能。";
  $pageTitle  = 'トップ | '.$siteName;
  $sortLabel  = request('sort', 'favorite') === 'new' ? '新着順' : '人気順';
  $pageNum    = (int) request('page', 1);

  // ディスクリプション（必要に応じて調整）
  $desc = "DUGAで配信中のアダルト動画をジャンル別に紹介！人気シリーズ・新作・割引作品など、今すぐ見られるおすすめラインナップを完全網羅。視聴前にチェックしておきたい料金・支払い方法もわかりやすく解説します。";

  // カノニカル & prev/next（ページネーション）
  $isPaginated  = $items instanceof \Illuminate\Contracts\Pagination\Paginator;
  $pageNum     = $isPaginated ? (int) $items->currentPage() : (int) request('page', 1);

  // sort（指定がなければ favorite 扱い）
  $sort = request('sort', 'favorite');

  $canonicalUrl = url('/');

  $prevUrl      = $isPaginated ? $items->previousPageUrl() : null;
  $nextUrl      = $isPaginated ? $items->nextPageUrl()     : null;

  // noindex 条件
  $shouldNoindex = false;

  // 2ページ目以降は noindex
  if ($pageNum > 1) {
      $shouldNoindex = true;
  }

  // sort がデフォルト以外なら noindex（任意だが推奨）
  if (!empty($sort) && $sort !== 'favorite') {
      $shouldNoindex = true;
  }

  // サイト代表アイコン（任意で差し替え）
  $ogImage = asset('favicon.ico');

  // ItemList JSON-LD 用に一覧の要素を作る
  $itemList = [];
  if (is_iterable($items)) {
      $pos = 1;
      foreach ($items as $it) {

          // ID / タイトル / 画像URL（簡易取得）
          $id    = method_exists($it,'getProductid') ? $it->getProductid() : null;
          $title = method_exists($it,'getTitle')     ? $it->getTitle()     : '';
          $url   = $id ? route('products.show', ['id'=>$id]) : url()->current();

          $img = null;
          if (method_exists($it,'getPosterImage') && ($pi = $it->getPosterImage())) {
              if (method_exists($pi,'getSmall'))  $img = $img ?: $pi->getSmall();
              if (method_exists($pi,'getMedium')) $img = $img ?: $pi->getMedium();
              if (method_exists($pi,'getMidium')) $img = $img ?: $pi->getMidium();
          }

          $itemList[] = [
              '@type'   => 'ListItem',
              'position'=> $pos++,
              'url'     => $url,
              'name'    => $title,
              'image'   => $img,
          ];
      }
  }

  $jsonLd = [
      '@context'        => 'https://schema.org',
      '@type'           => 'ItemList',
      'name'            => $siteName.' - '.$sortLabel.'一覧',
      'itemListElement' => $itemList,
  ];
@endphp

@section('title', $seoTitle)

@section('meta')
  {{-- 基本メタ --}}
  <meta name="description" content="{{ $seoDesc }}">

  {{-- カノニカル & ページネーション --}}
  <link rel="canonical" href="{{ $canonicalUrl }}">
  @if($shouldNoindex)
    <meta name="robots" content="noindex,follow">
  @endif
  @if($prevUrl)<link rel="prev" href="{{ $prevUrl }}">@endif
  @if($nextUrl)<link rel="next" href="{{ $nextUrl }}">@endif

  {{-- OGP / Twitter Card --}}
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

  {{-- 構造化データ（ItemList） --}}
  <script type="application/ld+json">@json($jsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</script>


@endsection

@section('content')
  {{-- ヘッダー行（並び替えなど任意） --}}
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">無料サンプル動画あり | 人気アダルト動画・AV作品を探す</h1>
    <div class="hidden sm:flex items-center gap-2 text-sm">
      <a href="{{ route('home', array_merge(request()->query(), ['sort' => 'favorite', 'page' => 1])) }}"
         class="px-3 py-1 rounded border hover:bg-gray-50 @if(request('sort','favorite')==='favorite') bg-gray-100 @endif">
        人気順
      </a>
      <a href="{{ route('home', array_merge(request()->query(), ['sort' => 'new', 'page' => 1])) }}"
         class="px-3 py-1 rounded border hover:bg-gray-50 @if(request('sort')==='new') bg-gray-100 @endif">
        新着順
      </a>
    </div>
  </div>

  <p class="text-gray-600 text-sm mb-6">
  DUGAで配信中の人気アダルト動画を厳選掲載。無料サンプル動画・画像、出演者・カテゴリ情報も充実。
</p>

  @php
  $crumbs = [
    ['label' => 'トップ', 'url' => route('home')],
  ];
@endphp
@include('partials.breadcrumbs', ['crumbs' => $crumbs])
  @php
    // $items は LengthAwarePaginator / Paginator / Collection / 配列 を想定
    $list = is_iterable($items) ? $items : [];
  @endphp

  @if (empty($list) || (is_countable($list) && count($list) === 0))
    <div class="rounded border border-dashed p-8 text-center text-gray-500">
      表示できる商品がありません。
    </div>
  @else
    {{-- カードグリッド --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
      @foreach ($list as $it)
        @php
          // ===== ID =====
          $id =
            (is_object($it) && method_exists($it, 'getProductid')) ? $it->getProductid()
            : (is_array($it) ? ($it['productid'] ?? $it['id'] ?? null) : null);

          // ===== タイトル =====
          $title =
            (is_object($it) && method_exists($it, 'getTitle')) ? $it->getTitle()
            : (is_array($it) ? ($it['title'] ?? $it['originaltitle'] ?? '') : '');

          // ===== 発売日 =====
          $release =
            (is_object($it) && method_exists($it, 'getReleasedate')) ? $it->getReleasedate()
            : ((is_object($it) && method_exists($it, 'getReleaseDate')) ? $it->getReleaseDate()
            : (is_array($it) ? ($it['releasedate'] ?? $it['opendate'] ?? '') : ''));

          // ===== 画像URL（Poster優先 / Jacketフォールバック）=====
          $posterUrl = null;
          if (is_object($it) && method_exists($it, 'getPosterImage')) {
              $pi = $it->getPosterImage();
              if ($pi) {
                  if (method_exists($pi, 'getSmall')  && !$posterUrl) $posterUrl = $pi->getSmall();
                  if (method_exists($pi, 'getMedium') && !$posterUrl) $posterUrl = $pi->getMedium();
                  if (method_exists($pi, 'getMidium') && !$posterUrl) $posterUrl = $pi->getMidium(); // 綴り揺れ対策
              }
          }
          if (!$posterUrl && is_object($it) && method_exists($it, 'getJacketImage')) {
              $ji = $it->getJacketImage();
              if ($ji) {
                  if (method_exists($ji, 'getSmall')  && !$posterUrl) $posterUrl = $ji->getSmall();
                  if (method_exists($ji, 'getMedium') && !$posterUrl) $posterUrl = $ji->getMedium();
                  if (method_exists($ji, 'getMidium') && !$posterUrl) $posterUrl = $ji->getMidium();
              }
          }
          if (!$posterUrl && is_array($it)) {
              $posterUrl = data_get($it, 'posterimage.0.small')
                        ?? data_get($it, 'posterimage.1.medium')
                        ?? data_get($it, 'posterimage.1.midium')
                        ?? data_get($it, 'jacketimage.0.small');
          }
          // ランキングを Item から取得
          $rank = method_exists($it,'getRanking') ? (int)$it->getRanking() : null;

          // ランキング色の割り当て
        $rankColor = match($rank) {
            1       => 'bg-yellow-400 text-yellow-900', // 金
            2       => 'bg-gray-300 text-gray-800',     // 銀
            3       => 'bg-amber-700 text-amber-100',   // 銅
            default => 'bg-gray-500 text-white',        // 4位以下
        };
        @endphp

        @if ($id)
          <a href="{{ route('products.show', ['id' => $id]) }}"
     class="group block overflow-hidden rounded-lg bg-white shadow hover:shadow-md transition relative">
    @if($rank)
      <div class="absolute left-2 top-2 z-10">
        <span class="inline-flex items-center justify-center rounded-full text-xs font-bold px-2 py-1 shadow
          @if($rank === 1) bg-yellow-400 text-yellow-900
          @elseif($rank === 2) bg-gray-300 text-gray-800
          @elseif($rank === 3) bg-amber-700 text-amber-100
          @else bg-gray-500 text-white
          @endif">
          {{ $rank }}位
        </span>
      </div>
    @endif

        {{-- サムネ --}}
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

    {{-- ページネーション（Paginator のとき表示） --}}
    @if ($items instanceof \Illuminate\Contracts\Pagination\Paginator)
      <div class="mt-6">
        @include('partials.pagination', ['paginator' => $items])
      </div>
    @endif
  @endif
@endsection
