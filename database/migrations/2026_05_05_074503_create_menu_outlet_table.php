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
        Schema::create('menu_outlet', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('menu_id');
            $table->string('outlet_id');
            $table->decimal('harga', 12, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->foreign('menu_id')->references('id')->on('menu')->onDelete('cascade');
            $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
            $table->unique(['menu_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_outlet');
    }
};
