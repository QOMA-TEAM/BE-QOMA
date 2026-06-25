<?php
namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\BahanOutletApproval;
use App\Services\Owner\BahanOutletApprovalService;
use App\Traits\{HasPagination, OwnerAccess};
use Illuminate\Http\Request;

class ApprovalHargaBahanController extends Controller
{
    use HasPagination, OwnerAccess;

    public function __construct(private BahanOutletApprovalService $service) {}

    // GET /owner/approval-harga-bahan?status=pending&outlet_id=xxx
    public function index(Request $request)
    {
        $usahaId = $this->getUsahaId();
        $data = $this->service->listUntukOwner($usahaId, [
            'status'    => $request->status,
            'outlet_id' => $request->outlet_id,
            'per_page'  => $this->getPerPage($request),
        ]);

        return response()->json($this->paginateResponse($data, 'Daftar approval harga bahan baku'));
    }

    // POST /owner/approval-harga-bahan/{id}/approve
    public function approve(Request $request, string $id)
    {
        $usahaId  = $this->getUsahaId();
        $approval = $this->findOwned($usahaId, $id);

        $request->validate([
            'catatan' => 'nullable|string|max:500',
        ]);

        try {
            $approval = $this->service->approve($approval, $request->catatan);
            return response()->json([
                'message' => 'Perubahan harga bahan baku disetujui. Harga baru sudah berlaku.',
                'data'    => $approval,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /owner/approval-harga-bahan/{id}/reject
    public function reject(Request $request, string $id)
    {
        $usahaId  = $this->getUsahaId();
        $approval = $this->findOwned($usahaId, $id);

        $request->validate([
            'catatan' => 'required|string|min:5|max:500',
        ]);

        try {
            $approval = $this->service->reject($approval, $request->catatan);
            return response()->json([
                'message' => 'Perubahan harga bahan baku ditolak. Harga lama tetap berlaku.',
                'data'    => $approval,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function findOwned(string $usahaId, string $id): BahanOutletApproval
    {
        $approval = BahanOutletApproval::where('id', $id)
                                       ->where('usaha_id', $usahaId)
                                       ->with(['bahanOutlet.bahanMaster', 'outlet'])
                                       ->first();

        if (!$approval) abort(404, 'Approval tidak ditemukan.');

        return $approval;
    }
}
