<?php
namespace App\Services\Outlet;

use App\Models\Meja;
use App\Services\ActivityLogService;
use Illuminate\Support\Str;

class MejaService
{
    public function getByOutlet(string $outletId)
    {
        return Meja::where('outlet_id', $outletId)
                   ->orderBy('nomor_meja')
                   ->get();
    }

    public function create(string $outletId, string $nomorMeja): Meja
    {
        // Cek duplikat nomor meja di outlet yang sama
        if (Meja::where('outlet_id', $outletId)->where('nomor_meja', $nomorMeja)->exists()) {
            throw new \Exception("Meja nomor {$nomorMeja} sudah ada di outlet ini.");
        }

        $mejaId = Str::uuid();

        // Generate QR code sebagai URL string yang mengarah ke halaman user dengan query params outlet_id dan meja_id 
        // Frontend yang render QR imagenya dari URL ini 
        $qrUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/') . "/user?outlet_id={$outletId}&meja_id={$mejaId}";

        $meja = Meja::create([
            'id'         => $mejaId,
            'outlet_id'  => $outletId,
            'nomor_meja' => $nomorMeja,
            'qr_code'    => $qrUrl,
        ]);

        ActivityLogService::log(
            'create_meja',
            "Meja nomor {$nomorMeja} ditambahkan",
            ['meja_id' => $meja->id],
            null,
            $outletId,
        );

        return $meja;
    }

    public function delete(Meja $meja): void
    {
        // Cek apakah ada pesanan aktif di meja ini
        $adaPesananAktif = $meja->pesanans()
                               ->whereIn('status', ['pending', 'confirmed'])
                               ->exists();

        if ($adaPesananAktif) {
            throw new \Exception('Meja tidak bisa dihapus karena masih ada pesanan aktif.');
        }

        $meja->delete();
    }
}
