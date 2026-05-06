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
        Schema::create('pesanan_detail', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('pesanan_id');
            $table->string('menu_id');
            $table->integer('qty')->default(1);
            $table->decimal('harga', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('pesanan_id')->references('id')->on('pesanan')->onDelete('cascade');
            $table->foreign('menu_id')->references('id')->on('menu')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan_detail');
    }
};
