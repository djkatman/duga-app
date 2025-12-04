<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('duga_products', function (Blueprint $table) {
            //
            $table->text('original_title')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('duga_products', function (Blueprint $table) {
            //
            $table->string('original_title', 255)->nullable()->change();
        });
    }
};
