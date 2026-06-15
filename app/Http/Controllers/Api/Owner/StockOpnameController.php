<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Outlet\StockOpnameSessionResource;
use App\Models\Outlet;
use App\Models\StockOpnameSession;
use App\Traits\HasPagination;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    use HasPagination;

    /**
     * GET /owner/stock-opname
     * Mengambil histori stock opname dari semua outlet milik owner
     */
    public function index(Request $request)
    {
        $usahaId = auth()->user()->usaha_id;

        // 1. Ambil semua outlet ID yang dimiliki oleh usaha owner ini
        $outlets = Outlet::where('usaha_id', $usahaId)->pluck('id');

        // 2. Query histori sesi stock opname di outlet-outlet tersebut
        $query = StockOpnameSession::whereIn('outlet_id', $outlets)
            ->with(['outlet:id,nama_outlet'])
            ->withCount([
                'items as total_item',
                'items as total_draft' => fn($q) => $q->where('status', 'draft'),
                'items as total_final' => fn($q) => $q->where('status', 'final'),
            ])
            ->addSelect([
                'total_kerugian' => \App\Models\StockOpname::selectRaw('COALESCE(SUM(stock_opname.jumlah * bahan_master.harga_default), 0)')
                    ->join('bahan_master', 'bahan_master.id', '=', 'stock_opname.bahan_master_id')
                    ->whereColumn('stock_opname.session_id', 'stock_opname_sessions.id')
                    ->where('stock_opname.status', 'final')
            ]);
            
        // Filter spesifik ke 1 outlet jika dipilih dari dropdown
        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        // Urutkan dari yang terbaru
        $history = $query->orderByDesc('tanggal')
            ->paginate($this->getPerPage($request));

        return response()->json(
            $this->paginateResponse(
                $history->through(fn($s) => new StockOpnameSessionResource($s)),
                'Histori stock opname outlet'
            )
        );
    }
}
