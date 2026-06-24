<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\{BahanMaster, KategoriMenu, Menu, MenuOutlet, Outlet};
use App\Services\ActivityLogService;
use App\Traits\{HasPagination, OwnerAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB};
use Illuminate\Support\Str;
use App\Services\ImageService;

class MenuController extends Controller
{
    use HasPagination, OwnerAccess;

    public function __construct(private ImageService $imageService) {}

    // GET /owner/menu?kategori_id=xxx&is_active=1&page=1
    public function index(Request $request)
    {
        $usahaId = $this->getUsahaId();

        $query = Menu::where('usaha_id', $usahaId)
                    ->with(['kategori:id,nama', 'bahanMasters:id,nama,satuan', 'addons:id,nama,harga']) 
                    ->withCount('menuOutlets');

        if ($request->kategori_id) {
            $query->where('kategori_id', $request->kategori_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->search) {
            $query->where('nama', 'ilike', "%{$request->search}%");
        }

        $menus = $query->orderBy('nama')->paginate($this->getPerPage($request));

        return response()->json($this->paginateResponse($menus, 'Daftar menu'));
    }

    // POST /owner/menu  (multipart/form-data)
    public function store(Request $request)
    {
        $usahaId = $this->getUsahaId();

        $request->validate([
            'nama'                          => 'required|string|max:150',
            'kategori_id'                   => 'required|exists:kategori_menu,id',
            'harga_default'                 => 'required|numeric|min:1',
            'keterangan'                    => 'nullable|string|max:500',
            'is_active'                     => 'nullable|boolean',
            'gambar'                        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'bahan_baku'                    => 'nullable|array',
            'bahan_baku.*.bahan_master_id'  => 'required|exists:bahan_master,id',
            'bahan_baku.*.jumlah_pakai'     => 'required|numeric|min:0.01',
            'addon_ids'                     => 'nullable|array',       // ← TAMBAHKAN
            'addon_ids.*'                   => 'string|exists:addon,id', // ← TAMBAHKAN
        ]);

        // Validasi kategori milik usaha ini
        $kategoriValid = KategoriMenu::where('id', $request->kategori_id)
                                    ->where('usaha_id', $usahaId)
                                    ->exists();

        if (!$kategoriValid) {
            return response()->json([
                'message' => 'Kategori tidak ditemukan atau bukan milik usaha Anda.',
            ], 422);
        }

        // Validasi bahan baku milik usaha ini
        if ($request->bahan_baku) {
            $bahanIds        = collect($request->bahan_baku)->pluck('bahan_master_id');
            $validBahanCount = BahanMaster::whereIn('id', $bahanIds)
                                        ->where('usaha_id', $usahaId)
                                        ->count();

            if ($validBahanCount !== $bahanIds->count()) {
                return response()->json([
                    'message' => 'Satu atau lebih bahan baku tidak valid atau bukan milik usaha Anda.',
                ], 422);
            }
        }

        // ← BARU: Validasi addon milik usaha ini
        if ($request->addon_ids) {
            $validAddonCount = \App\Models\Addon::whereIn('id', $request->addon_ids)
                                                ->where('usaha_id', $usahaId)
                                                ->count();

            if ($validAddonCount !== count($request->addon_ids)) {
                return response()->json([
                    'message' => 'Satu atau lebih addon tidak valid atau bukan milik usaha Anda.',
                ], 422);
            }
        }

        // Cek duplikat
        $duplikat = Menu::where('usaha_id', $usahaId)
                        ->whereRaw('LOWER(nama) = ?', [strtolower($request->nama)])
                        ->exists();

        if ($duplikat) {
            return response()->json([
                'message' => "Menu '{$request->nama}' sudah ada di usaha ini.",
                'code'    => 'DUPLICATE',
            ], 422);
        }

        return DB::transaction(function () use ($request, $usahaId) {

            $gambarPath = $request->hasFile('gambar')
                ? $this->imageService->upload($request->file('gambar'), "menu/{$usahaId}")
                : null;

            $menu = Menu::create([
                'id'            => Str::uuid(),
                'usaha_id'      => $usahaId,
                'kategori_id'   => $request->kategori_id,
                'nama'          => $request->nama,
                'harga_default' => $request->harga_default,
                'gambar'        => $gambarPath,
                'keterangan'    => $request->keterangan,
                'is_active'     => $request->is_active ?? true,
            ]);

            // Sync bahan baku
            if ($request->bahan_baku) {
                foreach ($request->bahan_baku as $item) {
                    DB::table('menu_bahan')->insert([
                        'id'              => (string) Str::uuid(),
                        'menu_id'         => $menu->id,
                        'bahan_master_id' => $item['bahan_master_id'],
                        'jumlah_pakai'    => $item['jumlah_pakai'],
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            // ← BARU: Sync addon ke menu
            if ($request->addon_ids) {
                foreach ($request->addon_ids as $addonId) {
                    DB::table('menu_addon')->insert([
                        'id'         => (string) Str::uuid(),
                        'menu_id'    => $menu->id,
                        'addon_id'   => $addonId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Auto sync ke semua outlet usaha ini
            Outlet::where('usaha_id', $usahaId)->each(function ($outlet) use ($menu) {
                MenuOutlet::firstOrCreate(
                    ['menu_id' => $menu->id, 'outlet_id' => $outlet->id],
                    ['id' => (string) Str::uuid(), 'harga' => $menu->harga_default, 'is_available' => true]
                );
            });

            ActivityLogService::log(
                'create_menu',
                "Menu '{$menu->nama}' (Rp " . number_format($menu->harga_default) . ") dibuat" .
                ($request->addon_ids ? " dengan " . count($request->addon_ids) . " addon" : ''),
                ['menu_id' => $menu->id, 'nama' => $menu->nama],
                $usahaId,
            );

            return response()->json([
                'message' => 'Menu berhasil dibuat dan sudah tersebar ke semua outlet',
                'data'    => $menu->load(['kategori', 'bahanMasters', 'addons', 'menuOutlets.outlet']),
            ], 201);
        });
    }

    // GET /owner/menu/{id}
    public function show(string $id)
    {
        $menu = $this->validateMilikUsaha(Menu::class, $id);

        return response()->json([
            'message' => 'Detail menu',
            'data'    => $menu->load(['kategori', 'bahanMasters', 'addons', 'menuOutlets.outlet']), 
        ]);
    }

    // POST /owner/menu/{id}  (pakai POST + _method=PUT untuk multipart)
    public function update(Request $request, string $id)
    {
        $usahaId = $this->getUsahaId();
        $menu    = $this->validateMilikUsaha(Menu::class, $id);

        $request->validate([
            'nama'                         => 'sometimes|string|max:150',
            'kategori_id'                  => 'sometimes|exists:kategori_menu,id',
            'harga_default'                => 'sometimes|numeric|min:1',
            'keterangan'                   => 'nullable|string|max:500',
            'is_active'                    => 'nullable|boolean',
            'gambar'                       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'bahan_baku'                   => 'nullable|array',
            'bahan_baku.*.bahan_master_id' => 'required|exists:bahan_master,id',
            'bahan_baku.*.jumlah_pakai'    => 'required|numeric|min:0.01',
            'bahan_baku.*.satuan_pakai'    => 'nullable|in:kg,gram,liter,ml,pcs,porsi,lusin,botol,sachet',
            'addon_ids'                    => 'nullable|array',
            'addon_ids.*'                  => 'string|exists:addon,id',
        ]);
        
        // Sebelum update, cek duplikat jika nama diubah
        if ($request->filled('nama') && strtolower($request->nama) !== strtolower($menu->nama)) {
            $duplikat = Menu::where('usaha_id', $usahaId)
                            ->where('id', '!=', $id)
                            ->whereRaw('LOWER(nama) = ?', [strtolower($request->nama)])
                            ->exists();

            if ($duplikat) {
                return response()->json([
                    'message' => "Menu '{$request->nama}' sudah ada.",
                    'code'    => 'DUPLICATE',
                ], 422);
            }
        }

        return DB::transaction(function () use ($request, $menu, $usahaId) {

            $oldHarga = $menu->harga_default;

            // Ganti gambar jika ada
            $data['gambar'] = $request->hasFile('gambar')
                ? $this->imageService->replace($request->file('gambar'), $menu->gambar, "menu/{$usahaId}")
                : $menu->gambar;

            $menu->update([
                'kategori_id'   => $request->kategori_id   ?? $menu->kategori_id,
                'nama'          => $request->nama           ?? $menu->nama,
                'harga_default' => $request->harga_default  ?? $menu->harga_default,
                'gambar'        => $data['gambar']          ?? $menu->gambar,
                'keterangan'    => $request->keterangan     ?? $menu->keterangan,
                'is_active'     => $request->is_active      ?? $menu->is_active,
            ]);

            // Sync bahan baku jika dikirim
            if ($request->has('bahan_baku')) {
                DB::table('menu_bahan')->where('menu_id', $menu->id)->delete();

                foreach ($request->bahan_baku as $item) {
                    DB::table('menu_bahan')->insert([
                        'id'              => (string) Str::uuid(),
                        'menu_id'         => $menu->id,
                        'bahan_master_id' => $item['bahan_master_id'],
                        'jumlah_pakai'    => $item['jumlah_pakai'],
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            // Jika harga berubah, update menu_outlet yang belum di-override
            $newHarga = $menu->fresh()->harga_default;
            if ((float) $oldHarga !== (float) $newHarga) {
                MenuOutlet::where('menu_id', $menu->id)
                          ->where('harga', $oldHarga)
                          ->update(['harga' => $newHarga]);
            }

            ActivityLogService::log(
                'update_menu',
                "Menu '{$menu->nama}' diupdate",
                ['menu_id' => $menu->id],
                $usahaId,
            );

            return response()->json([
                'message' => 'Menu berhasil diupdate',
                'data'    => $menu->fresh(['kategori', 'bahanMasters', 'menuOutlets.outlet']),
            ]);
        });
    }

    // DELETE /owner/menu/{id}
    public function destroy(string $id)
    {
        $usahaId = $this->getUsahaId();
        $menu    = $this->validateMilikUsaha(Menu::class, $id);

        $this->imageService->delete($menu->gambar);
        $menu->delete();

        ActivityLogService::log(
            'delete_menu',
            "Menu '{$menu->nama}' dihapus",
            ['menu_id' => $menu->id],
            $usahaId,
        );

        return response()->json(['message' => 'Menu berhasil dihapus']);
    }
}