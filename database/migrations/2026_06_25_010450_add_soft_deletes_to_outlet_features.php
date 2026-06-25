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
        Schema::table('bahan_master', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('kategori_menu', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('menu', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('meja', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('addon', function (Blueprint $table) { $table->softDeletes(); });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bahan_master', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('kategori_menu', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('menu', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('meja', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('addon', function (Blueprint $table) { $table->dropSoftDeletes(); });
    }
};
