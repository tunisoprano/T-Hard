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
        Schema::table('products', function (Blueprint $table) {
            // pgvector gibi özel bir eklenti kurmuyoruz — 150 ürünlük bu
            // ölçekte, embedding'i düz bir jsonb dizisi olarak saklayıp
            // benzerlik hesabını PHP tarafında yapmak yeterli ve basit.
            $table->jsonb('embedding')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });
    }
};
