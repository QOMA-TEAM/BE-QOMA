<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;
use App\Http\Resources\Shared\ActivityLogResource;


class ActivityLogController extends Controller
{
    use HasPagination, OutletAccess;

    // GET /outlet/activity-log
    public function index(Request $request)
    {
        $outletId = $this->getOutletId();

        $logs = ActivityLog::where('outlet_id', $outletId)
                           ->with('user:id,username,nama_lengkap')
                           ->when($request->aktivitas, fn($q) => $q->where('aktivitas', $request->aktivitas))
                           ->when($request->dari,      fn($q) => $q->whereDate('created_at', '>=', $request->dari))
                           ->when($request->sampai,    fn($q) => $q->whereDate('created_at', '<=', $request->sampai))
                           ->latest()
                           ->paginate($this->getPerPage($request));

        return response()->json(
            $this->paginateResponse(
                $logs->through(fn($l) => new ActivityLogResource($l)),
                'Activity logs'
            )
        );
    }
}