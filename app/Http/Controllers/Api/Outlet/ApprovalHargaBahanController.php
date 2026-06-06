<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\{BahanOutlet, BahanOutletApproval};
use App\Services\Owner\BahanOutletApprovalService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;

class ApprovalHargaBahanController extends Controller
{
    use HasPagination, OutletAccess;

    public function __construct(private BahanOutletApprovalService $service) {}

    // GET /outlet/approval-harga-bahan?status=pending
    public function index(Request $request)
    {
        $outletId = $this->getOutletId();
        $data = $this->service->listUntukOutlet($outletId, [
            'status'   => $request->status,
            'per_page' => $this->getPerPage($request),
        ]);

        return response()->json($this->paginateResponse($data, 'Daftar approval harga bahan'));
    }

    // POST /outlet/approval-harga-bahan
    public function store(Request $request)
    {
        $outletId = $this->getOutletId();
        $outlet   = $this->getOutlet();

        $request->validate([
            'bahan_outlet_id' => 'required|string|exists:bahan_outlet,id',
            'harga_baru'      => 'required|numeric|min:1',
            'alasan'          => 'required|string|min:10|max:500',
        ]);

        // Pastikan bahan_outlet milik outlet ini
        $bahanOutlet = BahanOutlet::where('id', $request->bahan_outlet_id)
                                  ->where('outlet_id', $outletId)
                                  ->with('bahanMaster')
                                  ->firstOrFail();

        try {
            $approval = $this->service->ajukanPerubahanHarga(
                $bahanOutlet,
                (float) $request->harga_baru,
                $request->alasan,
                $outletId,
                $outlet->usaha_id,
            );

            return response()->json([
                'message' => 'Permohonan perubahan harga bahan baku berhasil dikirim ke owner. Harga lama masih berlaku.',
                'data'    => $approval,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}