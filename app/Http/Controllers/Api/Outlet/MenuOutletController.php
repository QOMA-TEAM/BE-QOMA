<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\{Menu, MenuOutlet};
use App\Services\ActivityLogService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;

class MenuOutletController extends Controller
{
    use HasPagination, OutletAccess;

    // GET /outlet/menu — list menu dengan harga outlet ini
    public function index(Request $request)
    {
        $outletId = $this->getOutletId();

        $menus = MenuOutlet::where('outlet_id', $outletId)
                           ->with(['menu.kategori:id,nama', 'menu.bahanMasters:id,nama,satuan'])
                           ->paginate($this->getPerPage($request));

        return response()->json($this->paginateResponse($menus, 'Daftar menu outlet'));
    }

    // PATCH /outlet/menu/{menu_id}/harga — edit harga menu di outlet ini
    public function updateHarga(Request $request, string $menuId)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'harga'        => 'required|numeric|min:1',
            'is_available' => 'nullable|boolean',
        ]);

        $menuOutlet = MenuOutlet::where('menu_id', $menuId)
                                ->where('outlet_id', $outletId)
                                ->firstOrFail();

        $menuOutlet->update([
            'harga'        => $request->harga,
            'is_available' => $request->is_available ?? $menuOutlet->is_available,
        ]);

        ActivityLogService::log(
            'update_harga_menu',
            "Harga menu '{$menuOutlet->menu->nama}' diubah menjadi Rp " . number_format($request->harga),
            ['menu_id' => $menuId, 'harga_baru' => $request->harga],
            null,
            $outletId,
        );

        return response()->json([
            'message' => 'Harga menu berhasil diupdate',
            'data'    => $menuOutlet->fresh('menu'),
        ]);
    }
}