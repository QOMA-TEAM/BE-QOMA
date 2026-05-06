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
        Schema::create('laporan_keuangan', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('outlet_id');
            $table->decimal('total_pendapatan', 15, 2)->default(0);
            $table->decimal('total_pengeluaran', 15, 2)->default(0);
            $table->decimal('total_kerugian', 15, 2)->default(0);
            $table->decimal('total_keuntungan', 15, 2)->default(0);
            $table->string('periode');
            $table->enum('tipe_periode', ['daily', 'monthly'])->default('daily');
            $table->timestamps();

            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->unique(['outlet_id', 'periode', 'tipe_periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_keuangan');
    }
};
