{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<rss version="2.0">

  <channel>
    <title><![CDATA[DUGAアダルト動画を見るならここ]]></title>

    {{-- チャンネルURL --}}
    <link>{{ $channel['link'] }}</link>

    {{-- RSS自体のURL（購読側の更新判定に効くことが多い） --}}
    <atom:link href="{{ $channel['self'] ?? url()->current() }}" rel="self" type="application/rss+xml" />

    <description><![CDATA[{{ $channel['description'] }}]]></description>
    <language>{{ $channel['language'] }}</language>

    {{-- RSS2.0らしく入れておくと安定 --}}
    <lastBuildDate>{{ $channel['lastBuildDate'] }}</lastBuildDate>

    @foreach($items as $p)
      @php
        $link = route('products.show', ['id' => $p->productid]);

        $pub = $p->release_date
          ? \Carbon\Carbon::parse($p->open_date)->toRssString()
          : optional($p->created_at)->toRssString();

        $imgUrl = $p->jacket_large ?: $p->poster_large ?: '';
        $desc = '';

        if ($imgUrl) {
          $desc .= '<p><img src="' . e($imgUrl) . '" alt="' . e($p->title) . '" style="max-width:100%;height:auto;" /></p>';
        }
        $desc .= '<p>' . ($p->caption ?: $p->title) . '</p>';

        $enclosureUrl = $p->poster_large ?: $p->jacket_large;

        // guidは「その記事を一意に識別」できる文字列が推奨（URLに寄せるのが無難）
        $guid = $link;
      @endphp

      <item>
        <title><![CDATA[{{ $p->title }}]]></title>
        <link>{{ $link }}</link>
        <guid isPermaLink="true">{{ $guid }}</guid>
        <pubDate>{{ $pub }}</pubDate>
        <description><![CDATA[{!! $desc !!}]]></description>
      </item>
    @endforeach

  </channel>
</rss>