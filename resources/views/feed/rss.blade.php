{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<rss version="2.0">
  <channel>
    <title><![CDATA[{{ $channel['title'] }}]]></title>
    <link>{{ $channel['link'] }}</link>
    <description><![CDATA[{{ $channel['description'] }}]]></description>
    <language>{{ $channel['language'] }}</language>
    <lastBuildDate>{{ $channel['lastBuildDate'] }}</lastBuildDate>

    @foreach($items as $p)
      @php
        $link = route('products.show', ['id' => $p->productid]);
        $pub  = $p->release_date
          ? \Carbon\Carbon::parse($p->release_date)->toRssString()
          : optional($p->created_at)->toRssString();
        $desc = $p->caption ?: $p->title;
      @endphp

      <item>
        <title><![CDATA[{{ $p->title }}]]></title>
        <link>{{ $link }}</link>
        <guid isPermaLink="false">{{ $p->productid }}</guid>
        <pubDate>{{ $pub }}</pubDate>
        <description><![CDATA[{!! \Illuminate\Support\Str::limit(strip_tags($desc), 800) !!}]]></description>

        {{-- 画像を入れたい場合（任意） --}}
        @if(!empty($p->poster_large))
          <enclosure url="{{ $p->poster_large }}" type="image/jpeg" />
        @elseif(!empty($p->jacket_large))
          <enclosure url="{{ $p->jacket_large }}" type="image/jpeg" />
        @endif
      </item>
    @endforeach
  </channel>
</rss>
