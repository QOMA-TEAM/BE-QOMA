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
        Schema::create('menu_bahan', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('menu_id');
            $table->string('bahan_master_id');
            $table->decimal('jumlah_pakai', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('menu_id')->references('id')->on('menu')->onDelete('cascade');
            $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('cascade');
            $table->unique(['menu_id', 'bahan_master_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_bahan');
    }
};
