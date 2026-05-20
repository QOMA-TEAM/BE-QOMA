<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('role_id')->nullable();
            $table->string('usaha_id')->nullable();
            $table->string('outlet_id')->nullable();
            $table->string('username')->unique();
            $table->string('nama_lengkap')->nullable();
            $table->string('email')->unique();        // ← UNIQUE & NOT NULLABLE
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};