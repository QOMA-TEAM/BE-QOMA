<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\BahanOutlet;
use App\Services\Outlet\BahanOutletService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BahanOutletController extends Controller
{
    use HasPagination, OutletAccess;

    public function __construct(private BahanOutletService $service) {}

    // GET /outlet/bahan-baku?search=x&sort_by=stok&sort_dir=asc&menipis=1
    public function index(Request $request)
    {
        $outletId = $this->getOutletId();
        $bahans   = $this->service->getList($outletId, array_merge(
            $request->only(['search', 'menipis', 'mendekati_expired', 'sort_by', 'sort_dir']),
            ['per_page' => $this->getPerPage($request)]
        ));

        return response()->json($this->paginateResponse($bahans, 'Daftar bahan baku outlet'));
    }

    // POST /outlet/bahan-baku — tambah stok bahan
    public function store(Request $request)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'bahan_master_id'    => 'required|exists:bahan_master,id',
            'jumlah'             => 'required|numeric|min:0.01',
            'tanggal_masuk'      => 'nullable|date',
            'tanggal_kadaluarsa' => 'nullable|date|after:today',
            'stok_minimum'       => 'nullable|numeric|min:0',
        ]);

        try {
            $bahan = $this->service->tambah($outletId, $request->all());
            return response()->json(['message' => 'Bahan baku berhasil ditambahkan', 'data' => $bahan], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /outlet/bahan-baku/{id}/konfigurasi — update stok_minimum, expired
    public function updateKonfigurasi(Request $request, string $id)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'stok_minimum'       => 'nullable|numeric|min:0',
            'tanggal_kadaluarsa' => 'nullable|date',
        ]);

        $bahan = BahanOutlet::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();
        $bahan = $this->service->updateKonfigurasi($bahan, $request->all());

        return response()->json(['message' => 'Konfigurasi bahan diupdate', 'data' => $bahan]);
    }

    // POST /outlet/stock-opname
    public function stockOpname(Request $request)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'bahan_master_id' => 'required|exists:bahan_master,id',
            'tipe'            => 'required|in:busuk,rusak,ga_layak,hilang',
            'jumlah'          => 'required|numeric|min:0.01',
            'keterangan'      => 'nullable|string|max:255',
            'foto_bukti'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $fotoPath = null;
        if ($request->hasFile('foto_bukti')) {
            $fotoPath = $request->file('foto_bukti')->storeAs(
                "stock-opname/{$outletId}",
                Str::uuid() . '.' . $request->file('foto_bukti')->getClientOriginalExtension(),
                'public'
            );
        }

        try {
            $opname = $this->service->stockOpname($outletId, $request->all(), $fotoPath);
            return response()->json(['message' => 'Stock opname berhasil dicatat', 'data' => $opname], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /outlet/alerts
    public function alerts()
    {
        $outletId = $this->getOutletId();
        return response()->json([
            'message' => 'Alert stok',
            'data'    => $this->service->getAlerts($outletId),
        ]);
    }
}