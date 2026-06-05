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
        Schema::create('outlet_deactivation_queue', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('usaha_id');
            $table->string('subscription_id');
            $table->integer('jumlah_harus_nonaktif'); // berapa outlet yang harus dinonaktifkan
            $table->timestamp('deadline')->nullable(); // deadline pilih, lewat → auto
            $table->boolean('is_processed')->default(false);
            $table->timestamps();

            $table->foreign('usaha_id')->references('id')->on('usaha')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_deactivation_queue');
    }
};
