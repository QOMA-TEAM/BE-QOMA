<?php
namespace App\Services\Owner;
use App\Models\{Menu, MenuOutlet, Outlet};
use App\Services\ActivityLogService;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class MenuService
{
    public function __construct(private ImageService $imageService) {}
    public function getByUsaha(string $usahaId, array $filters = [], int $perPage = 15)
    {
        $query = Menu::where('usaha_id', $usahaId)
                     ->with(['kategori', 'bahanMasters', 'menuOutlets.outlet']);

        if (!empty($filters['kategori_id'])) $query->where('kategori_id', $filters['kategori_id']);
        if (isset($filters['is_active']))    $query->where('is_active', $filters['is_active']);

        return $query->orderBy('nama')->paginate($perPage);
    }

    public function create(array $data, string $usahaId, ?UploadedFile $gambar = null): Menu
    {
        return DB::transaction(function () use ($data, $usahaId, $gambar) {
            $menu = Menu::create([
                'id'            => Str::uuid(),
                'usaha_id'      => $usahaId,
                'kategori_id'   => $data['kategori_id'],
                'nama'          => $data['nama'],
                'harga_default' => $data['harga_default'],
                'gambar'        => $gambar ? $this->imageService->upload($gambar, "menu/{$usahaId}") : null,
                'keterangan'    => $data['keterangan'] ?? null,
                'is_active'     => $data['is_active'] ?? true,
            ]);

            if (!empty($data['bahan_master'])) {
                $this->syncBahanMaster($menu, $data['bahan_master']);
            }

            // Auto sync ke semua outlet usaha ini
            $this->syncKeSemuaOutlet($menu, $usahaId);

            ActivityLogService::log('create_menu', "Menu '{$menu->nama}' dibuat", [], $usahaId);

            return $menu->load(['kategori', 'bahanMasters', 'menuOutlets.outlet']);
        });
    }

    public function update(Menu $menu, array $data, ?UploadedFile $gambar = null): Menu
    {
        return DB::transaction(function () use ($menu, $data, $gambar) {
            $oldHarga = $menu->harga_default;

            if ($gambar) {
                $data['gambar'] = $this->imageService->replace($gambar, $menu->gambar, "menu/{$menu->usaha_id}");
            }

            $menu->update([
                'kategori_id'   => $data['kategori_id']   ?? $menu->kategori_id,
                'nama'          => $data['nama']           ?? $menu->nama,
                'harga_default' => $data['harga_default']  ?? $menu->harga_default,
                'gambar'        => $data['gambar']         ?? $menu->gambar,
                'keterangan'    => $data['keterangan']     ?? $menu->keterangan,
                'is_active'     => $data['is_active']      ?? $menu->is_active,
            ]);

            if (isset($data['bahan_master'])) {
                $this->syncBahanMaster($menu, $data['bahan_master']);
            }

            // Update harga di menu_outlet yang belum di-override
            $newHarga = $menu->fresh()->harga_default;
            if ((float)$oldHarga !== (float)$newHarga) {
                MenuOutlet::where('menu_id', $menu->id)
                          ->where('harga', $oldHarga)
                          ->update(['harga' => $newHarga]);
            }

            ActivityLogService::log('update_menu', "Menu '{$menu->nama}' diupdate", [], $menu->usaha_id);

            return $menu->fresh(['kategori', 'bahanMasters', 'menuOutlets.outlet']);
        });
    }

    public function delete(Menu $menu): void
    {
        $this->imageService->delete($menu->gambar);
        $menu->delete();
        ActivityLogService::log('delete_menu', "Menu '{$menu->nama}' dihapus", [], $menu->usaha_id);
    }

    private function syncBahanMaster(Menu $menu, array $items): void
    {
        $menu->bahanMasters()->detach();
        foreach ($items as $item) {
            $menu->bahanMasters()->attach($item['bahan_master_id'], [
                'id'           => Str::uuid(),
                'jumlah_pakai' => $item['jumlah_pakai'],
            ]);
        }
    }

    private function syncKeSemuaOutlet(Menu $menu, string $usahaId): void
    {
        Outlet::where('usaha_id', $usahaId)->each(function ($outlet) use ($menu) {
            MenuOutlet::firstOrCreate(
                ['menu_id' => $menu->id, 'outlet_id' => $outlet->id],
                ['id' => Str::uuid(), 'harga' => $menu->harga_default, 'is_available' => true]
            );
        });
    }
}