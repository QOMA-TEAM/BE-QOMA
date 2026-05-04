<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    // bahan_master — dibuat owner, template bahan baku global per usaha
    Schema::create('bahan_master', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('usaha_id');
        $table->string('nama');
        $table->string('satuan')->nullable();
        $table->decimal('harga_default', 12, 2)->default(0);
        $table->string('gambar')->nullable();
        $table->timestamps();

        $table->foreign('usaha_id')->references('id')->on('usaha')->onDelete('cascade');
    });

    // bahan_outlet — stok aktual per outlet
    Schema::create('bahan_outlet', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('outlet_id');
        $table->string('bahan_master_id');
        $table->decimal('stok', 12, 2)->default(0);
        $table->decimal('stok_minimum', 12, 2)->default(5);
        $table->date('tanggal_masuk')->nullable();
        $table->date('tanggal_kadaluarsa')->nullable();
        $table->timestamps();

        $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
        $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('cascade');
        $table->unique(['outlet_id', 'bahan_master_id']); // 1 bahan hanya 1 record per outlet
    });

    // menu_bahan — bahan baku yang dipakai tiap menu
    Schema::create('menu_bahan', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('menu_id');
        $table->string('bahan_master_id');
        $table->decimal('jumlah_pakai', 12, 2)->default(0);
        $table->timestamps();

        $table->foreign('menu_id')->references('id')->on('menu')->onDelete('cascade');
        $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('cascade');
        $table->unique(['menu_id', 'bahan_master_id']);
    });

    // menu_outlet — harga per outlet (bisa override dari harga_default)
    Schema::create('menu_outlet', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('menu_id');
        $table->string('outlet_id');
        $table->decimal('harga', 12, 2)->default(0);
        $table->boolean('is_available')->default(true);
        $table->timestamps();

        $table->foreign('menu_id')->references('id')->on('menu')->onDelete('cascade');
        $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
        $table->unique(['menu_id', 'outlet_id']);
    });

    // addon — tambahan menu (extra topping, dll)
    Schema::create('addon', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('usaha_id');
        $table->string('nama');
        $table->decimal('harga', 12, 2)->default(0);
        $table->timestamps();

        $table->foreign('usaha_id')->references('id')->on('usaha')->onDelete('cascade');
    });

    // meja — tiap outlet punya meja dengan QR code unik
    Schema::create('meja', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('outlet_id');
        $table->string('nomor_meja');
        $table->string('qr_code')->nullable();
        $table->timestamps();

        $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
    });

    // pesanan
    Schema::create('pesanan', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('outlet_id');
        $table->string('meja_id')->nullable();
        $table->string('nama_pelanggan');
        $table->string('no_telp')->nullable();
        $table->decimal('total_harga', 12, 2)->default(0);
        $table->enum('status', ['pending', 'confirmed', 'paid', 'cancelled'])->default('pending');
        $table->timestamps();

        $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
        $table->foreign('meja_id')->references('id')->on('meja')->onDelete('set null');
    });

    // pesanan_detail — item per pesanan
    Schema::create('pesanan_detail', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('pesanan_id');
        $table->string('menu_id');
        $table->integer('qty')->default(1);
        $table->decimal('harga', 12, 2)->default(0);
        $table->timestamps();

        $table->foreign('pesanan_id')->references('id')->on('pesanan')->onDelete('cascade');
        $table->foreign('menu_id')->references('id')->on('menu')->onDelete('restrict');
    });

    // pesanan_addon
    Schema::create('pesanan_addon', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('pesanan_detail_id');
        $table->string('addon_id');
        $table->integer('qty')->default(1);
        $table->timestamps();

        $table->foreign('pesanan_detail_id')->references('id')->on('pesanan_detail')->onDelete('cascade');
        $table->foreign('addon_id')->references('id')->on('addon')->onDelete('cascade');
    });

    // pembayaran
    Schema::create('pembayaran', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('pesanan_id')->unique();
        $table->string('metode')->nullable();
        $table->decimal('jumlah_bayar', 12, 2)->default(0);
        $table->enum('status', ['pending', 'paid'])->default('pending');
        $table->timestamp('psid_at')->nullable(); // paid_at
        $table->timestamps();

        $table->foreign('pesanan_id')->references('id')->on('pesanan')->onDelete('cascade');
    });

    // kerugian — kerugian per outlet
    Schema::create('kerugian', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('outlet_id');
        $table->decimal('total_rugi', 12, 2)->default(0);
        $table->date('tanggal');
        $table->timestamps();

        $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
    });

    // pengeluaran — pengeluaran bahan baku per outlet
    Schema::create('pengeluaran', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('outlet_id');
        $table->string('bahan_master_id')->nullable();
        $table->string('sumber')->nullable(); // keterangan pembelian bahan baku
        $table->decimal('total', 12, 2)->default(0);
        $table->date('tanggal');
        $table->timestamps();

        $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
        $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('set null');
    });

    // Laporan keuangan per outlet per periode
    Schema::create('laporan_keuangan', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('outlet_id');
        $table->decimal('total_pendapatan', 15, 2)->default(0);
        $table->decimal('total_pengeluaran', 15, 2)->default(0);
        $table->decimal('total_kerugian', 15, 2)->default(0);
        $table->decimal('total_keuntungan', 15, 2)->default(0);
        $table->string('periode');
        $table->enum('tipe_periode', ['daily', 'monthly'])->default('daily');
        $table->timestamps();

        $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
        $table->unique(['outlet_id', 'periode', 'tipe_periode']);
    });
    
    Schema::create('stock_movements', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('outlet_id');
        $table->string('bahan_master_id');
        $table->enum('type', ['in', 'out', 'adjustment']);
        $table->decimal('quantity', 12, 2);
        $table->date('expired_date')->nullable();
        $table->string('reference_id')->nullable();
        $table->text('note')->nullable();
        $table->timestamps();

        $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
        $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('cascade');
    });

    Schema::create('stock_opname', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('outlet_id');
        $table->string('bahan_master_id');
        $table->enum('tipe', ['busuk', 'rusak', 'ga_layak', 'hilang']);
        $table->decimal('jumlah', 12, 2);
        $table->string('foto_bukti')->nullable();
        $table->text('keterangan')->nullable();
        $table->timestamps();

        $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');
        $table->foreign('bahan_master_id')->references('id')->on('bahan_master')->onDelete('cascade');
    });
}

public function down(): void
{
    Schema::dropIfExists('laporan_keuangan');
    Schema::dropIfExists('pengeluaran');
    Schema::dropIfExists('kerugian');
    Schema::dropIfExists('stock_opname');
    Schema::dropIfExists('stock_movements');
    Schema::dropIfExists('pembayaran');
    Schema::dropIfExists('pesanan_addon');
    Schema::dropIfExists('pesanan_detail');
    Schema::dropIfExists('pesanan');
    Schema::dropIfExists('meja');
    Schema::dropIfExists('addon');
    Schema::dropIfExists('menu_outlet');
    Schema::dropIfExists('menu_bahan');
    Schema::dropIfExists('bahan_outlet');
    Schema::dropIfExists('bahan_master');
}
};