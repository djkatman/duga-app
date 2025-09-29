@props([
  // [['label'=>'トップ','url'=>route('home')], ['label'=>'カテゴリ: 〇〇','url'=>...], ['label'=>'作品タイトル']]
  'crumbs' => [],
])

@if(!empty($crumbs))
<nav class="mb-4" aria-label="パンくずリスト">
  <ol class="flex items-center gap-1 text-sm text-gray-600 overflow-x-auto no-scrollbar">
    @foreach($crumbs as $i => $c)
      @php
        $isLast = $i === array_key_last($crumbs);
        $label  = $c['label'] ?? '';
        $url    = $c['url']   ?? null;
      @endphp

      @if($i > 0)
        {{-- 区切り（>） --}}
        <li class="shrink-0 mx-1 text-gray-400">›</li>
      @endif

      <li class="shrink-0">
        @if(!$isLast && $url)
          <a href="{{ $url }}" class="hover:text-indigo-600 hover:underline">{{ $label }}</a>
        @else
          <span class="text-gray-900 font-medium">{{ $label }}</span>
        @endif
      </li>
    @endforeach
  </ol>
</nav>
@endif
