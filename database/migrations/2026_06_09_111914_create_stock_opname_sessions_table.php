<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel sesi (1 per outlet per hari)
        Schema::create('stock_opname_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('outlet_id');
            $table->date('tanggal');              // tanggal sesi (1 hari = 1 sesi)
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->unique(['outlet_id', 'tanggal']); // ← 1 outlet max 1 sesi per hari
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_sessions');
    }
};