@if ($paginator instanceof \Illuminate\Contracts\Pagination\Paginator)
  @php
    // 現在ページ／総ページ／総件数
    $current = $paginator->currentPage();
    $last    = $paginator->lastPage();
    $total   = method_exists($paginator, 'total') ? (int)$paginator->total() : null;

    // 表示範囲（firstItem/lastItem は LengthAwarePaginator なら利用可）
    $from = method_exists($paginator, 'firstItem') ? $paginator->firstItem() : null;
    $to   = method_exists($paginator, 'lastItem')  ? $paginator->lastItem()  : null;

    // 既存クエリを引き継ぎ（per_page だけ差し替えるためのベース）
    $qs = request()->query();
  @endphp

  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    {{-- 件数要約 --}}
    <div class="text-sm text-gray-600">
      @if(!is_null($total) && !is_null($from) && !is_null($to))
        全 {{ number_format($total) }} 件中 {{ number_format($from) }}–{{ number_format($to) }} 件を表示
      @elseif(!is_null($total))
        全 {{ number_format($total) }} 件
      @else
        ページ {{ number_format($current) }} / {{ number_format($last) }}
      @endif
    </div>

    {{-- 1ページあたり件数（per_page） --}}
    <form method="get" class="flex items-center gap-2 text-sm">
      @foreach($qs as $k => $v)
        @if($k !== 'per_page')
          <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
      @endforeach

      <label for="perPage" class="text-gray-600">表示件数</label>
      <select id="perPage" name="per_page"
              class="rounded border px-2 py-1"
              onchange="this.form.submit()">
        @foreach([24,40,60,80,100] as $n)
          <option value="{{ $n }}" @selected((int)request('per_page', $paginator->perPage()) === $n)>{{ $n }}</option>
        @endforeach
      </select>
    </form>

    {{-- ページリンク（Tailwind） --}}
    <div>
      {{-- withQueryString で q/sort/per_page などを維持 --}}
      {{ $paginator->withQueryString()->onEachSide(1)->links() }}
    </div>
  </div>
@endif
