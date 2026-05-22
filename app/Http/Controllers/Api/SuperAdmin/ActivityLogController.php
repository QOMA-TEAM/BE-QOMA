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

    // Aktivitas yang relevan untuk super admin
    private const SUPER_ADMIN_AKTIVITAS = [
        // Usaha management
        'approve_usaha',
        'reject_usaha',
        'suspend_usaha',
        'unsuspend_usaha',
        // Subscription & plan
        'owner_register',
        'konfirmasi_pembayaran_subscription',
        'cancel_subscription',
        'create_plan',
        'update_plan',
        'delete_plan',
        'auto_downgrade_free',
        'request_upgrade_plan',
        // Owner management
        'reset_password_owner',
        'toggle_owner_status',
    ];

    public function index(Request $request)
    {
        $query = ActivityLog::with('user:id,username,nama_lengkap')
                            ->latest();

        // ← DEFAULT: hanya tampilkan log yang relevan untuk super admin
        // Kecuali kalau request ?semua=1
        if (!$request->boolean('semua')) {
            $query->whereIn('aktivitas', self::SUPER_ADMIN_AKTIVITAS);
        }

        // Filter tambahan
        if ($request->usaha_id)  $query->where('usaha_id', $request->usaha_id);
        if ($request->aktivitas) $query->where('aktivitas', $request->aktivitas);
        if ($request->user_id)   $query->where('user_id', $request->user_id);
        if ($request->dari)      $query->whereDate('created_at', '>=', $request->dari);
        if ($request->sampai)    $query->whereDate('created_at', '<=', $request->sampai);

        // Filter by kategori
        if ($request->kategori === 'subscription') {
            $query->whereIn('aktivitas', [
                'owner_register', 'konfirmasi_pembayaran_subscription',
                'cancel_subscription', 'create_plan', 'update_plan',
                'delete_plan', 'auto_downgrade_free', 'request_upgrade_plan',
            ]);
        }

        if ($request->kategori === 'usaha') {
            $query->whereIn('aktivitas', [
                'approve_usaha', 'reject_usaha',
                'suspend_usaha', 'unsuspend_usaha',
            ]);
        }

        if ($request->kategori === 'owner') {
            $query->whereIn('aktivitas', [
                'reset_password_owner', 'toggle_owner_status',
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