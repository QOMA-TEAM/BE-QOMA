<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usaha', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('nama_usaha');
            $table->string('email');
            $table->string('alamat')->nullable();
            $table->string('owner_id')->nullable(); // FK ke users, set after
            $table->enum('status', ['pending', 'active', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usaha');
    }
};