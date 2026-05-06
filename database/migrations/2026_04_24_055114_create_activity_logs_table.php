<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->nullable();
            $table->string('usaha_id')->nullable();
            $table->string('outlet_id')->nullable();
            $table->string('aktivitas');
            $table->text('deskripsi')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};