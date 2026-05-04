<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\{Kerugian, LaporanKeuangan, Pengeluaran, Pesanan};
use App\Services\LaporanKeuanganService;
use App\Traits\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeuanganOutletController extends Controller
{
    use OutletAccess;

    public function __construct(private LaporanKeuanganService $service) {}

    /**
     * GET /outlet/keuangan?range=7days
     *
     * Return:
     * - cards: pendapatan, pengeluaran, kerugian, keuntungan
     * - detail per hari (untuk grafik)
     * - status: untung / rugi
     */
    public function index(Request $request)
    {
        $outletId = $this->getOutletId();
        $range    = $request->get('range', '7days');

        if (!in_array($range, ['1day', '7days', '30days'])) {
            return response()->json([
                'message' => 'Range tidak valid. Gunakan: 1day, 7days, 30days',
            ], 422);
        }

        [$dari, $sampai] = match($range) {
            '1day'  => [now()->toDateString(), now()->toDateString()],
            '7days' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            default => [now()->subDays(29)->toDateString(), now()->toDateString()],
        };

        // Ambil dari laporan_keuangan (sudah di-cache saat ada transaksi)
        $laporan = LaporanKeuangan::where('outlet_id', $outletId)
            ->where('tipe_periode', 'daily')
            ->whereBetween('periode', [$dari, $sampai])
            ->orderBy('periode')
            ->get();

        $totalPendapatan  = $laporan->sum('total_pendapatan');
        $totalPengeluaran = $laporan->sum('total_pengeluaran');
        $totalKerugian    = $laporan->sum('total_kerugian');
        $totalKeuntungan  = $totalPendapatan - $totalPengeluaran - $totalKerugian;

        // Logic untung/rugi
        $statusKeuangan = $totalKeuntungan >= 0 ? 'untung' : 'rugi';
        $selisih        = abs($totalKeuntungan);

        return response()->json([
            'message' => 'Laporan keuangan outlet',
            'filter'  => ['range' => $range, 'dari' => $dari, 'sampai' => $sampai],
            'data'    => [
                // Cards
                'cards' => [
                    'total_pendapatan'  => (float) $totalPendapatan,
                    'total_pengeluaran' => (float) $totalPengeluaran,
                    'total_kerugian'    => (float) $totalKerugian,
                    'total_keuntungan'  => (float) $totalKeuntungan,
                    'status'            => $statusKeuangan,
                    'selisih'           => (float) $selisih,
                    'pesan'             => $statusKeuangan === 'untung'
                        ? "Outlet untung Rp " . number_format($selisih) . " dalam periode ini."
                        : "⚠️ Outlet rugi Rp " . number_format($selisih) . ". Pertimbangkan untuk menaikkan harga menu.",
                ],

                // List per hari (untuk grafik)
                'grafik' => $laporan->map(fn($l) => [
                    'tanggal'           => $l->periode,
                    'total_pendapatan'  => (float) $l->total_pendapatan,
                    'total_pengeluaran' => (float) $l->total_pengeluaran,
                    'total_kerugian'    => (float) $l->total_kerugian,
                    'total_keuntungan'  => (float) $l->total_keuntungan,
                    'status'            => $l->total_keuntungan >= 0 ? 'untung' : 'rugi',
                ]),

                // List transaksi detail
                'transaksi' => $this->getListTransaksi($outletId, $dari, $sampai),
            ],
        ]);
    }

    /**
     * Ambil list transaksi detail (pendapatan + pengeluaran + kerugian)
     */
    private function getListTransaksi(string $outletId, string $dari, string $sampai): array
    {
        // Pendapatan dari pesanan paid
        $pendapatan = Pesanan::where('outlet_id', $outletId)
            ->where('status', 'paid')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$dari, $sampai])
            ->with('meja:id,nomor_meja')
            ->select('id', 'meja_id', 'nama_pelanggan', 'total_harga', 'updated_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($p) => [
                'tipe'       => 'pendapatan',
                'id'         => $p->id,
                'keterangan' => "Pesanan - {$p->nama_pelanggan}" . ($p->meja ? " (Meja {$p->meja->nomor_meja})" : ''),
                'nominal'    => (float) $p->total_harga,
                'tanggal'    => $p->updated_at->toDateString(),
                'waktu'      => $p->updated_at->format('H:i'),
            ]);

        // Pengeluaran bahan baku
        $pengeluaran = Pengeluaran::where('outlet_id', $outletId)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn($p) => [
                'tipe'       => 'pengeluaran',
                'id'         => $p->id,
                'keterangan' => $p->sumber ?? 'Pembelian bahan baku',
                'nominal'    => (float) $p->total,
                'tanggal'    => $p->tanggal->format('Y-m-d'),
                'waktu'      => '-',
            ]);

        // Kerugian (dari stock opname + manual)
        $kerugian = Kerugian::where('outlet_id', $outletId)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn($k) => [
                'tipe'       => 'kerugian',
                'id'         => $k->id,
                'keterangan' => 'Kerugian operasional',
                'nominal'    => (float) $k->total_rugi,
                'tanggal'    => $k->tanggal->format('Y-m-d'),
                'waktu'      => '-',
            ]);

        // Gabung dan sort berdasarkan tanggal terbaru
        return collect($pendapatan)
            ->concat($pengeluaran)
            ->concat($kerugian)
            ->sortByDesc('tanggal')
            ->values()
            ->toArray();
    }
}