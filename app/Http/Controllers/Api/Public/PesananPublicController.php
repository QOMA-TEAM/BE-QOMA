<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\{Addon, Meja, Menu, MenuOutlet, Outlet, Pesanan, PesananAddon, PesananDetail};
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Events\PesananBaru;

class PesananPublicController extends Controller
{
    /**
     * POST /public/pesanan
     *
     * Pelanggan buat pesanan setelah scan QR dan pilih menu.
     *
     * Flow:
     * 1. Validasi outlet buka
     * 2. Validasi meja valid
     * 3. Validasi semua item (menu harus milik outlet ini)
     * 4. Validasi addon (opsional)
     * 5. Hitung total harga
     * 6. Simpan pesanan + detail + addon
     * 7. Return response ke pelanggan
     */
    public function store(Request $request)
    {
        // Di method store() — fix validasi
        $request->validate([
            'outlet_id'                  => 'required|string|exists:outlet,id',
            'meja_id'                    => 'required|string|exists:meja,id',
            'nama_pelanggan'             => 'required|string|max:100',
            'no_telp'                    => 'nullable|string|max:20',
            'items'                      => 'required|array|min:1',
            'items.*.menu_id'            => 'required|string|exists:menu,id',
            'items.*.qty'                => 'required|integer|min:1',
            'items.*.addons'             => 'nullable|array',
            'items.*.addons.*.addon_id'  => 'required|string|exists:addon,id',
            'items.*.addons.*.qty'       => 'required|integer|min:1',
            'tipe_pesanan'               => 'nullable|in:dine_in,take_away',
        ]);

        // 1. Validasi outlet buka
        $outlet = Outlet::find($request->outlet_id);
        if (!$outlet->status_buka) {
            return response()->json([
                'message' => 'Outlet sedang tutup. Pesanan tidak bisa dibuat.',
                'code'    => 'OUTLET_CLOSED',
            ], 403);
        }

        // 2. Validasi meja milik outlet ini
        $meja = Meja::where('id', $request->meja_id)
                    ->where('outlet_id', $request->outlet_id)
                    ->first();

        if (!$meja) {
            return response()->json([
                'message' => 'Meja tidak valid.',
                'code'    => 'MEJA_NOT_VALID',
            ], 422);
        }

        // 3. Validasi semua menu milik usaha outlet ini + ambil harga
        $usahaId    = $outlet->usaha_id;
        $itemsData  = [];
        $totalHarga = 0;

        foreach ($request->items as $index => $item) {
            $menu = Menu::where('id', $item['menu_id'])
                        ->where('usaha_id', $usahaId)
                        ->where('is_active', true)
                        ->first();

            if (!$menu) {
                return response()->json([
                    'message' => "Menu pada item ke-" . ($index + 1) . " tidak valid atau tidak aktif.",
                    'code'    => 'MENU_NOT_VALID',
                ], 422);
            }

            // Cek apakah menu available di outlet ini
            $menuOutlet = MenuOutlet::where('menu_id', $menu->id)
                                    ->where('outlet_id', $request->outlet_id)
                                    ->first();

            if ($menuOutlet && !$menuOutlet->is_available) {
                return response()->json([
                    'message' => "Menu '{$menu->nama}' sedang tidak tersedia di outlet ini.",
                    'code'    => 'MENU_NOT_AVAILABLE',
                ], 422);
            }

            // Ambil harga — override outlet atau default
            $harga         = $menuOutlet ? (float) $menuOutlet->harga : (float) $menu->harga_default;
            $subtotalMenu  = $harga * $item['qty'];

            // 4. Validasi dan hitung addon (opsional)
            $addonsData   = [];
            $subtotalAddon = 0;

            if (!empty($item['addons'])) {
                foreach ($item['addons'] as $addonItem) {
                    $addon = Addon::where('id', $addonItem['addon_id'])
                                ->where('usaha_id', $usahaId)
                                ->first();

                    if (!$addon) {
                        return response()->json([
                            'message' => "Addon tidak valid atau bukan milik usaha ini.",
                            'code'    => 'ADDON_NOT_VALID',
                        ], 422);
                    }

                    $addonTerdaftar = DB::table('menu_addon')
                                        ->where('menu_id', $menu->id)
                                        ->where('addon_id', $addon->id)
                                        ->exists();

                    if (!$addonTerdaftar) {
                        return response()->json([
                            'message' => "Addon '{$addon->nama}' tidak tersedia untuk menu '{$menu->nama}'.",
                            'code'    => 'ADDON_NOT_FOR_MENU',
                        ], 422);
                    }

                    $subtotalAddon += (float) $addon->harga * $addonItem['qty'];

                    $addonsData[] = [
                        'addon_id' => $addon->id,
                        'qty'      => $addonItem['qty'],
                        'harga'    => (float) $addon->harga,
                        'nama'     => $addon->nama,
                    ];
                }
            }

            $itemsData[] = [
                'menu_id'  => $menu->id,
                'nama'     => $menu->nama,
                'qty'      => $item['qty'],
                'harga'    => $harga,
                'addons'   => $addonsData,
                'subtotal' => $subtotalMenu + $subtotalAddon,
            ];

            $totalHarga += $subtotalMenu + $subtotalAddon;
        }

        // 5. Simpan semua dalam 1 transaction
        return DB::transaction(function () use ($request, $outlet, $meja, $itemsData, $totalHarga) {

            // Buat pesanan
            $pesanan = Pesanan::create([
                'id'             => Str::uuid(),
                'outlet_id'      => $outlet->id,
                'meja_id'        => $meja->id,
                'nama_pelanggan' => $request->nama_pelanggan,
                'no_telp'        => $request->no_telp,
                'total_harga'    => $totalHarga,
                'status'         => 'pending',
                'tipe_pesanan'   => $request->tipe_pesanan ?? 'dine_in', 
                'expired_at'     => now()->addMinutes(10),                
            ]);

            // Simpan detail item + addon
            foreach ($itemsData as $item) {
                $detail = PesananDetail::create([
                    'id'         => Str::uuid(),
                    'pesanan_id' => $pesanan->id,
                    'menu_id'    => $item['menu_id'],
                    'qty'        => $item['qty'],
                    'harga'      => $item['harga'],
                ]);

                // Simpan addon per detail (opsional)
                foreach ($item['addons'] as $addon) {
                    PesananAddon::create([
                        'id'                => Str::uuid(),
                        'pesanan_detail_id' => $detail->id,
                        'addon_id'          => $addon['addon_id'],
                        'qty'               => $addon['qty'],
                    ]);
                }
            }

            ActivityLogService::log(
                'pesanan_masuk',
                "Pesanan baru dari {$request->nama_pelanggan} (Meja {$meja->nomor_meja}). Total: Rp " . number_format($totalHarga),
                ['pesanan_id' => $pesanan->id, 'total' => $totalHarga],
                null,
                $outlet->id,
            );

            // Broadcast event ke kasir (real-time update)
            try {
                broadcast(new PesananBaru($pesanan->load('meja', 'details')))->toOthers();
            } catch (\Exception $e) {
                \Log::warning('Broadcast PesananBaru gagal: ' . $e->getMessage());
            }

            // Response ke pelanggan
            return response()->json([
                'message' => 'Pesanan berhasil dibuat! Silakan menuju kasir untuk pembayaran.',
                'data'    => [
                    'pesanan_id'     => $pesanan->id,
                    'nomor_meja'     => $meja->nomor_meja,
                    'nama_pelanggan' => $pesanan->nama_pelanggan,
                    'no_telp'        => $pesanan->no_telp,
                    'items'          => collect($itemsData)->map(fn($i) => [
                        'nama'     => $i['nama'],
                        'qty'      => $i['qty'],
                        'harga'    => $i['harga'],
                        'addons'   => collect($i['addons'])->map(fn($a) => [
                            'nama'  => $a['nama'],
                            'qty'   => $a['qty'],
                            'harga' => $a['harga'],
                        ]),
                        'subtotal' => $i['subtotal'],
                    ]),
                    'total_harga' => $totalHarga,
                    'status'      => 'pending',
                    'pesan'       => 'Tunjukkan ID pesanan ini ke kasir untuk pembayaran.',
                ],
            ], 201);
        });
    }

    /**
     * GET /public/pesanan/{id}?outlet_id=xxx
     *
     * Pelanggan cek status pesanannya.
     */
    public function show(string $id, Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|string|exists:outlet,id',
        ]);

        $pesanan = Pesanan::where('id', $id)
                          ->where('outlet_id', $request->outlet_id)
                          ->with([
                              'meja:id,nomor_meja',
                              'details.menu:id,nama,gambar',
                              'details.addons.addon:id,nama,harga',
                              'pembayaran',
                          ])
                          ->first();

        if (!$pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        $sisaDetik = $pesanan->expired_at && $pesanan->status === 'pending'
            ? max(0, now()->diffInSeconds($pesanan->expired_at, false))
            : null;

        $statusLabel = match($pesanan->status) {
            'pending'   => 'Menunggu konfirmasi kasir',
            'confirmed' => 'Dikonfirmasi — silakan lakukan pembayaran',
            'paid'      => 'Lunas ✓',
            'cancelled' => 'Dibatalkan',
            'expired'   => 'Pesanan kedaluwarsa',
            default     => $pesanan->status,
        };

        // Timer berhenti kalau bukan pending
        $timerAktif = $pesanan->status === 'pending';
        $sisaDetik  = ($timerAktif && $pesanan->expired_at)
                        ? max(0, now()->diffInSeconds($pesanan->expired_at, false))
                        : null;

        $statusLabel = match($pesanan->status) {
            'pending'   => 'Menunggu konfirmasi kasir',
            'confirmed' => 'Dikonfirmasi — silakan lakukan pembayaran',
            'paid'      => 'Lunas ✓',
            'cancelled' => 'Dibatalkan',
            default     => $pesanan->status,
        };

        return response()->json([
            'message' => 'Detail pesanan',
            'data'    => [
                'pesanan_id'     => $pesanan->id,
                'nomor_meja'     => $pesanan->meja->nomor_meja ?? '-',
                'nama_pelanggan' => $pesanan->nama_pelanggan,
                'no_telp'        => $pesanan->no_telp,
                'status'         => $pesanan->status,
                'status_label'   => $statusLabel,
                'tipe_pesanan'     => $pesanan->tipe_pesanan,
                'expired_at'       => $pesanan->expired_at?->format('Y-m-d H:i:s'),
                'sisa_waktu_detik' => $sisaDetik,
                'is_expired'       => $pesanan->status === 'expired',
                'total_harga'    => (float) $pesanan->total_harga,
                'items'          => $pesanan->details->map(fn($d) => [
                    'nama'    => $d->menu->nama ?? '-',
                    'qty'     => $d->qty,
                    'harga'   => (float) $d->harga,
                    'subtotal'=> (float) ($d->harga * $d->qty),
                    'addons'  => $d->addons->map(fn($a) => [
                        'nama'  => $a->addon->nama ?? '-',
                        'qty'   => $a->qty,
                        'harga' => (float) ($a->addon->harga ?? 0),
                    ]),
                ]),
                'pembayaran' => $pesanan->pembayaran ? [
                    'metode'      => $pesanan->pembayaran->metode,
                    'jumlah_bayar'=> (float) $pesanan->pembayaran->jumlah_bayar,
                    'paid_at'     => $pesanan->pembayaran->psid_at,
                ] : null,
            ],
        ]);
    }

    // POST /public/pesanan/{id}/cancel
    public function cancel(Request $request, string $id)
    {
        $request->validate([
            'outlet_id' => 'required|string|exists:outlet,id',
        ]);

        // Cari pesanan dulu
        $pesanan = Pesanan::where('id', $id)
                        ->where('outlet_id', $request->outlet_id)
                        ->firstOrFail();

        // Cek status manual
        if (!in_array($pesanan->status, ['pending'])) {
            return response()->json([
                'message' => 'Pesanan hanya bisa dibatalkan saat masih pending.',
            ], 422);
        }

        try {
            $pesanan = app(\App\Services\Outlet\PesananService::class)
                        ->cancelOlehPelanggan($pesanan);

            return response()->json([
                'message' => 'Pesanan berhasil dibatalkan.',
                'data'    => [
                    'pesanan_id' => $pesanan->id,
                    'status'     => $pesanan->status,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}