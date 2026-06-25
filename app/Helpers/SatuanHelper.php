<?php
namespace App\Helpers;

class SatuanHelper
{
    // Mapping satuan ke satuan dasar
    private static array $konversi = [
        'kg'     => ['dasar' => 'gram', 'faktor' => 1000],
        'gram'   => ['dasar' => 'gram', 'faktor' => 1],
        'liter'  => ['dasar' => 'ml',   'faktor' => 1000],
        'ml'     => ['dasar' => 'ml',   'faktor' => 1],
        'pcs'    => ['dasar' => 'pcs',  'faktor' => 1],
        'porsi'  => ['dasar' => 'porsi','faktor' => 1],
        'lusin'  => ['dasar' => 'pcs',  'faktor' => 12],
        'botol'  => ['dasar' => 'botol','faktor' => 1],
        'sachet' => ['dasar' => 'sachet','faktor' => 1],
    ];

    /**
     * Konversi jumlah dari satuan input ke satuan dasar
     * Contoh: konversi(2, 'kg') → 2000 (gram)
     * Contoh: konversi(500, 'gram') → 500 (gram)
     */
    public static function keSatuanDasar(float $jumlah, string $satuan): float
    {
        $satuan = strtolower($satuan);
        $faktor = self::$konversi[$satuan]['faktor'] ?? 1;
        return $jumlah * $faktor;
    }

    /**
     * Konversi dari satuan dasar ke satuan display
     * Contoh: dariSatuanDasar(2000, 'kg') → 2
     * Contoh: dariSatuanDasar(500, 'gram') → 500
     */
    public static function dariSatuanDasar(float $jumlahDasar, string $satuan): float
    {
        $satuan = strtolower($satuan);
        $faktor = self::$konversi[$satuan]['faktor'] ?? 1;
        return $faktor > 0 ? $jumlahDasar / $faktor : $jumlahDasar;
    }

    /**
     * Ambil satuan dasar dari satuan input
     * Contoh: getSatuanDasar('kg') → 'gram'
     */
    public static function getSatuanDasar(string $satuan): string
    {
        $satuan = strtolower($satuan);
        return self::$konversi[$satuan]['dasar'] ?? $satuan;
    }

    /**
     * Apakah satuan ini butuh konversi?
     */
    public static function butuhKonversi(string $satuan): bool
    {
        $satuan = strtolower($satuan);
        return isset(self::$konversi[$satuan]) && self::$konversi[$satuan]['faktor'] !== 1;
    }
}
