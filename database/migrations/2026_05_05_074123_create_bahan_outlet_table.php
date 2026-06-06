<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bahan_outlet', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('outlet_id');
            $table->string('bahan_master_id');
            $table->decimal('stok', 12, 2)->default(0);       // ← total stok saja
            $table->decimal('stok_minimum', 12, 2)->default(5);
            $table->timestamps();

            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('cascade');
            $table->unique(['outlet_id', 'bahan_master_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahan_outlet');
    }
};