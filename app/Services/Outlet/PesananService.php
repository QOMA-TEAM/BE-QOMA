<?php
namespace App\Services\Outlet;

use App\Models\{BahanOutlet, LaporanKeuangan, Meja, Menu, MenuOutlet, Pembayaran, Pesanan, PesananDetail, StockMovement};
use App\Services\{ActivityLogService, LaporanKeuanganService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PesananService
{
    public function __construct(
        private LaporanKeuanganService $laporanService
    ) {}

    /**
     * Ambil list pesanan milik outlet ini
     */
    public function getList(string $outletId, array $filters = [])
    {
        $query = Pesanan::where('outlet_id', $outletId)
                        ->with(['meja:id,nomor_meja', 'details.menu:id,nama'])
                        ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Detail 1 pesanan
     */
    public function getDetail(string $pesananId, string $outletId): Pesanan
    {
        return Pesanan::where('id', $pesananId)
                      ->where('outlet_id', $outletId)
                      ->with([
                          'meja:id,nomor_meja',
                          'details.menu:id,nama,gambar',
                          'details.addons.addon:id,nama,harga',
                          'pembayaran',
                      ])
                      ->firstOrFail();
    }

    /**
     * Kasir tambah item ke pesanan (sebelum paid)
     */
    public function tambahItem(Pesanan $pesanan, array $items): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar, tidak bisa diubah.');
        }

        DB::transaction(function () use ($pesanan, $items) {
            foreach ($items as $item) {
                $menu = Menu::findOrFail($item['menu_id']);

                // Ambil harga dari menu_outlet (harga override per outlet)
                $menuOutlet = MenuOutlet::where('menu_id', $menu->id)
                                        ->where('outlet_id', $pesanan->outlet_id)
                                        ->first();

                $harga = $menuOutlet ? $menuOutlet->harga : $menu->harga_default;

                // Cek apakah item sudah ada di pesanan → update qty
                $existing = PesananDetail::where('pesanan_id', $pesanan->id)
                                         ->where('menu_id', $menu->id)
                                         ->first();

                if ($existing) {
                    $existing->update(['qty' => $existing->qty + $item['qty']]);
                } else {
                    PesananDetail::create([
                        'id'         => Str::uuid(),
                        'pesanan_id' => $pesanan->id,
                        'menu_id'    => $menu->id,
                        'qty'        => $item['qty'],
                        'harga'      => $harga,
                    ]);
                }
            }

            // Recalculate total harga
            $this->recalculateTotal($pesanan);
        });

        return $pesanan->fresh(['meja', 'details.menu', 'pembayaran']);
    }

    /**
     * Kasir hapus item dari pesanan (sebelum paid)
     */
    public function hapusItem(Pesanan $pesanan, string $detailId): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar, tidak bisa diubah.');
        }

        $detail = PesananDetail::where('id', $detailId)
                               ->where('pesanan_id', $pesanan->id)
                               ->firstOrFail();

        $detail->delete();
        $this->recalculateTotal($pesanan);

        return $pesanan->fresh(['meja', 'details.menu']);
    }

    /**
     * Update qty item pesanan
     */
    public function updateQtyItem(Pesanan $pesanan, string $detailId, int $qty): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar, tidak bisa diubah.');
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

        return $pesanan->fresh(['meja', 'details.menu']);
    }

    /**
     * Konfirmasi pesanan (pending → confirmed)
     */
    public function konfirmasi(Pesanan $pesanan): Pesanan
    {
        if ($pesanan->status !== 'pending') {
            throw new \Exception('Hanya pesanan berstatus pending yang bisa dikonfirmasi.');
        }

        $pesanan->update(['status' => 'confirmed']);

        ActivityLogService::log(
            'konfirmasi_pesanan',
            "Pesanan #{$pesanan->id} dikonfirmasi",
            ['pesanan_id' => $pesanan->id],
            null,
            $pesanan->outlet_id,
        );

        return $pesanan->fresh(['meja', 'details.menu']);
    }

    /**
     * Konfirmasi pembayaran (confirmed → paid)
     *
     * Flow setelah paid:
     * 1. Update status pesanan
     * 2. Buat record pembayaran
     * 3. Auto kurangi stok bahan baku
     * 4. Catat stock movement
     * 5. Recalculate laporan keuangan
     */
    public function konfirmasiPembayaran(Pesanan $pesanan, string $metode): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar sebelumnya.');
        }

        if ($pesanan->status !== 'confirmed') {
            throw new \Exception('Pesanan harus dikonfirmasi dulu sebelum pembayaran.');
        }

        return DB::transaction(function () use ($pesanan, $metode) {

            // 1. Update status pesanan
            $pesanan->update(['status' => 'paid']);

            // 2. Buat record pembayaran
            Pembayaran::create([
                'id'          => Str::uuid(),
                'pesanan_id'  => $pesanan->id,
                'metode'      => $metode,
                'jumlah_bayar'=> $pesanan->total_harga,
                'status'      => 'paid',
                'psid_at'     => now(),
            ]);

            // 3. Auto kurangi stok bahan baku
            $this->kurangiStokOtomatis($pesanan);

            // 4. Update laporan keuangan hari ini
            $this->laporanService->recalculate($pesanan->outlet_id, now()->toDateString());

            ActivityLogService::log(
                'konfirmasi_pembayaran',
                "Pembayaran pesanan #{$pesanan->id} dikonfirmasi via {$metode}. Total: Rp " . number_format($pesanan->total_harga),
                ['pesanan_id' => $pesanan->id, 'metode' => $metode, 'total' => $pesanan->total_harga],
                null,
                $pesanan->outlet_id,
            );

            return $pesanan->fresh(['meja', 'details.menu', 'pembayaran']);
        });
    }

    /**
     * Cancel pesanan
     */
    public function cancel(Pesanan $pesanan): Pesanan
    {
        if ($pesanan->status === 'paid') {
            throw new \Exception('Pesanan sudah dibayar, tidak bisa dibatalkan.');
        }

        $pesanan->update(['status' => 'cancelled']);

        ActivityLogService::log(
            'cancel_pesanan',
            "Pesanan #{$pesanan->id} dibatalkan",
            ['pesanan_id' => $pesanan->id],
            null,
            $pesanan->outlet_id,
        );

        return $pesanan->fresh();
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function recalculateTotal(Pesanan $pesanan): void
    {
        $total = PesananDetail::where('pesanan_id', $pesanan->id)
                              ->selectRaw('SUM(harga * qty) as total')
                              ->value('total') ?? 0;

        $pesanan->update(['total_harga' => $total]);
    }

    /**
     * Auto kurangi stok saat pesanan dibayar
     */
    private function kurangiStokOtomatis(Pesanan $pesanan): void
    {
        $details = PesananDetail::where('pesanan_id', $pesanan->id)
                                ->with('menu.bahanMasters')
                                ->get();

        foreach ($details as $detail) {
            foreach ($detail->menu->bahanMasters as $bahan) {
                $jumlahKurang = $bahan->pivot->jumlah_pakai * $detail->qty;

                // Kurangi stok di bahan_outlet
                $bahanOutlet = BahanOutlet::where('outlet_id', $pesanan->outlet_id)
                                          ->where('bahan_master_id', $bahan->id)
                                          ->first();

                if ($bahanOutlet) {
                    $bahanOutlet->decrement('stok', $jumlahKurang);

                    // Catat stock movement
                    StockMovement::create([
                        'id'              => Str::uuid(),
                        'outlet_id'       => $pesanan->outlet_id,
                        'bahan_master_id' => $bahan->id,
                        'type'            => 'out',
                        'quantity'        => $jumlahKurang,
                        'reference_id'    => $pesanan->id,
                        'note'            => "Pesanan #{$pesanan->id} - {$detail->menu->nama} x{$detail->qty}",
                    ]);
                }
            }
        }
    }
}