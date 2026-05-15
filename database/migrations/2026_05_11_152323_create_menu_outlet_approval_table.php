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
        Schema::create('menu_outlet_approval', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('menu_outlet_id');          // FK ke menu_outlet
            $table->string('outlet_id');
            $table->string('usaha_id');
            $table->decimal('harga_lama', 12, 2);      // harga sebelum perubahan
            $table->decimal('harga_baru', 12, 2);      // harga yang diajukan
            $table->text('alasan');                    // keterangan kenapa naik
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('catatan_owner')->nullable(); // catatan saat approve/reject
            $table->timestamp('diproses_at')->nullable();
            $table->timestamps();

            $table->foreign('menu_outlet_id')->references('id')->on('menu_outlet')->onDelete('cascade');
            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->foreign('usaha_id')->references('id')->on('usaha')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_outlet_approval');
    }
};
