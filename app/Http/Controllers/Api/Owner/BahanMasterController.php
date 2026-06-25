<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\BahanMaster;
use App\Services\ActivityLogService;
use App\Traits\{HasPagination, OwnerAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\ImageService;

class BahanMasterController extends Controller
{
    
    use HasPagination, OwnerAccess;

    public function __construct(private ImageService $imageService) {}

    /**
     * Ambil daftar bahan baku milik owner
     */

    // GET /owner/bahan-baku?search=beras&page=1
    public function index(Request $request)
    {
        $usahaId = $this->getUsahaId();

        $query = BahanMaster::where('usaha_id', $usahaId);

        if ($request->search) {
            $query->where('nama', 'ilike', "%{$request->search}%");
        }

        $bahans = $query->orderBy('nama')
                        ->paginate($this->getPerPage($request));

        return response()->json($this->paginateResponse($bahans, 'Daftar bahan baku'));
    }

    // POST /owner/bahan-baku  (multipart/form-data)
    public function store(Request $request)
    {
        $usahaId = $this->getUsahaId();

        $request->validate([
            'nama'          => 'required|string|max:100',
            'satuan'        => 'required|in:kg,gram,liter,pcs,porsi,lusin,botol,sachet',
            'harga_default' => 'required|numeric|min:0',
            'gambar'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Cek duplikat case-insensitive per usaha
        $duplikat = BahanMaster::where('usaha_id', $usahaId)
                            ->whereRaw('LOWER(nama) = ?', [strtolower($request->input('nama'))])
                            ->exists();

        if ($duplikat) {
            return response()->json([
                'message' => "Bahan baku '{$request->input('nama')}' sudah ada di usaha ini.",
                'code'    => 'DUPLICATE',
            ], 422);
        }

        $gambarPath = $request->hasFile('gambar')
            ? $this->imageService->upload($request->file('gambar'), "bahan-master/{$usahaId}")
            : null;

        $satuanDasar     = \App\Helpers\SatuanHelper::getSatuanDasar($request->input('satuan'));
        $konversiKeDasar = match(strtolower($request->input('satuan'))) {
            'kg'    => 1000,
            'liter' => 1000,
            'lusin' => 12,
            default => 1,
        };

        $bahan = BahanMaster::create([
            'id'               => (string) Str::uuid(),
            'usaha_id'         => $usahaId,
            'nama'             => $request->input('nama'),
            'satuan'           => $request->input('satuan'),
            'satuan_dasar'     => $satuanDasar,      
            'konversi_ke_dasar'=> $konversiKeDasar,  
            'harga_default'    => $request->input('harga_default'),
            'gambar'           => $gambarPath,
        ]);

        ActivityLogService::log(
            'create_bahan_master',
            "Bahan baku '{$bahan->nama}' (Rp " . number_format((float) $bahan->harga_default) . "/{$bahan->satuan}) ditambahkan",
            ['bahan_id' => $bahan->id, 'nama' => $bahan->nama],
            $usahaId,
        );

        return response()->json([
            'message' => 'Bahan baku berhasil ditambahkan',
            'data'    => $bahan,
        ], 201);
    }

    // GET /owner/bahan-baku/{id}
    public function show(string $id)
    {
        $bahan = $this->validateMilikUsaha(BahanMaster::class, $id);

        return response()->json([
            'message' => 'Detail bahan baku',
            'data'    => $bahan,
        ]);
    }

    // PUT /owner/bahan-baku/{id}
    public function update(Request $request, string $id)
    {
        $usahaId = $this->getUsahaId();
        $bahan   = $this->validateMilikUsaha(BahanMaster::class, $id);

        $request->validate([
            'nama'          => 'sometimes|string|max:100',
            'satuan'        => 'sometimes|in:kg,gram,liter,pcs,porsi,lusin,botol,sachet',
            'harga_default' => 'sometimes|numeric|min:0',
            'gambar'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Cek duplikat nama jika nama diubah
        if ($request->filled('nama') && strtolower($request->input('nama')) !== strtolower($bahan->nama)) {
            $duplikat = BahanMaster::where('usaha_id', $usahaId)
                                ->where('id', '!=', $id)
                                ->whereRaw('LOWER(nama) = ?', [strtolower($request->input('nama'))])
                                ->exists();

            if ($duplikat) {
                return response()->json([
                    'message' => "Bahan baku '{$request->input('nama')}' sudah ada.",
                    'code'    => 'DUPLICATE',
                ], 422);
            }
        }

        $gambarPath = $request->hasFile('gambar')
            ? $this->imageService->replace($request->file('gambar'), $bahan->gambar, "bahan-master/{$usahaId}")
            : $bahan->gambar;
        
        $satuanDasar = $bahan->satuan_dasar;
        $konversiKeDasar = $bahan->konversi_ke_dasar;

        if ($request->filled('satuan')) {
            $satuanDasar = \App\Helpers\SatuanHelper::getSatuanDasar($request->input('satuan'));

            $konversiKeDasar = match (strtolower($request->input('satuan'))) {
                'kg'    => 1000,
                'liter' => 1000,
                'lusin' => 12,
                default => 1,
            };
        }

        $bahan->update([
            'nama'              => $request->input('nama', $bahan->nama),
            'satuan'            => $request->input('satuan', $bahan->satuan),
            'satuan_dasar'      => $satuanDasar,
            'konversi_ke_dasar' => $konversiKeDasar,
            'harga_default'     => $request->input('harga_default', $bahan->harga_default),
            'gambar'            => $gambarPath,
        ]);
        
        ActivityLogService::log(
            'update_bahan_master',
            "Bahan baku '{$bahan->nama}' diupdate",
            ['bahan_id' => $bahan->id],
            $usahaId,
        );

        return response()->json([
            'message' => 'Bahan baku berhasil diupdate',
            'data'    => $bahan->fresh(),
        ]);
    }

    // DELETE /owner/bahan-baku/{id}
    public function destroy(string $id)
    {
        $usahaId = $this->getUsahaId();
        $bahan   = $this->validateMilikUsaha(BahanMaster::class, $id);

        // Cek apakah bahan dipakai di menu
        $dipakaiDiMenu = DB::table('menu_bahan')
                           ->where('bahan_master_id', $id)
                           ->exists();

        if ($dipakaiDiMenu) {
            return response()->json([
                'message' => "Bahan '{$bahan->nama}' tidak bisa dihapus karena masih digunakan di menu.",
                'code'    => 'IN_USE',
            ], 422);
        }

        $bahan->delete();

        ActivityLogService::log(
            'delete_bahan_master',
            "Bahan baku '{$bahan->nama}' dihapus",
            ['bahan_id' => $id],
            $usahaId,
        );

        return response()->json(['message' => 'Bahan baku berhasil dihapus']);
    }
    
}
