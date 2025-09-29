<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DugaApiController;

// トップページ
Route::get('/', [DugaApiController::class, 'index'])->name('home');
// 詳細ページ
Route::get('/products/{id}', [DugaApiController::class, 'show'])->name('products.show');
// 絞り込み一覧（type: category|label|series|performer）
Route::get('/browse/{type}/{id}', [DugaApiController::class, 'browse'])
    ->whereIn('type', ['category','label','series','performer'])
    ->name('browse.filter');
// キーワード検索
Route::get('/search', [DugaApiController::class, 'search'])->name('search');
