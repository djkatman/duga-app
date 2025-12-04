<?php
// database/migrations/2025_11_10_000000_create_product_views.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('product_views', function (Blueprint $t) {
      $t->id();
      $t->string('productid', 64)->index();
      $t->date('view_date')->index();       // 集計用（YYYY-MM-DD）
      $t->timestamp('created_at')->index(); // 生データ（任意）
      $t->string('fp', 64)->nullable();     // 簡易フィンガープリント（重複防止に利用）
      $t->index(['productid','view_date']);
    });
  }
  public function down(): void { Schema::dropIfExists('product_views'); }
};