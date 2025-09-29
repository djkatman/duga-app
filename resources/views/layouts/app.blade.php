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
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-F49G5PN2JV"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-F49G5PN2JV');
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

        {{-- PC: 検索フォーム --}}
        <form action="{{ route('search') }}" method="get" class="hidden md:flex items-center gap-2 flex-1 max-w-xl ml-auto">
          <input type="text" name="q" value="{{ request('q') }}"
                 placeholder="キーワード検索"
                 class="w-full rounded border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
          <button type="submit"
                  class="inline-flex items-center justify-center px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 whitespace-nowrap">
            検索
          </button>
        </form>

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

    {{-- Mobile Menu + 検索フォーム --}}
    <div id="mobileMenu" class="md:hidden hidden border-t border-gray-200">
      <div class="px-4 py-3">
        <form action="{{ route('search') }}" method="get" class="flex items-center gap-2">
          <input type="text" name="q" value="{{ request('q') }}"
                 placeholder="キーワード検索"
                 class="w-full rounded border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
          <button type="submit"
                  class="inline-flex items-center justify-center px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 whitespace-nowrap">
            検索
          </button>
        </form>
      </div>
      <nav class="px-4 pb-3 space-y-2 text-sm">
        <a href="{{ route('home', ['sort'=>'favorite']) }}" class="block px-3 py-2 rounded hover:bg-gray-100">人気順</a>
        <a href="{{ route('home', ['sort'=>'new']) }}" class="block px-3 py-2 rounded hover:bg-gray-100">新着順</a>
      </nav>
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
