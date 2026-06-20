<?php
namespace App\Services\Outlet;

use App\Events\{PesananDiupdate, PesananExpired};
use App\Models\{BahanOutlet, Menu, MenuOutlet, Pembayaran, Pesanan, PesananDetail, StockMovement};
use App\Services\{ActivityLogService, LaporanKeuanganService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\Outlet\BahanOutletService;

class PesananService
{
    public function __construct(
        private LaporanKeuanganService $laporanService
    ) {}

    /**
     * Ambil list pesanan milik outlet
     * Auto-expire pesanan pending yang sudah lewat 10 menit
     */

    // Update method getList()
    public function getList(string $outletId, array $filters = [])
    {
        $this->autoExpirePesanan($outletId);

        $query = Pesanan::select('id', 'outlet_id', 'meja_id', 'nama_pelanggan', 'no_telp', 'total_harga', 'status', 'tipe_pesanan', 'expired_at', 'created_at')
                        ->where('outlet_id', $outletId)
                        ->with([
                            'meja:id,nomor_meja',
                            'details:id,pesanan_id,menu_id,qty,harga',
                            'details.menu:id,nama',
                            'pembayaran:id,pesanan_id,metode',
                        ])
                        ->latest();

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            // Kalau ada filter status spesifik, terapkan
            $query->where('status', $filters['status']);
        } elseif (empty($filters['status'])) {
            // Default: tidak menampilkan yang sudah paid atau cancelled (disesuaikan dengan kebutuhan frontend)
            // Biarkan frontend yang mem-filter (pending, confirmed, expired)
            // Atau cukup sembunyikan 'paid' dan 'cancelled' jika tidak diminta
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                ->orWhere('nama_pelanggan', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function getDetail(string $pesananId, string $outletId): Pesanan
    {
        $pesanan = Pesanan::select('id', 'outlet_id', 'meja_id', 'nama_pelanggan', 'no_telp', 'total_harga', 'status', 'tipe_pesanan', 'expired_at', 'created_at')
                        ->where('id', $pesananId)
                        ->where('outlet_id', $outletId)
                        ->with([
                            'meja:id,nomor_meja',
                            'details:id,pesanan_id,menu_id,qty,harga',
                            'details.menu:id,nama,gambar',
                            'details.addons:id,pesanan_detail_id,addon_id,qty',
                            'details.addons.addon:id,nama,harga',
                            'pembayaran:id,pesanan_id,metode,jumlah_bayar,status,psid_at',
                        ])
                        ->firstOrFail();

        if ($pesanan->isExpired()) {
            $this->prosesExpired($pesanan);
            $pesanan->refresh();
        }

        return $pesanan;
    }

    /**
     * Buat pesanan baru — set expired_at 10 menit dari sekarang
     * Dipanggil dari PesananPublicController
     */
    public function buatPesanan(array $data): Pesanan
    {
        return Pesanan::create(array_merge($data, [
            'expired_at' => now()->addMinutes(10), // ← 10 menit dari created_at
        ]));
    }

    /**
     * Konfirmasi pesanan oleh kasir (pending → confirmed)
     * Reset expired_at karena kasir sudah konfirmasi
     */
    public function konfirmasi(Pesanan $pesanan): Pesanan
    {
        if ($pesanan->status === 'expired') {
            throw new \Exception('Pesanan sudah expired, tidak bisa dikonfirmasi.');
        }

        if ($pesanan->status !== 'pending') {
            throw new \Exception('Hanya pesanan berstatus pending yang bisa dikonfirmasi.');
        }

        // Cek expired sebelum konfirmasi
        if ($pesanan->isExpired()) {
            $this->prosesExpired($pesanan);
            throw new \Exception('Pesanan sudah expired karena melewati batas waktu 10 menit.');
        }

        $pesanan->update([
            'status'     => 'confirmed',
            'expired_at' => null, // ← hapus expired_at setelah dikonfirmasi
        ]);

        ActivityLogService::log(
            'konfirmasi_pesanan',
            "Pesanan #{$pesanan->id} dikonfirmasi",
            ['pesanan_id' => $pesanan->id],
            null,
            $pesanan->outlet_id,
        );

        broadcast(new PesananDiupdate($pesanan->fresh(), 'konfirmasi'))->toOthers();

        return $pesanan->fresh(['meja', 'details.menu', 'details.addons.addon']);

    }

    /**
     * Update tipe pesanan (dine_in ↔ take_away)
     * Bisa diubah kasir sampai sebelum paid
     */
    public function updateTipePesanan(Pesanan $pesanan, string $tipe): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar, tidak bisa diubah.');
        }

        if ($pesanan->status === 'expired') {
            throw new \Exception('Pesanan sudah expired.');
        }

        if (!in_array($tipe, ['dine_in', 'take_away'])) {
            throw new \Exception('Tipe pesanan tidak valid. Gunakan: dine_in atau take_away');
        }

        $tipeLama = $pesanan->tipe_pesanan;
        $pesanan->update(['tipe_pesanan' => $tipe]);

        ActivityLogService::log(
            'update_tipe_pesanan',
            "Tipe pesanan #{$pesanan->id} diubah dari {$tipeLama} ke {$tipe}",
            ['pesanan_id' => $pesanan->id, 'tipe_lama' => $tipeLama, 'tipe_baru' => $tipe],
            null,
            $pesanan->outlet_id,
        );

        broadcast(new PesananDiupdate($pesanan->fresh(), 'update_tipe'))->toOthers();

        return $pesanan->fresh(['meja', 'details.menu', 'details.addons.addon']);

    }

    /**
     * Pelanggan cancel pesanan (hanya status pending)
     */
    public function cancelOlehPelanggan(Pesanan $pesanan): Pesanan
    {
        if ($pesanan->status !== 'pending') {
            throw new \Exception('Pesanan hanya bisa dibatalkan saat masih pending.');
        }

        if ($pesanan->isExpired()) {
            $this->prosesExpired($pesanan);
            throw new \Exception('Pesanan sudah expired.');
        }

        $pesanan->update(['status' => 'cancelled']);

        ActivityLogService::log(
            'cancel_pesanan_pelanggan',
            "Pesanan #{$pesanan->id} dibatalkan oleh pelanggan",
            ['pesanan_id' => $pesanan->id],
            null,
            $pesanan->outlet_id,
        );

        broadcast(new PesananDiupdate($pesanan->fresh(), 'cancel'))->toOthers();

        return $pesanan->fresh();
    }

    /**
     * Cancel oleh kasir
     */
    public function cancel(Pesanan $pesanan): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar, tidak bisa dibatalkan.');
        }

        $pesanan->update(['status' => 'cancelled']);

        ActivityLogService::log(
            'cancel_pesanan',
            "Pesanan #{$pesanan->id} dibatalkan oleh kasir",
            ['pesanan_id' => $pesanan->id],
            null,
            $pesanan->outlet_id,
        );

        broadcast(new PesananDiupdate($pesanan->fresh(), 'cancel'))->toOthers();

        return $pesanan->fresh();
    }

    /**
     * Konfirmasi pembayaran (confirmed → paid)
     */
    public function konfirmasiPembayaran(Pesanan $pesanan, string $metode): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar sebelumnya.');
        }

        if ($pesanan->status === 'expired') {
            throw new \Exception('Pesanan sudah expired.');
        }

        if ($pesanan->status !== 'confirmed') {
            throw new \Exception('Pesanan harus dikonfirmasi dulu sebelum pembayaran.');
        }

        return DB::transaction(function () use ($pesanan, $metode) {
            $pesanan->update(['status' => 'paid']);

            Pembayaran::create([
                'id'           => Str::uuid(),
                'pesanan_id'   => $pesanan->id,
                'metode'       => $metode,
                'jumlah_bayar' => $pesanan->total_harga,
                'status'       => 'paid',
                'psid_at'      => now(),
            ]);

            $this->kurangiStokOtomatis($pesanan);
            $this->laporanService->recalculate($pesanan->outlet_id, now()->toDateString());

            ActivityLogService::log(
                'konfirmasi_pembayaran',
                "Pembayaran pesanan #{$pesanan->id} dikonfirmasi via {$metode}. Total: Rp " . number_format($pesanan->total_harga),
                ['pesanan_id' => $pesanan->id, 'metode' => $metode, 'total' => $pesanan->total_harga],
                null,
                $pesanan->outlet_id,
            );

            broadcast(new PesananDiupdate($pesanan->fresh(), 'bayar'))->toOthers();

            return $pesanan->fresh(['meja', 'details.menu', 'details.addons.addon', 'pembayaran']);
        });
    }

    /**
     * Tambah item — cek expired dulu
     */
    public function tambahItem(Pesanan $pesanan, array $items): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar, tidak bisa diubah.');
        }

        if ($pesanan->status === 'expired') {
            throw new \Exception('Pesanan sudah expired.');
        }

        if ($pesanan->isExpired()) {
            $this->prosesExpired($pesanan);
            throw new \Exception('Pesanan expired saat proses berlangsung.');
        }

        DB::transaction(function () use ($pesanan, $items) {
            foreach ($items as $item) {
                $menu       = Menu::findOrFail($item['menu_id']);
                $menuOutlet = MenuOutlet::where('menu_id', $menu->id)
                                        ->where('outlet_id', $pesanan->outlet_id)
                                        ->first();
                $harga = $menuOutlet ? $menuOutlet->harga : $menu->harga_default;

                $existing = null;
                if (empty($item['addons'])) {
                    $existing = PesananDetail::where('pesanan_id', $pesanan->id)
                                             ->where('menu_id', $menu->id)
                                             ->doesntHave('addons')
                                             ->first();
                }

                if ($existing) {
                    $existing->increment('qty', $item['qty']);
                } else {
                    $detail = PesananDetail::create([
                        'id'         => Str::uuid(),
                        'pesanan_id' => $pesanan->id,
                        'menu_id'    => $menu->id,
                        'qty'        => $item['qty'],
                        'harga'      => $harga,
                    ]);

                    if (!empty($item['addons'])) {
                        foreach ($item['addons'] as $addon) {
                            \App\Models\PesananAddon::create([
                                'id'                => Str::uuid(),
                                'pesanan_detail_id' => $detail->id,
                                'addon_id'          => $addon['addon_id'],
                                'qty'               => $addon['qty'],
                            ]);
                        }
                    }
                }
            }
            $this->recalculateTotal($pesanan);
        });

        broadcast(new PesananDiupdate($pesanan->fresh(), 'edit_item'))->toOthers();

        return $pesanan->fresh(['meja', 'details.menu', 'details.addons.addon', 'pembayaran']);
    }

    /**
     * Hapus item
     */
    public function hapusItem(Pesanan $pesanan, string $detailId): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar, tidak bisa diubah.');
        }

        if ($pesanan->isExpired()) {
            $this->prosesExpired($pesanan);
            throw new \Exception('Pesanan sudah expired.');
        }

        $detail = PesananDetail::where('id', $detailId)
                               ->where('pesanan_id', $pesanan->id)
                               ->firstOrFail();

        $detail->delete();
        $this->recalculateTotal($pesanan);

        broadcast(new PesananDiupdate($pesanan->fresh(), 'edit_item'))->toOthers();

        return $pesanan->fresh(['meja', 'details.menu', 'details.addons.addon']);

    }

    /**
     * Update qty item
     */
    public function updateQtyItem(Pesanan $pesanan, string $detailId, int $qty): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar, tidak bisa diubah.');
        }

        if ($pesanan->isExpired()) {
            $this->prosesExpired($pesanan);
            throw new \Exception('Pesanan sudah expired.');
        }

        $detail = PesananDetail::where('id', $detailId)
                               ->where('pesanan_id', $pesanan->id)
                               ->firstOrFail();

        if ($qty <= 0) {
            $detail->delete();
        } else {
            $detail->update(['qty' => $qty]);
        }

        $this->recalculateTotal($pesanan);
        broadcast(new PesananDiupdate($pesanan->fresh(), 'edit_item'))->toOthers();

        return $pesanan->fresh(['meja', 'details.menu', 'details.addons.addon']);

    }

    // ubah autoExpirePesanan jadi public
    public function autoExpirePesananPublic(string $outletId): void
    {
        $this->autoExpirePesanan($outletId);
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function recalculateTotal(Pesanan $pesanan): void
    {
        $totalItems = PesananDetail::where('pesanan_id', $pesanan->id)
                              ->selectRaw('SUM(harga * qty) as total')
                              ->value('total') ?? 0;

        $totalAddons = \Illuminate\Support\Facades\DB::table('pesanan_addon')
            ->join('pesanan_detail', 'pesanan_addon.pesanan_detail_id', '=', 'pesanan_detail.id')
            ->join('addon', 'pesanan_addon.addon_id', '=', 'addon.id')
            ->where('pesanan_detail.pesanan_id', $pesanan->id)
            ->sum(\Illuminate\Support\Facades\DB::raw('addon.harga * pesanan_addon.qty'));

        $pesanan->update(['total_harga' => $totalItems + $totalAddons]);

    }

    /**
     * Proses expired — update status + broadcast
     */
    private function prosesExpired(Pesanan $pesanan): void
    {
        if ($pesanan->status !== 'pending') return;

        $pesanan->update(['status' => 'expired']);

        ActivityLogService::log(
            'pesanan_expired',
            "Pesanan #{$pesanan->id} expired (melebihi 10 menit)",
            ['pesanan_id' => $pesanan->id],
            null,
            $pesanan->outlet_id,
        );

        // Broadcast ke kasir (hilang dari list) & pelanggan (tampil pesan expired)
        broadcast(new PesananExpired($pesanan))->toOthers();
    }

    /**
     * Auto expire semua pesanan pending yang sudah lewat 10 menit
     * Dipanggil saat kasir fetch list pesanan
     */
    private function autoExpirePesanan(string $outletId): void
    {
        $pesananExpired = Pesanan::where('outlet_id', $outletId)
                                 ->where('status', 'pending')
                                 ->where('expired_at', '<=', now())
                                 ->get();

        foreach ($pesananExpired as $pesanan) {
            $this->prosesExpired($pesanan);
        }
    }

    private function kurangiStokOtomatis(Pesanan $pesanan): void
    {
        $bahanService = app(BahanOutletService::class);

        $details = PesananDetail::where('pesanan_id', $pesanan->id)
                                ->with('menu.bahanMasters')
                                ->get();

        foreach ($details as $detail) {
            foreach ($detail->menu->bahanMasters as $bahan) {
                $jumlahKurang = $bahan->pivot->jumlah_pakai * $detail->qty;

                // Cek apakah bahan ada di outlet
                $bahanOutlet = BahanOutlet::where('outlet_id', $pesanan->outlet_id)
                                        ->where('bahan_master_id', $bahan->id)
                                        ->first();

                if (!$bahanOutlet) continue;

                // Pakai FEFO — kurangi dari batch yang paling dekat expired
                try {
                    $bahanService->kurangiStokFEFO(
                        $pesanan->outlet_id,
                        $bahan->id,
                        $jumlahKurang,
                        $pesanan->id,
                        "Pesanan #{$pesanan->id} - {$detail->menu->nama} x{$detail->qty}"
                    );
                } catch (\Exception $e) {
                    // Stok tidak cukup — catat tapi tidak gagalkan pesanan
                    \Log::warning("Stok tidak cukup saat kurangi otomatis: {$e->getMessage()}");
                }
            }
        }
    }
}