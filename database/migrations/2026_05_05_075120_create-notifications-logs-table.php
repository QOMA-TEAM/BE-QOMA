<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('notifications', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('user_id');
        $table->string('title');
        $table->text('message');
        $table->boolean('is_read')->default(false);
        $table->string('type')->nullable(); // 'new_owner', 'new_subscription', dll
        $table->json('data')->nullable();   // payload tambahan
        $table->timestamps();

        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    });
}

public function down(): void
{
    Schema::dropIfExists('notifications');
}
};