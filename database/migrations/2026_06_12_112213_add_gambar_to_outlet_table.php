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
        Schema::table('outlet', function (Blueprint $table) {
            $table->string('gambar_icon')->nullable()->after('status_buka');
            $table->string('gambar_header')->nullable()->after('gambar_icon');
        });
    }

    public function down(): void
    {
        Schema::table('outlet', function (Blueprint $table) {
            $table->dropColumn(['gambar_icon', 'gambar_header']);
        });
    }
};
