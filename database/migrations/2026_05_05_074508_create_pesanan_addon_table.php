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
        Schema::create('pesanan_addon', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('pesanan_detail_id');
            $table->string('addon_id');
            $table->integer('qty')->default(1);
            $table->timestamps();

            $table->foreign('pesanan_detail_id')->references('id')->on('pesanan_detail')->onDelete('cascade');
            $table->foreign('addon_id')->references('id')->on('addon')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan_addon');
    }
};
