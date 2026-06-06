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
        Schema::create('bahan_outlet_approval', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('bahan_outlet_id');    // FK ke bahan_outlet
            $table->string('outlet_id');
            $table->string('usaha_id');
            $table->decimal('harga_lama', 12, 2); // harga_default sebelumnya
            $table->decimal('harga_baru', 12, 2); // harga yang diajukan
            $table->text('alasan');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('catatan_owner')->nullable();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamps();

            $table->foreign('bahan_outlet_id')->references('id')->on('bahan_outlet')->onDelete('cascade');
            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->foreign('usaha_id')->references('id')->on('usaha')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahan_outlet_approval');
    }
};
