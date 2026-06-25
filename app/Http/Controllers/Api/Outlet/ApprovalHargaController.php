<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\{MenuOutlet, MenuOutletApproval};
use App\Services\Owner\MenuOutletApprovalService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;

class ApprovalHargaController extends Controller
{
    use HasPagination, OutletAccess;

    public function __construct(private MenuOutletApprovalService $service) {}

    // GET /outlet/approval-harga?status=pending
    public function index(Request $request)
    {
        $outletId = $this->getOutletId();
        $data = $this->service->listUntukOutlet($outletId, [
            'status'   => $request->status,
            'per_page' => $this->getPerPage($request),
        ]);

        return response()->json($this->paginateResponse($data, 'Daftar approval harga'));
    }

    // POST /outlet/approval-harga — ajukan perubahan harga
    public function store(Request $request)
    {
        $outletId = $this->getOutletId();
        $outlet   = $this->getOutlet();

        $request->validate([
            'menu_id'    => 'required|string|exists:menu,id',
            'harga_baru' => 'required|numeric|min:1',
            'alasan'     => 'required|string|min:10|max:500',
        ]);

        // Cari menu_outlet milik outlet ini
        $menuOutlet = MenuOutlet::where('menu_id', $request->menu_id)
                                ->where('outlet_id', $outletId)
                                ->firstOrFail();

        try {
            $approval = $this->service->ajukanPerubahanHarga(
                $menuOutlet,
                (float) $request->harga_baru,
                $request->alasan,
                $outletId,
                $outlet->usaha_id,
            );

            return response()->json([
                'message' => 'Permohonan perubahan harga berhasil dikirim ke owner. Harga lama masih berlaku sampai disetujui.',
                'data'    => $approval,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
