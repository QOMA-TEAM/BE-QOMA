<?php
namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\MenuOutletApproval;
use App\Services\Owner\MenuOutletApprovalService;
use App\Traits\{HasPagination, OwnerAccess};
use Illuminate\Http\Request;

class ApprovalHargaController extends Controller
{
    use HasPagination, OwnerAccess;

    public function __construct(private MenuOutletApprovalService $service) {}

    // GET /owner/approval-harga?status=pending&outlet_id=xxx
    public function index(Request $request)
    {
        $usahaId = $this->getUsahaId();
        $data = $this->service->listUntukOwner($usahaId, [
            'status'    => $request->status,
            'outlet_id' => $request->outlet_id,
            'per_page'  => $this->getPerPage($request),
        ]);

        return response()->json($this->paginateResponse($data, 'Daftar approval harga menu'));
    }

    // POST /owner/approval-harga/{id}/approve
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
                'message' => 'Perubahan harga disetujui. Harga baru sudah berlaku.',
                'data'    => $approval,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /owner/approval-harga/{id}/reject
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
                'message' => 'Perubahan harga ditolak. Harga lama tetap berlaku.',
                'data'    => $approval,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function findOwned(string $usahaId, string $id): MenuOutletApproval
    {
        $approval = MenuOutletApproval::where('id', $id)
                                      ->where('usaha_id', $usahaId)
                                      ->with(['menuOutlet.menu', 'outlet'])
                                      ->first();

        if (!$approval) abort(404, 'Approval tidak ditemukan atau bukan milik usaha Anda.');

        return $approval;
    }
}
