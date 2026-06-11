<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\{BahanOutlet, StockOpname};
use App\Services\Outlet\BahanOutletService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;
use App\Services\ImageService;
use App\Http\Resources\Outlet\{BahanOutletResource, StockOpnameResource, StockOpnameSessionResource};

class BahanOutletController extends Controller
{
    use HasPagination, OutletAccess;

    public function __construct(
        private BahanOutletService $service,
        private ImageService $imageService
    ) {}

    // GET /outlet/bahan-baku
    public function index(Request $request)
    {
        $outletId = $this->getOutletId();
        $bahans   = $this->service->getList($outletId, array_merge(
            $request->only(['search', 'satuan', 'menipis', 'mendekati_expired', 'sort_by', 'sort_dir']),
            ['per_page' => $this->getPerPage($request)]
        ));

        return response()->json(
            $this->paginateResponse(
                $bahans->through(fn($b) => new BahanOutletResource($b)),
                'Daftar bahan baku'
            )
        );
    }

    // GET /outlet/bahan-master
    public function bahanMaster(Request $request)
    {
        $outlet = $this->getOutlet();
        $usahaId = $outlet->usaha_id;

        $query = \App\Models\BahanMaster::where('usaha_id', $usahaId);

        if ($request->search) {
            $query->where('nama', 'like', "%{$request->search}%");
        }

        $bahans = $query->orderBy('nama')->paginate($this->getPerPage($request));

        return response()->json($this->paginateResponse($bahans, 'Daftar bahan master'));
    }

    // POST /outlet/bahan-baku
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
            return response()->json([
                'message' => 'Bahan baku berhasil ditambahkan',
                'data'    => new BahanOutletResource($bahan),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /outlet/bahan-baku/{id}/konfigurasi
    public function updateKonfigurasi(Request $request, string $id)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'stok_minimum'       => 'nullable|numeric|min:0',
            'tanggal_kadaluarsa' => 'nullable|date',
        ]);

        $bahan = BahanOutlet::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();
        $bahan = $this->service->updateKonfigurasi($bahan, $request->all());

        return response()->json([
            'message' => 'Konfigurasi bahan diupdate',
            'data'    => new BahanOutletResource($bahan),
        ]);
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

    // ============================================================
    // STOCK OPNAME — SESSION SYSTEM
    // ============================================================

    public function sesiHariIni()
    {
        $outletId = $this->getOutletId();
        $sesi = $this->service->getSesiHariIni($outletId);

        return response()->json([
            'message' => 'Info sesi hari ini',
            'data'    => $sesi ? new StockOpnameSessionResource($sesi) : null,
        ]);
    }

    public function historiSesi(Request $request)
    {
        $outletId = $this->getOutletId();
        $history = $this->service->getListSesi($outletId, $this->getPerPage($request));

        return response()->json(
            $this->paginateResponse(
                $history->through(fn($s) => new StockOpnameSessionResource($s)),
                'Histori sesi stock opname'
            )
        );
    }

    public function tambahItem(Request $request)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'bahan_master_id' => 'required|exists:bahan_master,id',
            'tipe'            => 'required|in:busuk,rusak,ga_layak,hilang',
            'jumlah'          => 'required|numeric|min:0.01',
            'keterangan'      => 'nullable|string|max:255',
            'foto_bukti'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $fotoPath = $request->hasFile('foto_bukti')
            ? $this->imageService->upload($request->file('foto_bukti'), "stock-opname/{$outletId}")
            : null;

        try {
            $opname = $this->service->buatDraftItem($outletId, $request->all(), $fotoPath);
            return response()->json([
                'message' => 'Item ditambahkan ke sesi hari ini',
                'data'    => new StockOpnameResource($opname),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function updateItem(Request $request, string $id)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'tipe'       => 'sometimes|in:busuk,rusak,ga_layak,hilang',
            'jumlah'     => 'sometimes|numeric|min:0.01',
            'keterangan' => 'nullable|string|max:255',
            'foto_bukti' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $opname = StockOpname::where('id', $id)
                             ->where('outlet_id', $outletId)
                             ->firstOrFail();

        $fotoPath = $request->hasFile('foto_bukti')
            ? $this->imageService->upload($request->file('foto_bukti'), "stock-opname/{$outletId}")
            : null;

        try {
            $opname = $this->service->updateDraftItem($opname, $request->all(), $fotoPath);
            return response()->json([
                'message' => 'Item berhasil diupdate',
                'data'    => new StockOpnameResource($opname),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function hapusItem(string $id)
    {
        $outletId = $this->getOutletId();
        $opname   = StockOpname::where('id', $id)
                               ->where('outlet_id', $outletId)
                               ->firstOrFail();

        try {
            $this->service->hapusDraftItem($opname);
            return response()->json(['message' => 'Item berhasil dihapus']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /outlet/stock-opname/simpan
     * Finalisasi SEMUA draft sekaligus — 1x klik "Simpan"
     */
    public function simpan()
    {
        $outletId = $this->getOutletId();

        try {
            $hasil = $this->service->finalisasiSemuaDraft($outletId);

            return response()->json([
                'message' => "✅ {$hasil['total_item']} item berhasil difinalisasi.",
                'data'    => $hasil,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function tutupSesi()
    {
        $outletId = $this->getOutletId();
        try {
            $sesi = $this->service->tutupSesi($outletId);
            return response()->json([
                'message' => 'Sesi stock opname hari ini berhasil ditutup',
                'data'    => new StockOpnameSessionResource($sesi),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}