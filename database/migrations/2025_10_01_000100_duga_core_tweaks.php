<?php

// database/migrations/2025_10_01_000100_duga_core_tweaks.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up() {
    Schema::table('duga_products', function (Blueprint $t) {
      // 長いURLでも落ちないよう桁増やし（SQLite は変更不要でもOK）
      $t->string('url', 1024)->nullable()->change();
      $t->string('affiliate_url', 1024)->nullable()->change();

      // 索引（存在チェックはDB毎に差があるので try/catch でも可）
      $t->index('maker');
      $t->index('item_no');
      $t->index('price');
      $t->index('release_date');
      $t->index('open_date');
      $t->index('rating');
      $t->index('mylist_total');
      $t->index('ranking_total');
      $t->index('synced_at');
    });

    Schema::table('duga_category_product', function (Blueprint $t) {
      // 既にprimaryがあればスキップ。無ければ unique などで重複防止
      // 外部キーは未設定なら付与（SQLite は一旦テーブル再作成になる場合あり）
    });

    Schema::table('duga_performer_product', function (Blueprint $t) {
      // 同上
    });
  }

  public function down() {
    // インデックスを巻き戻すならここに dropIndex を列挙
  }
};