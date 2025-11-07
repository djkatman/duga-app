@extends('layouts.app')

@php
  $title = $pageTitle ?? 'このサイトについて';
  $desc  = $pageDesc  ?? 'DUGAサンプル動画見放題サイトの概要、ポリシー、問い合わせ先などを掲載します。';
  $canonical = url()->current();
@endphp

@section('title', $title)

@section('meta')
  <meta name="description" content="{{ $desc }}">
  <link rel="canonical" href="{{ $canonical }}">

  <meta property="og:site_name" content="DUGAサンプル動画見放題">
  <meta property="og:type" content="website">
  <meta property="og:title" content="{{ $title }}">
  <meta property="og:description" content="{{ $desc }}">
  <meta property="og:url" content="{{ $canonical }}">
@endsection

@section('content')
  @include('partials.breadcrumbs', [
    'crumbs' => [
      ['label' => 'トップ', 'url' => route('home')],
      ['label' => $title],
    ]
  ])

  <div class="bg-white rounded-lg shadow p-6 space-y-6">
    <h1 class="text-2xl font-bold">{{ $title }}</h1>
    <p class="text-gray-700 leading-relaxed">
      本サイトは DUGA の作品情報をもとに、サンプル動画・画像、出演者・カテゴリ等の情報を見やすく提供する非公式のファンサイトです。
    </p>

    <div class="space-y-3 text-sm text-gray-700">
      <div>
        <h2 class="text-lg font-semibold">運営方針</h2>
        <ul class="list-disc ml-5 mt-2 space-y-1">
          <li>正確で最新の情報を提供するよう努めますが、内容を保証するものではありません。</li>
          <li>外部リンクの一部はアフィリエイトリンクを含みます（該当箇所で明示します）。</li>
        </ul>
      </div>

      <div>
        <h2 class="text-lg font-semibold">権利表記</h2>
        <p class="mt-2">各作品の画像・テキスト等の権利は、それぞれの権利者に帰属します。</p>
      </div>

      <div>
        <h2 class="text-lg font-semibold">解析について</h2>
        <p class="mt-2">
          本サイトでは、利用状況の把握とサービス改善のため、
          <strong>Google Analytics 4（GA4）</strong> を利用しています。
          Google により Cookie などを通じてデータが収集・処理される場合があります。
          収集される情報には、閲覧ページ、クリック、使用ブラウザ、利用地域などが含まれる場合がありますが、
          これらの情報は個人を特定するものではなく、サイトの利用動向の分析目的にのみ使用されます。
        </p>

        <p class="mt-2">
          取得されたデータは Google LLC のプライバシーポリシーに基づき管理されます。
          詳細は
          <a href="https://policies.google.com/privacy?hl=ja" target="_blank" rel="noopener" class="text-indigo-600 underline">
            Google プライバシーポリシー
          </a>
          をご参照ください。
        </p>

        <p class="mt-2">
          また、Google が提供する
          <a href="https://tools.google.com/dlpage/gaoptout?hl=ja" target="_blank" rel="noopener" class="text-indigo-600 underline">
            Google アナリティクス オプトアウト アドオン
          </a>
          を利用することで、解析データの収集を停止できます。
        </p>

        <p class="mt-2">
          本サイトでは、訪問者の同意に基づいて解析を有効化する仕組み（Consent Mode v2）を導入しています。
          解析を許可または無効化する場合は、下のボタンを利用できます。
        </p>

        <div class="flex gap-3 mt-3">
          <button id="acceptAnalytics"
                  class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">
            解析を許可
          </button>
          <button id="optoutAnalytics"
                  class="px-3 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 text-sm">
            解析を無効化
          </button>
        </div>

        <script>
          document.getElementById('acceptAnalytics')?.addEventListener('click', function(){
            gtag('consent', 'update', { 'analytics_storage': 'granted' });
            localStorage.setItem('ga_analytics_optin', '1');
            alert('解析を許可しました。');
          });
          document.getElementById('optoutAnalytics')?.addEventListener('click', function(){
            gtag('consent', 'update', { 'analytics_storage': 'denied' });
            localStorage.removeItem('ga_analytics_optin');
            alert('解析を無効化しました。');
          });
        </script>
      </div>

    </div>
  </div>
@endsection