<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SuperAdmin\UsahaManagementService;
use App\Traits\HasPagination;
use Illuminate\Http\Request;

class OwnerManagementController extends Controller
{
    use HasPagination;

    public function __construct(private UsahaManagementService $service) {}

    // GET /super-admin/owner?search=xxx&is_active=1&page=1
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'is_active']);

        // Konversi string "1"/"0" ke boolean
        if (isset($filters['is_active'])) {
            $filters['is_active'] = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $owners = $this->service->listOwner($filters, $this->getPerPage($request));

        return response()->json(
            $this->paginateResponse($owners, 'Daftar semua owner')
        );
    }

    // GET /super-admin/usaha/{usaha_id}/owner
    public function byUsaha(string $usahaId)
    {
        $owner = $this->service->ownerByUsaha($usahaId);

        if (!$owner) {
            return response()->json(['message' => 'Owner tidak ditemukan untuk usaha ini'], 404);
        }

        return response()->json([
            'message' => 'Data owner',
            'data'    => $owner,
        ]);
    }

    // POST /super-admin/owner/{id}/reset-password
    public function resetPassword(string $id)
    {
        $owner = User::whereHas('role', fn($q) => $q->where('name', 'owner'))
                     ->findOrFail($id);

        $newPassword = $this->service->resetPasswordOwner($owner);

        return response()->json([
            'message'      => 'Password owner berhasil direset',
            'new_password' => $newPassword,
            'note'         => 'Berikan password ini ke owner. Tidak bisa ditampilkan lagi.',
        ]);
    }

    // PATCH /super-admin/owner/{id}/toggle-status
    public function toggleStatus(string $id)
    {
        $owner = User::whereHas('role', fn($q) => $q->where('name', 'owner'))
                     ->findOrFail($id);

        $owner = $this->service->toggleOwnerStatus($owner);
        $status = $owner->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "Akun owner berhasil {$status}",
            'data'    => $owner,
        ]);
    }
}
