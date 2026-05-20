<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\{KategoriMenu, Menu, MenuOutlet};
use App\Services\ActivityLogService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;

class MenuOutletController extends Controller
{
    use HasPagination, OutletAccess;

    // GET /outlet/menu
    public function index(Request $request)
    {
        $outlet   = $this->getOutlet(); // ← ambil outlet object
        $outletId = $outlet->id;
        $usahaId  = $outlet->usaha_id; // ← usaha_id dari outlet

        $query = Menu::select('id', 'usaha_id', 'kategori_id', 'nama', 'harga_default', 'gambar', 'keterangan')
                     ->where('usaha_id', $usahaId)
                     ->where('is_active', true)
                     ->with([
                         'kategori:id,nama',
                         'bahanMasters:id,nama,satuan',
                         'menuOutlets' => fn($q) => $q->select('id', 'menu_id', 'outlet_id', 'harga', 'is_available')
                                                       ->where('outlet_id', $outletId),
                     ])
                     ->when($request->kategori_id, fn($q) => $q->where('kategori_id', $request->kategori_id))
                     ->when($request->search, fn($q) => $q->where('nama', 'like', "%{$request->search}%"))
                     ->orderBy('nama');

        // Paginate dulu, lalu transform
        $paginated = $query->paginate($this->getPerPage($request));

        $paginated->getCollection()->transform(function ($menu) use ($outletId) {
            $menuOutlet  = $menu->menuOutlets->first();
            $harga       = $menuOutlet ? $menuOutlet->harga : $menu->harga_default;
            $isAvailable = $menuOutlet ? $menuOutlet->is_available : true;

            return [
                'id'           => $menu->id,
                'nama'         => $menu->nama,
                'kategori'     => $menu->kategori->nama ?? '-',
                'kategori_id'  => $menu->kategori_id,
                'harga'        => (float) $harga,
                'gambar'       => $menu->gambar ? asset('storage/' . $menu->gambar) : null,
                'keterangan'   => $menu->keterangan,
                'is_available' => $isAvailable,
                'bahan_baku'   => $menu->bahanMasters->map(fn($b) => [
                    'nama'   => $b->nama,
                    'satuan' => $b->satuan,
                ]),
            ];
        });

        // Filter is_available setelah transform
        $filtered = $paginated->getCollection()->filter(fn($m) => $m['is_available'])->values();
        $paginated->setCollection($filtered);

        $kategoris = KategoriMenu::select('id', 'nama')
                                 ->where('usaha_id', $usahaId)
                                 ->orderBy('nama')
                                 ->get();

        return response()->json([
            'message'   => 'Daftar menu outlet',
            'outlet'    => ['id' => $outlet->id, 'nama_outlet' => $outlet->nama_outlet],
            'kategoris' => $kategoris,
            'data'      => $paginated->items(),
            'meta'      => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    // PATCH /outlet/menu/{menu_id}/harga
    // CATATAN: endpoint ini sekarang HANYA untuk toggle is_available
    // Perubahan harga harus lewat /outlet/approval-harga
    public function updateAvailability(Request $request, string $menuId)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'is_available' => 'required|boolean',
        ]);

        $menuOutlet = MenuOutlet::where('menu_id', $menuId)
                                ->where('outlet_id', $outletId)
                                ->firstOrFail();

        $menuOutlet->update([
            'is_available' => $request->is_available,
        ]);

        ActivityLogService::log(
            'update_ketersediaan_menu',
            "Menu '{$menuOutlet->menu->nama}' " . ($request->is_available ? 'diaktifkan' : 'dinonaktifkan'),
            ['menu_id' => $menuId, 'is_available' => $request->is_available],
            null,
            $outletId,
        );

        return response()->json([
            'message' => 'Ketersediaan menu berhasil diupdate',
            'data'    => $menuOutlet->fresh('menu'),
        ]);
    }
}