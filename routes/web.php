<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DugaApiController;
use App\Http\Controllers\SitemapController;

Route::get('/', [DugaApiController::class, 'index'])->name('home');

// ★ 数字限定をやめ、英数・アンダースコア・ハイフンを許可
Route::get('/products/{id}', [DugaApiController::class, 'show'])
    ->where('id', '[A-Za-z0-9\-_]+')
    ->name('products.show');

Route::get('/browse/{type}/{id}', [DugaApiController::class, 'browse'])
    ->whereIn('type', ['category','label','series','performer','keyword'])
    ->name('browse.filter');

Route::get('/search', [DugaApiController::class, 'search'])->name('search');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
