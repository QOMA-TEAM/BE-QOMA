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
        Schema::create('addon', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('usaha_id');
            $table->string('nama');
            $table->decimal('harga', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('usaha_id')->references('id')->on('usaha')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon');
    }
};
