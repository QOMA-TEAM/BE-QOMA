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
        Schema::create('pesanan', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('outlet_id');
            $table->string('meja_id')->nullable();
            $table->string('nama_pelanggan');
            $table->string('no_telp')->nullable();
            $table->decimal('total_harga', 12, 2)->default(0);
            $table->enum('status', ['pending', 'confirmed', 'paid', 'cancelled', 'expired'])
                ->default('pending');                                    
            $table->enum('tipe_pesanan', ['dine_in', 'take_away'])
                ->default('dine_in');                                   
            $table->timestamp('expired_at')->nullable();                // ← BARU (10 menit dari created_at)
            $table->timestamps();

            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->foreign('meja_id')->references('id')->on('meja')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan');
    }
};
