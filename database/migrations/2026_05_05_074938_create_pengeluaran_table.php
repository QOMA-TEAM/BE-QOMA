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
        Schema::create('pengeluaran', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('outlet_id');
            $table->string('bahan_master_id')->nullable();
            $table->string('sumber')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->date('tanggal');
            $table->timestamps();

            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengeluaran');
    }
};
