<?php
namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Traits\HasPagination;
use Illuminate\Http\Request;
use App\Http\Resources\Shared\ActivityLogResource;

class ActivityLogController extends Controller
{
    use HasPagination;

    /**
     * @authenticated
     */

    // GET /super-admin/activity-logs?usaha_id=x&aktivitas=approve_usaha&dari=2026-01-01
    public function index(Request $request)
    {
        $query = ActivityLog::with('user:id,username,nama_lengkap')
                            ->latest();

        if ($request->usaha_id)  $query->where('usaha_id', $request->usaha_id);
        if ($request->outlet_id) $query->where('outlet_id', $request->outlet_id);
        if ($request->aktivitas) $query->where('aktivitas', $request->aktivitas);
        if ($request->user_id)   $query->where('user_id', $request->user_id);
        if ($request->dari)      $query->whereDate('created_at', '>=', $request->dari);
        if ($request->sampai)    $query->whereDate('created_at', '<=', $request->sampai);

        // Filter khusus super admin: hanya log yang relevan
        if ($request->kategori === 'subscription') {
            $query->whereIn('aktivitas', [
                'owner_register', 'konfirmasi_pembayaran',
                'cancel_subscription', 'create_plan', 'update_plan',
            ]);
        }

        if ($request->kategori === 'usaha') {
            $query->whereIn('aktivitas', [
                'approve_usaha', 'reject_usaha', 'suspend_usaha',
                'unsuspend_usaha', 'create_usaha',
            ]);
        }

        $logs = $query->paginate($this->getPerPage($request));

        return response()->json(
            $this->paginateResponse(
                $logs->through(fn($l) => new ActivityLogResource($l)),
                'Activity logs'
            )
        );
    }
}