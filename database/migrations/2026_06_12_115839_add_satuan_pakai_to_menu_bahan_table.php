<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_bahan', function (Blueprint $table) {
            $table->string('satuan_pakai')
                  ->default('gram')
                  ->after('jumlah_pakai');
        });
    }

    public function down(): void
    {
        Schema::table('menu_bahan', function (Blueprint $table) {
            $table->dropColumn('satuan_pakai');
        });
    }
};
