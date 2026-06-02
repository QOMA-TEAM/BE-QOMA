<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('nama_plan'); // free trial, pro
            $table->decimal('harga', 15, 2);
            $table->integer('batas_outlet');
            $table->integer('durasi_hari')->default(30);
            $table->boolean('is_lifetime')->default(false);
            $table->string('deskripsi')->nullable();

            $table->enum('status', ['aktif', 'tidak aktif'])
                  ->default('aktif');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};