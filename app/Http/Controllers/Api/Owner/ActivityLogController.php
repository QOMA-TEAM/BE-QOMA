<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Traits\HasPagination;
use Illuminate\Http\Request;
use App\Http\Resources\Shared\ActivityLogResource;

class ActivityLogController extends Controller
{
    use HasPagination;

    // GET /owner/activity-log
    public function index(Request $request)
    {
        $usahaId = auth()->user()->usaha_id;

        $query = ActivityLog::where('usaha_id', $usahaId)
                            ->with('user:id,username,nama_lengkap')
                            ->latest();

        if ($request->outlet_id) {
            if ($request->outlet_id === 'owner') {
                $query->whereNull('outlet_id');
            } else {
                $query->where('outlet_id', $request->outlet_id);
            }
        }

        if ($request->aktivitas) {
            $query->where('aktivitas', $request->aktivitas);
        }

        if ($request->dari) {
            $query->whereDate('created_at', '>=', $request->dari);
        }

        if ($request->sampai) {
            $query->whereDate('created_at', '<=', $request->sampai);
        }

         if ($request->search) {
            $search = strtolower($request->search);
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(aktivitas) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(deskripsi) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('user', function($u) use ($search) {
                      $u->whereRaw('LOWER(nama_lengkap) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(username) LIKE ?', ["%{$search}%"]);
                  });
            });
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
