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
        Schema::create('kerugian', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('outlet_id');
            $table->decimal('total_rugi', 12, 2)->default(0);
            $table->date('tanggal');
            $table->timestamps();

            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kerugian');
    }
};
