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
        Schema::create('stock_opname', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('outlet_id');
            $table->string('session_id');              // ← FK ke stock_opname_sessions
            $table->string('bahan_master_id');
            $table->enum('tipe', ['busuk', 'rusak', 'ga_layak', 'hilang']);
            $table->decimal('jumlah', 12, 2);
            $table->string('foto_bukti')->nullable();
            $table->text('keterangan')->nullable();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->timestamp('finalized_at')->nullable(); // kapan item ini di-final
            $table->timestamps();

            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('stock_opname_sessions')->onDelete('cascade');
            $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname');
    }
};
