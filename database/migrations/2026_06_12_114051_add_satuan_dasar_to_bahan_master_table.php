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
        Schema::table('bahan_master', function (Blueprint $table) {
            // satuan_dasar = satuan terkecil yang dipakai untuk hitung stok
            // Contoh: satuan = kg, satuan_dasar = gram
            // Contoh: satuan = liter, satuan_dasar = ml
            // Contoh: satuan = pcs, satuan_dasar = pcs (tidak perlu konversi)
            $table->string('satuan_dasar')->default('gram')->after('satuan');
            $table->decimal('konversi_ke_dasar', 12, 4)->default(1)->after('satuan_dasar');
            // konversi_ke_dasar: 1 satuan = berapa satuan_dasar
            // Contoh: 1 kg = 1000 gram → konversi_ke_dasar = 1000
            // Contoh: 1 liter = 1000 ml → konversi_ke_dasar = 1000
            // Contoh: 1 pcs = 1 pcs → konversi_ke_dasar = 1
        });
    }

    public function down(): void
    {
        Schema::table('bahan_master', function (Blueprint $table) {
            $table->dropColumn(['satuan_dasar', 'konversi_ke_dasar']);
        });
    }
};
