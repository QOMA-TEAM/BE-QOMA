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
        Schema::create('bahan_master', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('usaha_id');
            $table->string('nama');
            $table->enum('satuan', ['kg', 'gram', 'liter', 'pcs', 'porsi', 'lusin', 'botol', 'sachet'])->nullable();
            $table->decimal('harga_default', 12, 2)->default(0);
            $table->string('gambar')->nullable();
            $table->timestamps();

            $table->foreign('usaha_id')->references('id')->on('usaha')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahan_master');
    }
};
