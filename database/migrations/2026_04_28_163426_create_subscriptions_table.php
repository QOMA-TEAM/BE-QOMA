<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('usaha_id');
        $table->string('plan_id');
        $table->date('start_date');
        $table->date('end_date')->nullable();
        $table->string('status')->default('active'); // pending, active, expired, cancelled
        $table->string('tipe')->default('new');       // ← BARU: new, upgrade
        $table->date('grace_period_end')->nullable(); // ← BARU: end_date + 3 hari
        $table->timestamps();

        $table->foreign('usaha_id')->references('id')->on('usaha')->cascadeOnDelete();
        $table->foreign('plan_id')->references('id')->on('plans');
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};