<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Services\ActivityLogService;
use App\Traits\{HasPagination, OwnerAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AddonController extends Controller
{
    use HasPagination, OwnerAccess;

    // GET /owner/addon
    public function index(Request $request)
    {
        $usahaId = $this->getUsahaId();

        $addons = Addon::where('usaha_id', $usahaId)
                       ->orderBy('nama')
                       ->paginate($this->getPerPage($request));

        return response()->json($this->paginateResponse($addons, 'Daftar addon'));
    }

    // POST /owner/addon
    public function store(Request $request)
    {
        $usahaId = $this->getUsahaId();

        $request->validate([
            'nama'  => 'required|string|max:100',
            'harga' => 'required|numeric|min:0',
        ]);

        $addon = Addon::create([
            'id'       => Str::uuid(),
            'usaha_id' => $usahaId,
            'nama'     => $request->nama,
            'harga'    => $request->harga,
        ]);

        ActivityLogService::log('create_addon', "Addon '{$addon->nama}' dibuat", [], $usahaId);

        return response()->json(['message' => 'Addon berhasil dibuat', 'data' => $addon], 201);
    }

    // PUT /owner/addon/{id}
    public function update(Request $request, string $id)
    {
        $usahaId = $this->getUsahaId();
        $addon   = $this->validateMilikUsaha(Addon::class, $id);

        $request->validate([
            'nama'  => 'sometimes|string|max:100',
            'harga' => 'sometimes|numeric|min:0',
        ]);

        $addon->update($request->only(['nama', 'harga']));

        ActivityLogService::log('update_addon', "Addon '{$addon->nama}' diupdate", [], $usahaId);

        return response()->json(['message' => 'Addon diupdate', 'data' => $addon->fresh()]);
    }

    // DELETE /owner/addon/{id}
    public function destroy(string $id)
    {
        $usahaId = $this->getUsahaId();
        $addon   = $this->validateMilikUsaha(Addon::class, $id);
        $addon->delete();

        ActivityLogService::log('delete_addon', "Addon '{$addon->nama}' dihapus", [], $usahaId);

        return response()->json(['message' => 'Addon dihapus']);
    }
}