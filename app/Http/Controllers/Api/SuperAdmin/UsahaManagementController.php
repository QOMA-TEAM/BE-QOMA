<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Usaha;
use App\Services\SuperAdmin\UsahaManagementService;
use App\Traits\HasPagination;
use Illuminate\Http\Request;
use App\Http\Resources\SuperAdmin\UsahaManagementResource;

class UsahaManagementController extends Controller
{
    use HasPagination;

    public function __construct(private UsahaManagementService $service) {}

    // GET /super-admin/usaha?status=pending&search=warung&page=1&per_page=10
    public function index(Request $request)
    {
        $usahas = $this->service->listUsaha(
            $request->only(['status', 'search', 'exclude_status']),
            $this->getPerPage($request)
        );

        return response()->json([
            'message' => 'Daftar semua usaha',
            'data'    => UsahaManagementResource::collection($usahas)->resolve(),
            'meta'    => [
                'current_page' => $usahas->currentPage(),
                'per_page'     => $usahas->perPage(),
                'total'        => $usahas->total(),
                'last_page'    => $usahas->lastPage(),
                'from'         => $usahas->firstItem(),
                'to'           => $usahas->lastItem(),
            ],
        ]);
    }

    // GET /super-admin/usaha/{id}
    public function show(string $id)
    {
        $usaha = $this->service->detailUsaha($id);

        return response()->json([
            'message' => 'Detail usaha',
            'data'    =>new UsahaManagementResource($usaha),
        ]);
    }

    // POST /super-admin/usaha/{id}/approve
    public function approve(string $id)
    {
        $usaha = Usaha::findOrFail($id);

        try {
            $usaha = $this->service->approve($usaha);
            return response()->json([
                'message' => 'Usaha berhasil di-approve',
                'data'    => new UsahaManagementResource($usaha),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /super-admin/usaha/{id}/reject
    public function reject(Request $request, string $id)
    {
        $request->validate([
            'alasan' => 'required|string|min:10|max:500',
        ]);

        $usaha = Usaha::findOrFail($id);

        try {
            $usaha = $this->service->reject($usaha, $request->alasan);
            return response()->json([
                'message' => 'Usaha berhasil di-reject',
                'data'    => new UsahaManagementResource($usaha),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /super-admin/usaha/{id}/suspend
    public function suspend(Request $request, string $id)
    {
        $request->validate([
            'catatan' => 'nullable|string|max:500',
        ]);

        $usaha = Usaha::findOrFail($id);

        try {
            $usaha = $this->service->suspend($usaha, $request->catatan);
            return response()->json([
                'message' => 'Usaha berhasil disuspend',
                'data'    => new UsahaManagementResource($usaha),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /super-admin/usaha/{id}/unsuspend
    public function unsuspend(string $id)
    {
        $usaha = Usaha::findOrFail($id);

        try {
            $usaha = $this->service->unsuspend($usaha);
            return response()->json([
                'message' => 'Usaha berhasil diaktifkan kembali',
                'data'    => new UsahaManagementResource($usaha),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /super-admin/usaha/pending — shortcut untuk approval queue
    public function pending(Request $request)
    {
        $request->merge(['status' => 'pending']);

        $usahas = $this->service->listUsaha(
            ['status' => 'pending', 'search' => $request->search],
            $this->getPerPage($request)
        );

        return response()->json(
            $this->paginateResponse($usahas, 'Daftar usaha menunggu approval')
        );
    }
}