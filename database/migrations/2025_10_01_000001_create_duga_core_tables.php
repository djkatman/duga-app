<?php
// database/migrations/2025_10_01_000001_create_duga_core_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up() {
    Schema::create('duga_products', function (Blueprint $t) {
      $t->id();
      $t->string('productid')->unique();      // API 主キー
      $t->string('title')->index();
      $t->string('original_title')->nullable();
      $t->text('caption')->nullable();
      $t->string('maker')->nullable();
      $t->string('item_no')->nullable();
      $t->unsignedInteger('price')->nullable();
      $t->unsignedInteger('volume')->nullable();
      $t->date('release_date')->nullable();
      $t->date('open_date')->nullable();
      $t->unsignedTinyInteger('rating')->nullable(); // 0-5
      $t->unsignedInteger('mylist_total')->nullable();
      $t->unsignedInteger('ranking_total')->nullable();
      $t->string('url')->nullable();
      $t->string('affiliate_url')->nullable();

      // 画像（代表だけ）
      $t->string('poster_small')->nullable();
      $t->string('poster_medium')->nullable();
      $t->string('poster_large')->nullable();
      $t->string('jacket_small')->nullable();
      $t->string('jacket_medium')->nullable();
      $t->string('jacket_large')->nullable();

      // メタ
      $t->timestamp('synced_at')->nullable(); // API 同期時刻
      $t->timestamps();
    });

    Schema::create('duga_categories', function (Blueprint $t) {
      $t->id();
      $t->string('duga_id')->unique();
      $t->string('name')->index();
      $t->timestamps();
    });

    Schema::create('duga_performers', function (Blueprint $t) {
      $t->id();
      $t->string('duga_id')->unique();
      $t->string('name')->index();
      $t->string('kana')->nullable();
      $t->timestamps();
    });

    // pivot
    Schema::create('duga_category_product', function (Blueprint $t) {
      $t->unsignedBigInteger('duga_product_id');
      $t->unsignedBigInteger('duga_category_id');
      $t->primary(['duga_product_id','duga_category_id']);
    });
    Schema::create('duga_performer_product', function (Blueprint $t) {
      $t->unsignedBigInteger('duga_product_id');
      $t->unsignedBigInteger('duga_performer_id');
      $t->primary(['duga_product_id','duga_performer_id']);
    });
  }
  public function down() {
    Schema::dropIfExists('duga_performer_product');
    Schema::dropIfExists('duga_category_product');
    Schema::dropIfExists('duga_performers');
    Schema::dropIfExists('duga_categories');
    Schema::dropIfExists('duga_products');
  }
};