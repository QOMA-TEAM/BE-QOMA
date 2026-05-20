<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Meja;
use App\Services\Outlet\MejaService;
use App\Traits\{HasPagination, OutletAccess}; // ← tambah HasPagination
use Illuminate\Http\Request;

class MejaController extends Controller
{
    use HasPagination, OutletAccess; // ← tambah HasPagination

    public function __construct(private MejaService $service) {}

    // GET /outlet/meja
    public function index(Request $request) // ← tambah Request
    {
        $outletId = $this->getOutletId();

        $mejas = Meja::select('id', 'outlet_id', 'nomor_meja', 'qr_code')
                     ->where('outlet_id', $outletId)
                     ->orderBy('nomor_meja')
                     ->paginate($this->getPerPage($request));

        return response()->json(
            $this->paginateResponse($mejas, 'Daftar meja')
        );
    }

    // POST /outlet/meja
    public function store(Request $request)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'nomor_meja' => 'required|string|max:10',
        ]);

        try {
            $meja = $this->service->create($outletId, $request->nomor_meja);
            return response()->json([
                'message' => 'Meja berhasil ditambahkan',
                'data'    => $meja,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // DELETE /outlet/meja/{id}
    public function destroy(string $id)
    {
        $outletId = $this->getOutletId();
        $meja     = Meja::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();

        try {
            $this->service->delete($meja);
            return response()->json(['message' => 'Meja berhasil dihapus']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}