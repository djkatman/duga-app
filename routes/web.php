<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DugaApiController;
use App\Http\Controllers\SitemapController;

Route::get('/', [DugaApiController::class, 'index'])->name('home');

// ★ 数字限定をやめ、英数・アンダースコア・ハイフンを許可
Route::get('/products/{id}', [DugaApiController::class, 'show'])
    ->where('id', '[A-Za-z0-9\-_]+')
    ->name('products.show');
// 詳細
Route::get('/browse/{type}/{id}', [DugaApiController::class, 'browse'])
    ->whereIn('type', ['category','label','series','performer','keyword'])
    ->name('browse.filter');
// 検索
Route::get('/search', [DugaApiController::class, 'search'])->name('search');
// サイトマップ
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');

// 分割サイトマップ（例：products のページ分割、lists は一覧のソート×ページ）
Route::get('/sitemap-products-{n}.xml', [SitemapController::class, 'products'])->whereNumber('n')->name('sitemap.products');
Route::get('/sitemap-lists.xml', [SitemapController::class, 'lists'])->name('sitemap.lists');
