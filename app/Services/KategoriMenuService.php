<?php

namespace App\Services;

use App\Models\KategoriMenu;
use Illuminate\Support\Str;

class KategoriMenuService
{
    public function getByUsaha(string $usahaId)
    {
        return KategoriMenu::where('usaha_id', $usahaId)
                           ->withCount('menus')
                           ->orderBy('nama')
                           ->get();
    }

    public function create(array $data, string $usahaId): KategoriMenu
    {
        return KategoriMenu::create([
            'id'       => Str::uuid(),
            'usaha_id' => $usahaId,
            'nama'     => $data['nama'],
        ]);
    }

    public function update(KategoriMenu $kategori, array $data): KategoriMenu
    {
        $kategori->update(['nama' => $data['nama']]);
        return $kategori->fresh();
    }

    public function delete(KategoriMenu $kategori): void
    {
        // Cek apakah ada menu yang pakai kategori ini
        if ($kategori->menus()->exists()) {
            throw new \Exception('Kategori tidak bisa dihapus karena masih dipakai oleh menu.');
        }
        $kategori->delete();
    }
}
