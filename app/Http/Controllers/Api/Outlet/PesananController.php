<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Services\Outlet\PesananService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;

class PesananController extends Controller
{
    use HasPagination, OutletAccess;

    public function __construct(private PesananService $service) {}

    // GET /outlet/pesanan?status=pending
    public function index(Request $request)
    {
        $outletId = $this->getOutletId();
        $pesanans = $this->service->getList($outletId, [
            'status'   => $request->status,
            'per_page' => $this->getPerPage($request),
        ]);

        return response()->json($this->paginateResponse($pesanans, 'Daftar pesanan'));
    }

    // GET /outlet/pesanan/{id}
    public function show(string $id)
    {
        $outletId = $this->getOutletId();
        return response()->json([
            'message' => 'Detail pesanan',
            'data'    => $this->service->getDetail($id, $outletId),
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
            return response()->json(['message' => 'Item berhasil ditambahkan', 'data' => $pesanan]);
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
            return response()->json(['message' => 'Qty berhasil diupdate', 'data' => $pesanan]);
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
            return response()->json(['message' => 'Item berhasil dihapus', 'data' => $pesanan]);
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
            return response()->json(['message' => 'Pesanan dikonfirmasi', 'data' => $pesanan]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /outlet/pesanan/{id}/bayar
    public function bayar(Request $request, string $id)
    {
        $outletId = $this->getOutletId();
        $request->validate([
            'metode' => 'required|in:tunai,transfer,qris',
        ]);

        $pesanan = Pesanan::where('id', $id)->where('outlet_id', $outletId)->firstOrFail();

        try {
            $pesanan = $this->service->konfirmasiPembayaran($pesanan, $request->metode);
            return response()->json(['message' => 'Pembayaran berhasil dikonfirmasi', 'data' => $pesanan]);
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
            return response()->json(['message' => 'Pesanan dibatalkan', 'data' => $pesanan]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}