<?php
namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SuperAdmin\SubscriptionManagementService;
use App\Traits\HasPagination;
use Illuminate\Http\Request;
use App\Http\Resources\SuperAdmin\SubscriptionDetailResource;

class SubscriptionController extends Controller
{
    use HasPagination;

    public function __construct(private SubscriptionManagementService $service) {}

    // GET /super-admin/subscriptions?status=active&plan_id=xxx&dari=2026-01-01&search=warung
    // GET /super-admin/subscriptions?status=pending&tipe=new
    // GET /super-admin/subscriptions?status=pending&tipe=upgrade
    public function index(Request $request)
    {
        $subs = $this->service->list(
            $request->only(['status', 'plan_id', 'dari', 'sampai', 'search', 'tipe']), // ← tambah tipe
            $this->getPerPage($request)
        );

        $formatted = $subs->through(fn($sub) => [
            'id'                 => $sub->id,
            'usaha_id'           => $sub->usaha_id,
            'nama_perusahaan'    => $sub->usaha->nama_usaha ?? '-',
            'nama_owner'         => $sub->usaha->owner->nama_lengkap ?? '-',
            'jenis_subscription' => $sub->plan->nama_plan ?? '-',
            'tipe'               => $sub->tipe,        // ← BARU: new / upgrade
            'start_subscription' => $sub->start_date,
            'end_date'           => $sub->end_date,
            'status'             => $sub->status,
            'harga'              => (float) ($sub->plan->harga ?? 0),
        ]);

        return response()->json($this->paginateResponse($formatted, 'Daftar subscription'));
    }

    // GET /super-admin/subscriptions/{id}
    public function show(string $id)
    {
        return response()->json([
            'message' => 'Detail subscription',
            'data'    => new SubscriptionDetailResource($this->service->detail($id)),
        ]);
    }

    // POST /super-admin/subscriptions/{id}/konfirmasi-pembayaran
    public function konfirmasiPembayaran(string $id)
    {
        $sub = Subscription::findOrFail($id);

        try {
            $sub = $this->service->konfirmasiPembayaran($sub);
            return response()->json([
                'message' => 'Pembayaran dikonfirmasi. Usaha dan akun owner sekarang aktif.',
                'data'    => new SubscriptionDetailResource($sub),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /super-admin/subscriptions/{id}/cancel
    public function cancel(Request $request, string $id)
    {
        $request->validate(['alasan' => 'required|string|min:5']);

        $sub = Subscription::findOrFail($id);
        $sub = $this->service->cancel($sub, $request->alasan);

        return response()->json([
            'message' => 'Subscription berhasil dibatalkan',
            'data'    => new SubscriptionDetailResource($sub),
        ]);
    }
}