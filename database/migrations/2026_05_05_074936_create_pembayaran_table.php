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
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('pesanan_id')->unique();
            $table->string('metode')->nullable();
            $table->decimal('jumlah_bayar', 12, 2)->default(0);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamp('psid_at')->nullable();
            $table->timestamps();

            $table->foreign('pesanan_id')->references('id')->on('pesanan')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
