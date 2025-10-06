<?php

// database/migrations/2025_10_05_000002_extend_duga_detail_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up() {

    // 作品に1:多 → "1" をプロダクト側に外部キーとして持つ
    Schema::table('duga_products', function (Blueprint $t) {
      $t->unsignedBigInteger('label_id')->nullable()->after('maker')->index();
      $t->unsignedBigInteger('series_id')->nullable()->after('label_id')->index();

      // レビューの集計値を作品テーブルに持たせて高速化
      $t->unsignedTinyInteger('review_rating')->nullable()->after('ranking_total'); // 0-5
      $t->unsignedInteger('review_count')->nullable()->after('review_rating');
    });

    // レーベル / シリーズ（ユニークID + 名前）
    Schema::create('duga_labels', function (Blueprint $t) {
      $t->id();
      $t->string('duga_id')->unique();
      $t->string('name')->index();
      $t->timestamps();
    });
    Schema::create('duga_series', function (Blueprint $t) {
      $t->id();
      $t->string('duga_id')->unique();
      $t->string('name')->index();
      $t->timestamps();
    });

    // 監督（多:多）
    Schema::create('duga_directors', function (Blueprint $t) {
      $t->id();
      $t->string('duga_id')->unique();
      $t->string('name')->index();
      $t->timestamps();
    });
    Schema::create('duga_director_product', function (Blueprint $t) {
      $t->unsignedBigInteger('duga_product_id');
      $t->unsignedBigInteger('duga_director_id');
      $t->primary(['duga_product_id','duga_director_id']);
    });

    // サンプル動画（作品:1 ←→ サンプル:多 を想定。実運用は通常1レコード）
    Schema::create('duga_product_samples', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('duga_product_id')->index();
      $t->string('movie_url')->nullable();
      $t->string('capture_url')->nullable();
      $t->timestamps();
      $t->unique(['duga_product_id','movie_url']);
    });

    // サンプル画像（サムネURLと拡大版URLを両方保持）
    Schema::create('duga_product_thumbnails', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('duga_product_id')->index();
      $t->string('thumb_url');        // noauth/scap/...
      $t->string('full_url')->nullable(); // cap/... など変換先
      $t->unsignedSmallInteger('sort_order')->default(0);
      $t->timestamps();
      $t->unique(['duga_product_id','thumb_url']);
    });

    // 販売形態（例: ストリーミング / ダウンロード / 価格）
    Schema::create('duga_product_sale_types', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('duga_product_id')->index();
      $t->string('type')->index();         // e.g. 'stream','download' などAPI値
      $t->unsignedInteger('price')->nullable();
      $t->timestamps();
      $t->unique(['duga_product_id','type']);
    });
  }

  public function down() {
    Schema::dropIfExists('duga_product_sale_types');
    Schema::dropIfExists('duga_product_thumbnails');
    Schema::dropIfExists('duga_product_samples');
    Schema::dropIfExists('duga_director_product');
    Schema::dropIfExists('duga_directors');
    Schema::dropIfExists('duga_series');
    Schema::dropIfExists('duga_labels');

    Schema::table('duga_products', function (Blueprint $t) {
      $t->dropColumn(['label_id','series_id','review_rating','review_count']);
    });
  }
};