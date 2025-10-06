<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>@yield('title', 'DUGAサンプル動画見放題')</title>

  {{-- Tailwind / Vite --}}
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @yield('meta')

  {{-- Google Analytics GA4 --}}
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-BD3JD8GZ2C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-BD3JD8GZ2C');
  </script>
</head>
<body class="min-h-dvh bg-gray-50 text-gray-900 antialiased">
  {{-- Header --}}
  <header class="bg-white border-b border-gray-200">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-lg">
          <span>🎬</span>
          <span>DUGAサンプル動画見放題</span>
        </a>

        @php
          // 現在のソート／クエリ（pageだけはリセットしたいので除外）
          $currentSort = request('sort', 'favorite');
          $baseQuery   = request()->except('page', 'sort');
          $sortMap = [
            'favorite' => '人気順',
            'release'  => '発売日順',
            'new'      => '新着順',
            'price'    => '価格順',
            'rating'   => '評価順',
            'mylist'   => 'マイリスト登録順',
          ];
        @endphp

        {{-- PC: 検索フォーム --}}
      <form action="{{ route('search') }}" method="get" class="hidden md:flex items-center gap-2 flex-1 max-w-xl ml-auto">
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="キーワード検索"
               class="w-full rounded border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
        {{-- ソートを検索にも引き継ぐ --}}
        <input type="hidden" name="sort" value="{{ $currentSort }}">
        <button type="submit"
                class="inline-flex items-center justify-center px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 whitespace-nowrap">
          検索
        </button>
      </form>

      {{-- PC: ソートリンク --}}
      <nav class="hidden md:flex items-center gap-2 ml-4">
        @foreach($sortMap as $key => $label)
          @php
            $url = route('home', array_merge($baseQuery, ['sort' => $key]));
            $active = $currentSort === $key;
          @endphp
          <a href="{{ $url }}"
             class="text-sm px-3 py-1.5 rounded {{ $active ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}"
             aria-current="{{ $active ? 'page' : 'false' }}">
            {{ $label }}
          </a>
        @endforeach
      </nav>


        {{-- Mobile: Hamburger --}}
        <button id="menuButton"
          class="md:hidden inline-flex items-center justify-center rounded-md p-2 hover:bg-gray-100 focus:outline-none"
          aria-expanded="false" aria-controls="mobileMenu">
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none">
            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
    </div>

    {{-- Mobile Menu + 検索フォーム + ソート --}}
  <div id="mobileMenu" class="md:hidden hidden border-t border-gray-200">
    <div class="px-4 py-3">
      <form action="{{ route('search') }}" method="get" class="flex items-center gap-2">
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="キーワード検索"
               class="w-full rounded border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
        {{-- ソートを検索にも引き継ぐ --}}
        <input type="hidden" name="sort" value="{{ $currentSort }}">
        <button type="submit"
                class="inline-flex items-center justify-center px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 whitespace-nowrap">
          検索
        </button>
      </form>
    </div>

    {{-- ソート一覧（モバイル） --}}
    <div class="px-4 pb-3">
      <div class="text-xs text-gray-500 mb-2">表示順</div>
      <nav class="grid grid-cols-2 gap-2 text-sm">
        @foreach($sortMap as $key => $label)
          @php
            $url = route('home', array_merge($baseQuery, ['sort' => $key]));
            $active = $currentSort === $key;
          @endphp
          <a href="{{ $url }}"
             class="block px-3 py-2 rounded border {{ $active ? 'border-indigo-600 text-indigo-700 bg-indigo-50' : 'border-gray-200 text-gray-700 hover:bg-gray-100' }}"
             aria-current="{{ $active ? 'page' : 'false' }}">
            {{ $label }}
          </a>
        @endforeach
      </nav>
    </div>
  </div>
</header>

  {{-- Main --}}
  <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
    @yield('content')
  </main>

  {{-- Footer --}}
  <footer class="mt-12 border-t border-gray-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-600">
      <p>© {{ date('Y') }} DUGAサンプル動画見放題</p>
    </div>
  </footer>

  {{-- Mobile Menu Toggle（Vanilla JS） --}}
  <script>
    const btn = document.getElementById('menuButton');
    const menu = document.getElementById('mobileMenu');
    btn?.addEventListener('click', () => {
      menu.classList.toggle('hidden');
      const expanded = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!expanded));
    });
  </script>
</body>
</html>
