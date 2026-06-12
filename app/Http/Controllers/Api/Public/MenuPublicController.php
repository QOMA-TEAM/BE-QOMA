<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\{KategoriMenu, Menu, MenuOutlet, Meja, Outlet};
use Illuminate\Http\Request;

class MenuPublicController extends Controller
{
    /**
     * GET /public/validasi-meja?outlet_id=xxx&meja_id=yyy
     *
     * Dipanggil saat pelanggan scan QR.
     * Validasi outlet buka + meja valid.
     */
    public function validasiMeja(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|string',
            'meja_id'   => 'required|string',
        ]);

        // Validasi outlet ada
        $outlet = Outlet::find($request->outlet_id);
        if (!$outlet) {
            return response()->json([
                'message' => 'Outlet tidak ditemukan.',
                'code'    => 'OUTLET_NOT_FOUND',
            ], 404);
        }

        // Validasi outlet buka
        if (!$outlet->status_buka) {
            return response()->json([
                'message' => 'Outlet sedang tutup. Silakan datang kembali nanti.',
                'code'    => 'OUTLET_CLOSED',
            ], 403);
        }

        // Validasi meja milik outlet ini
        $meja = Meja::where('id', $request->meja_id)
                    ->where('outlet_id', $request->outlet_id)
                    ->first();

        if (!$meja) {
            return response()->json([
                'message' => 'Meja tidak valid atau bukan milik outlet ini.',
                'code'    => 'MEJA_NOT_VALID',
            ], 404);
        }

        return response()->json([
            'message' => 'Meja valid. Silakan pilih menu.',
            'data'    => [
                'outlet' => [
                    'id'          => $outlet->id,
                    'nama_outlet' => $outlet->nama_outlet,
                    'alamat'      => $outlet->alamat,
                ],
                'meja' => [
                    'id'         => $meja->id,
                    'nomor_meja' => $meja->nomor_meja,
                ],
            ],
        ]);
    }

    /**
     * GET /public/menu?outlet_id=xxx&kategori_id=yyy
     *
     * List semua menu yang tersedia di outlet ini.
     * Harga diambil dari menu_outlet (override),
     * kalau tidak ada pakai harga_default dari menu.
     */
    public function index(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|string|exists:outlet,id',
        ]);

        $outlet = Outlet::find($request->outlet_id);

        // Cek outlet buka
        if (!$outlet->status_buka) {
            return response()->json([
                'message' => 'Outlet sedang tutup.',
                'code'    => 'OUTLET_CLOSED',
            ], 403);
        }

        // Ambil usaha_id dari outlet untuk filter menu milik usaha ini
        $usahaId = $outlet->usaha_id;

        $query = Menu::where('usaha_id', $usahaId)
                     ->where('is_active', true)
                     ->with([
                         'kategori:id,nama',
                         'bahanMasters:id,nama,satuan', // info bahan baku
                     ]);

        // Filter by kategori
        if ($request->kategori_id) {
            $query->where('kategori_id', $request->kategori_id);
        }

        $menus = $query->orderBy('nama')->get();

        // Inject harga override per outlet
        $menus = $menus->map(function ($menu) use ($request) {
            $menuOutlet = MenuOutlet::where('menu_id', $menu->id)
                                   ->where('outlet_id', $request->outlet_id)
                                   ->first();

            // Pakai harga override kalau ada, kalau tidak pakai harga_default
            $harga        = $menuOutlet ? $menuOutlet->harga : $menu->harga_default;
            $isAvailable  = $menuOutlet ? $menuOutlet->is_available : true;

            return [
                'id'          => $menu->id,
                'nama'        => $menu->nama,
                'kategori'    => $menu->kategori->nama ?? '-',
                'kategori_id' => $menu->kategori_id,
                'harga'       => (float) $harga,
                'gambar'      => $menu->gambar
                                    ? asset('storage/' . $menu->gambar)
                                    : null,
                'keterangan'  => $menu->keterangan,
                'is_available'=> $isAvailable,
                'bahan_baku'  => $menu->bahanMasters->map(fn($b) => [
                    'nama'   => $b->nama,
                    'satuan' => $b->satuan,
                ]),
            ];
        })->filter(fn($m) => $m['is_available'])->values(); // filter yang available saja

        // Kelompokkan per kategori
        $menuPerKategori = $menus->groupBy('kategori')->map(fn($items, $kategori) => [
            'kategori' => $kategori,
            'items'    => $items->values(),
        ])->values();

        // Ambil list kategori milik usaha ini (untuk tab/filter di frontend)
        $kategoris = KategoriMenu::where('usaha_id', $usahaId)
                                 ->select('id', 'nama')
                                 ->orderBy('nama')
                                 ->get();

        return response()->json([
            'message' => 'Daftar menu',
            'data'    => [
                'outlet' => [
                    'id'            => $outlet->id,
                    'nama_outlet'   => $outlet->nama_outlet,

                    'gambar_icon'   => $outlet->gambar_icon
                        ? asset('storage/' . $outlet->gambar_icon)
                        : null,

                    'gambar_header' => $outlet->gambar_header
                        ? asset('storage/' . $outlet->gambar_header)
                        : null,
                ],

                'kategoris'         => $kategoris,
                'menu_per_kategori' => $menuPerKategori,
                'total_menu'        => $menus->count(),
            ],
        ]);
    }

    /**
     * GET /public/menu/{id}?outlet_id=xxx
     *
     * Detail 1 menu — untuk halaman detail sebelum masuk cart.
     */
    public function show(Request $request, string $id)
    {
        $request->validate([
            'outlet_id' => 'required|string|exists:outlet,id',
        ]);

        $outlet = Outlet::find($request->outlet_id);

        if (!$outlet->status_buka) {
            return response()->json(['message' => 'Outlet sedang tutup.'], 403);
        }

        $menu = Menu::where('id', $id)
                    ->where('usaha_id', $outlet->usaha_id)
                    ->where('is_active', true)
                    ->with(['kategori:id,nama', 'bahanMasters:id,nama,satuan'])
                    ->first();

        if (!$menu) {
            return response()->json(['message' => 'Menu tidak ditemukan.'], 404);
        }

        $menuOutlet  = MenuOutlet::where('menu_id', $id)
                                 ->where('outlet_id', $request->outlet_id)
                                 ->first();

        $harga = $menuOutlet ? $menuOutlet->harga : $menu->harga_default;

        // Ambil addon milik usaha ini
        $addons = \App\Models\Addon::where('usaha_id', $outlet->usaha_id)
                                   ->select('id', 'nama', 'harga')
                                   ->get();

        return response()->json([
            'message' => 'Detail menu',
            'data'    => [
                'id'          => $menu->id,
                'nama'        => $menu->nama,
                'kategori'    => $menu->kategori->nama ?? '-',
                'harga'       => (float) $harga,
                'harga_dasar' => (float) $menu->harga_default,
                'gambar'      => $menu->gambar ? asset('storage/' . $menu->gambar) : null,
                'keterangan'  => $menu->keterangan,
                'bahan_baku'  => $menu->bahanMasters->map(fn($b) => [
                    'nama'   => $b->nama,
                    'satuan' => $b->satuan,
                ]),
                'addons_tersedia' => $addons, // addon opsional
            ],
        ]);
    }
}