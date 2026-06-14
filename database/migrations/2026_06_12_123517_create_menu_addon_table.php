<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_addon', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('menu_id');
            $table->string('addon_id');
            $table->timestamps();

            $table->foreign('menu_id')->references('id')->on('menu')->onDelete('cascade');
            $table->foreign('addon_id')->references('id')->on('addon')->onDelete('cascade');
            $table->unique(['menu_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_addon');
    }
};