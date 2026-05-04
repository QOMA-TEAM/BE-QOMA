<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Meja;
use App\Services\Outlet\MejaService;
use App\Traits\OutletAccess;
use Illuminate\Http\Request;

class MejaController extends Controller
{
    use OutletAccess;

    public function __construct(private MejaService $service) {}

    // GET /outlet/meja
    public function index()
    {
        $outletId = $this->getOutletId();
        return response()->json([
            'message' => 'Daftar meja',
            'data'    => $this->service->getByOutlet($outletId),
        ]);
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