<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('outlet_id');
            $table->string('bahan_master_id');
            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->decimal('quantity', 12, 2);
            $table->decimal('remaining_quantity', 12, 2); // ← BARU: sisa batch ini
            $table->date('expired_date')->nullable();
            $table->boolean('is_finished')->default(false); // ← BARU: batch habis
            $table->string('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};