<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Kerugian;
use App\Models\LaporanKeuangan;
use App\Models\Outlet;
use App\Models\Pengeluaran;
use App\Models\Pesanan;
use App\Services\LaporanKeuanganService;
use App\Traits\HasPagination; 
use App\Traits\OwnerAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeuanganController extends Controller
{
    use OwnerAccess, HasPagination;

    /**
     * GET /owner/keuangan
     * Summary dashboard (card + grafik sederhana)
     */
    public function index(Request $request)
    {
        $usahaId  = $this->getUsahaId();
        $range    = $request->get('range', '7days');
        $outletId = $request->get('outlet_id');

        [$dari, $sampai] = $this->getRange($range);

        // Ambil outlet milik usaha
        $outletIds = Outlet::where('usaha_id', $usahaId)
            ->when($outletId, fn($q) => $q->where('id', $outletId))
            ->pluck('id');

        // =========================
        // HITUNG SUMMARY
        // =========================

        $totalPendapatan = Pesanan::whereIn('outlet_id', $outletIds)
            ->where('status', 'paid')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$dari, $sampai])
            ->sum('total_harga');

        $totalPengeluaran = Pengeluaran::whereIn('outlet_id', $outletIds)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->sum('total');

        $totalKerugian = Kerugian::whereIn('outlet_id', $outletIds)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->sum('total_rugi');

        $totalKeuntungan = $totalPendapatan - $totalPengeluaran;

        return response()->json([
            'message' => 'Summary keuangan',
            'filter'  => compact('range', 'dari', 'sampai', 'outletId'),
            'data'    => [
                'total_pendapatan'  => (float) $totalPendapatan,
                'total_pengeluaran' => (float) $totalPengeluaran,
                'total_kerugian'    => (float) $totalKerugian,
                'total_keuntungan'  => (float) $totalKeuntungan,
            ]
        ]);
    }

    /**
     * GET /owner/keuangan/list
     * Detail transaksi
     */
    public function listTransaksi(Request $request)
    {
        $usahaId  = $this->getUsahaId();
        $range    = $request->get('range', '7days');
        $outletId = $request->get('outlet_id');
        $tipe     = $request->get('tipe', 'semua');
        $perPage  = $request->get('per_page', 15);
        $page     = $request->get('page', 1);

        [$dari, $sampai] = $this->getRange($range);

        $outletIds = Outlet::where('usaha_id', $usahaId)
            ->when($outletId, fn($q) => $q->where('id', $outletId))
            ->pluck('id');

        $result = collect();

        if (in_array($tipe, ['semua', 'pendapatan'])) {
            $pendapatan = Pesanan::whereIn('outlet_id', $outletIds)
                ->where('status', 'paid')
                ->whereBetween(DB::raw('DATE(updated_at)'), [$dari, $sampai])
                ->with('outlet:id,nama_outlet')
                ->get()
                ->map(fn($p) => [
                    'tipe'       => 'pendapatan',
                    'id'         => $p->id,
                    'outlet'     => $p->outlet->nama_outlet ?? '-',
                    'keterangan' => "Pesanan #{$p->id}",
                    'nominal'    => (float) $p->total_harga,
                    'tanggal'    => $p->updated_at->toDateString(),
                ]);
            $result = $result->merge($pendapatan);
        }

        if (in_array($tipe, ['semua', 'pengeluaran'])) {
            $pengeluaran = Pengeluaran::whereIn('outlet_id', $outletIds)
                ->whereBetween('tanggal', [$dari, $sampai])
                ->with('outlet:id,nama_outlet')
                ->get()
                ->map(fn($p) => [
                    'tipe'       => 'pengeluaran',
                    'id'         => $p->id,
                    'outlet'     => $p->outlet->nama_outlet ?? '-',
                    'keterangan' => $p->sumber ?? 'Pengeluaran',
                    'nominal'    => (float) $p->total,
                    'tanggal'    => $p->tanggal,
                ]);
            $result = $result->merge($pengeluaran);
        }

        if (in_array($tipe, ['semua', 'kerugian'])) {
            $kerugian = Kerugian::whereIn('outlet_id', $outletIds)
                ->whereBetween('tanggal', [$dari, $sampai])
                ->with('outlet:id,nama_outlet')
                ->get()
                ->map(fn($p) => [
                    'tipe'       => 'kerugian',
                    'id'         => $p->id,
                    'outlet'     => $p->outlet->nama_outlet ?? '-',
                    'keterangan' => 'Kerugian operasional',
                    'nominal'    => (float) $p->total_rugi,
                    'tanggal'    => $p->tanggal,
                ]);
            $result = $result->merge($kerugian);
        }

        // Sort & manual paginate
        $sorted = $result->sortByDesc('tanggal')->values();
        $total  = $sorted->count();
        $items  = $sorted->forPage($page, $perPage)->values();

        return response()->json([
            'message' => 'List transaksi keuangan',
            'filter'  => compact('range', 'dari', 'sampai', 'tipe'),
            'data'    => $items,
            'meta'    => [
                'current_page' => (int) $page,
                'per_page'     => (int) $perPage,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $perPage),
                'from'         => $total > 0 ? ($page - 1) * $perPage + 1 : null,
                'to'           => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Helper range tanggal
     */
    private function getRange($range)
    {
        return match($range) {
            '1day'  => [now()->toDateString(), now()->toDateString()],
            '7days' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            '30days'=> [now()->subDays(29)->toDateString(), now()->toDateString()],
            default => [now()->subDays(6)->toDateString(), now()->toDateString()],
        };
    }
}