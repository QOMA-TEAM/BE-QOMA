<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\KategoriMenu;
use App\Services\ActivityLogService;
use App\Traits\{HasPagination, OwnerAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KategoriMenuController extends Controller
{
    use HasPagination, OwnerAccess;

    public function index(Request $request)
    {
        $usahaId = $this->getUsahaId();

        $query = KategoriMenu::where('usaha_id', $usahaId)->withCount('menus');

        if ($request->search) {
            $query->where('nama', 'ilike', "%{$request->search}%");
        }

        $kategori = $query->orderBy('nama')->paginate($this->getPerPage($request));

        return response()->json($this->paginateResponse($kategori, 'Daftar kategori'));
    }

    public function store(Request $request)
    {
        $usahaId = $this->getUsahaId();

        $request->validate(['nama' => 'required|string|max:100']);

        // Cek duplikat
        if (KategoriMenu::where('usaha_id', $usahaId)->where('nama', $request->nama)->exists()) {
            return response()->json(['message' => "Kategori '{$request->nama}' sudah ada."], 422);
        }

        $kategori = KategoriMenu::create([
            'id'       => Str::uuid(),
            'usaha_id' => $usahaId,
            'nama'     => $request->nama,
        ]);

        ActivityLogService::log('create_kategori', "Kategori '{$request->nama}' dibuat", [], $usahaId);

        return response()->json(['message' => 'Kategori dibuat', 'data' => $kategori], 201);
    }

    public function show(string $id)
    {
        return response()->json([
            'message' => 'Detail kategori',
            'data'    => $this->validateMilikUsaha(KategoriMenu::class, $id),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $usahaId  = $this->getUsahaId();
        $kategori = $this->validateMilikUsaha(KategoriMenu::class, $id);

        $request->validate(['nama' => 'required|string|max:100']);
        $kategori->update(['nama' => $request->nama]);

        ActivityLogService::log('update_kategori', "Kategori '{$request->nama}' diupdate", [], $usahaId);

        return response()->json(['message' => 'Kategori diupdate', 'data' => $kategori->fresh()]);
    }

    public function destroy(string $id)
    {
        $usahaId  = $this->getUsahaId();
        $kategori = $this->validateMilikUsaha(KategoriMenu::class, $id);

        if ($kategori->menus()->exists()) {
            return response()->json([
                'message' => "Kategori '{$kategori->nama}' tidak bisa dihapus karena masih dipakai menu.",
                'code'    => 'IN_USE',
            ], 422);
        }

        $kategori->delete();
        ActivityLogService::log('delete_kategori', "Kategori '{$kategori->nama}' dihapus", [], $usahaId);

        return response()->json(['message' => 'Kategori dihapus']);
    }
}