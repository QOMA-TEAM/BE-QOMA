<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\{BahanOutlet, StockOpname};
use App\Services\Outlet\BahanOutletService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;
use App\Services\ImageService;
use App\Http\Resources\Outlet\{BahanOutletResource, StockOpnameResource};

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
            $request->only(['search', 'menipis', 'mendekati_expired', 'sort_by', 'sort_dir']),
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
    // STOCK OPNAME — DRAFT SYSTEM
    // ============================================================

    // GET /outlet/stock-opname/draft — list semua draft
    public function draftIndex()
    {
        $outletId = $this->getOutletId();
        $drafts   = $this->service->getDraftOpname($outletId);

        return response()->json([
            'message' => 'Daftar draft stock opname',
            'data'    => StockOpnameResource::collection($drafts),
            'total'   => $drafts->count(),
        ]);
    }

    // POST /outlet/stock-opname/draft — buat atau update draft
    public function draftStore(Request $request)
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
            $opname = $this->service->buatDraftOpname($outletId, $request->all(), $fotoPath);
            return response()->json([
                'message' => 'Draft stock opname disimpan. Klik "Simpan Final" jika sudah yakin.',
                'data'    => new StockOpnameResource($opname),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PUT /outlet/stock-opname/draft/{id} — edit draft
    public function draftUpdate(Request $request, string $id)
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
            $opname = $this->service->updateDraftOpname($opname, $request->all(), $fotoPath);
            return response()->json([
                'message' => 'Draft berhasil diupdate',
                'data'    => new StockOpnameResource($opname),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // DELETE /outlet/stock-opname/draft/{id} — hapus draft
    public function draftDestroy(string $id)
    {
        $outletId = $this->getOutletId();
        $opname   = StockOpname::where('id', $id)
                               ->where('outlet_id', $outletId)
                               ->firstOrFail();

        try {
            $this->service->hapusDraftOpname($opname);
            return response()->json(['message' => 'Draft berhasil dihapus']);
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
                'message' => "✅ {$hasil['total_berhasil']} item berhasil difinalisasi.",
                'data'    => $hasil,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /outlet/stock-opname — list semua (draft + final)
    public function opnameIndex(Request $request)
    {
        $outletId = $this->getOutletId();

        $query = StockOpname::select('id', 'outlet_id', 'bahan_master_id', 'tipe', 'jumlah', 'foto_bukti', 'keterangan', 'status', 'created_at')
                            ->where('outlet_id', $outletId)
                            ->with('bahanMaster:id,nama,satuan')
                            ->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $opnames = $query->paginate($this->getPerPage($request));

        return response()->json(
            $this->paginateResponse(
                $opnames->through(fn($o) => new StockOpnameResource($o)),
                'Daftar stock opname'
            )
        );
    }
}