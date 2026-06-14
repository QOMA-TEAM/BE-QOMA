<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Services\Outlet\PesananService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;
use App\Http\Resources\SuperAdmin\PlanResource;
use App\Http\Resources\Outlet\PesananResource;

class PesananController extends Controller
{
    use HasPagination, OutletAccess;

    public function __construct(private PesananService $service) {}

    // GET /outlet/pesanan?status=pending&search=siti
    public function index(Request $request)
    {
        $outletId = $this->getOutletId();
        $pesanans = $this->service->getList($outletId, [
            'status'   => $request->status,
            'search'   => $request->search, 
            'per_page' => $this->getPerPage($request),
        ]);

        return response()->json(
            $this->paginateResponse(
                $pesanans->through(fn($p) => new PesananResource($p)),
                'Daftar pesanan'
            )
        );
    }
    // GET /outlet/pesanan/{id}
   public function show(string $id)
    {
        $outletId = $this->getOutletId();

        // ← $pesanan tidak pernah didefinisikan, fix:
        $pesanan = $this->service->getDetail($id, $outletId);

        return response()->json([
            'message' => 'Detail pesanan',
            'data'    => new PesananResource($pesanan),
        ]);
    }

    // POST /outlet/pesanan/{id}/tambah-item — kasir tambah item
    public function tambahItem(Request $request, string $id)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'items'           => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menu,id',
            'items.*.qty'     => 'required|integer|min:1',
        ]);

        $pesanan = Pesanan::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();

        try {
            $pesanan = $this->service->tambahItem($pesanan, $request->items);
            return response()->json(['message' => 'Item berhasil ditambahkan', 'data' => new PesananResource($pesanan)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /outlet/pesanan/{id}/item/{detail_id}/qty — update qty
    public function updateQty(Request $request, string $id, string $detailId)
    {
        $outletId = $this->getOutletId();
        $request->validate(['qty' => 'required|integer|min:0']);

        $pesanan = Pesanan::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();

        try {
            $pesanan = $this->service->updateQtyItem($pesanan, $detailId, $request->qty);
            return response()->json(['message' => 'Qty berhasil diupdate', 'data' => new PesananResource($pesanan)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // DELETE /outlet/pesanan/{id}/item/{detail_id} — hapus item
    public function hapusItem(string $id, string $detailId)
    {
        $outletId = $this->getOutletId();
        $pesanan  = Pesanan::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();

        try {
            $pesanan = $this->service->hapusItem($pesanan, $detailId);
            return response()->json(['message' => 'Item berhasil dihapus', 'data' => new PesananResource($pesanan)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /outlet/pesanan/{id}/konfirmasi
    public function konfirmasi(string $id)
    {
        $outletId = $this->getOutletId();
        $pesanan  = Pesanan::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();

        try {
            $pesanan = $this->service->konfirmasi($pesanan);
            return response()->json(['message' => 'Pesanan dikonfirmasi', 'data' => new PesananResource($pesanan)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /outlet/pesanan/{id}/bayar
    public function bayar(Request $request, string $id)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'metode' => 'required|in:tunai,transfer,qris,debit',
        ]);

        $pesanan = Pesanan::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();

        try {
            $pesanan = $this->service->konfirmasiPembayaran($pesanan, $request->metode);
            return response()->json(['message' => 'Pembayaran berhasil dikonfirmasi', 'data' => new PesananResource($pesanan)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /outlet/pesanan/{id}/cancel
    public function cancel(string $id)
    {
        $outletId = $this->getOutletId();
        $pesanan  = Pesanan::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();

        try {
            $pesanan = $this->service->cancel($pesanan);
            return response()->json(['message' => 'Pesanan dibatalkan', 'data' => new PesananResource($pesanan)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /outlet/pesanan/{id}/tipe
    public function updateTipe(Request $request, string $id)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'tipe_pesanan' => 'required|in:dine_in,take_away',
        ]);

        $pesanan = Pesanan::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();

        try {
            $pesanan = $this->service->updateTipePesanan($pesanan, $request->tipe_pesanan);
            return response()->json(['message' => 'Tipe pesanan diupdate', 'data' => $pesanan]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /outlet/pesanan/semua?search=...
    public function semua(Request $request)
    {
        $outletId = $this->getOutletId();
        $this->service->autoExpirePesananPublic($outletId);

        $query = \App\Models\Pesanan::select(
                    'id', 'outlet_id', 'meja_id', 'nama_pelanggan',
                    'no_telp', 'total_harga', 'status', 'tipe_pesanan',
                    'expired_at', 'created_at'
                )
                ->where('outlet_id', $outletId)
                ->with(['meja:id,nomor_meja', 'details:id,pesanan_id,menu_id,qty,harga', 'details.menu:id,nama'])
                ->latest();

        // ← TIDAK ADA default exclude — semua status tampil termasuk confirmed
        if ($request->status)       $query->where('status', $request->status);
        if ($request->tipe_pesanan) $query->where('tipe_pesanan', $request->tipe_pesanan);
        if ($request->dari)         $query->whereDate('created_at', '>=', $request->dari);
        if ($request->sampai)       $query->whereDate('created_at', '<=', $request->sampai);

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                ->orWhere('nama_pelanggan', 'like', "%{$search}%");
            });
        }

        $pesanans = $query->paginate($this->getPerPage($request));

        return response()->json(
            $this->paginateResponse(
                $pesanans->through(fn($p) => new PesananResource($p)),
                'Semua pesanan'
            )
        );
    }
}