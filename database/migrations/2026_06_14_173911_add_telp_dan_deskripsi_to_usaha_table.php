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
        Schema::table('usaha', function (Blueprint $table) {
            $table->string('telp_usaha')->nullable()->after('email');
            $table->text('deskripsi_usaha')->nullable()->after('alamat');
        });
    }

    public function down(): void
    {
        Schema::table('usaha', function (Blueprint $table) {
            $table->dropColumn(['telp_usaha', 'deskripsi_usaha']);
        });
    }
};
