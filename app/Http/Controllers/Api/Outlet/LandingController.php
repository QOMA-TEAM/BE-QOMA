<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\{Kerugian, LaporanKeuangan, Pengeluaran, Pesanan};
use App\Services\LaporanKeuanganService;
use App\Traits\{HasPagination, OutletAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeuanganOutletController extends Controller
{
    use OutletAccess, HasPagination;

    public function __construct(private LaporanKeuanganService $service) {}

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

        $laporan = LaporanKeuangan::select(
                        'id', 'outlet_id', 'total_pendapatan', 'total_pengeluaran',
                        'total_kerugian', 'total_keuntungan', 'periode'
                    )
                    ->where('outlet_id', $outletId)
                    ->where('tipe_periode', 'daily')
                    ->whereBetween('periode', [$dari, $sampai])
                    ->orderBy('periode')
                    ->get();

        $totalPendapatan  = (float) $laporan->sum('total_pendapatan');
        $totalPengeluaran = (float) $laporan->sum('total_pengeluaran');
        $totalKerugian    = (float) $laporan->sum('total_kerugian');
        $totalKeuntungan  = $totalPendapatan - $totalPengeluaran - $totalKerugian;
        $statusKeuangan   = $totalKeuntungan >= 0 ? 'untung' : 'rugi';
        $selisih          = abs($totalKeuntungan);

        // Kalau belum ada data laporan, hitung langsung dari transaksi
        if ($laporan->isEmpty()) {
            $totalPendapatan = (float) Pesanan::where('outlet_id', $outletId)
                ->where('status', 'paid')
                ->whereBetween(DB::raw('DATE(updated_at)'), [$dari, $sampai])
                ->sum('total_harga');

            $totalPengeluaran = (float) Pengeluaran::where('outlet_id', $outletId)
                ->whereBetween('tanggal', [$dari, $sampai])
                ->sum('total');

            $totalKerugian = (float) Kerugian::where('outlet_id', $outletId)
                ->whereBetween('tanggal', [$dari, $sampai])
                ->sum('total_rugi');

            $totalKeuntungan = $totalPendapatan - $totalPengeluaran - $totalKerugian;
            $statusKeuangan  = $totalKeuntungan >= 0 ? 'untung' : 'rugi';
            $selisih         = abs($totalKeuntungan);
        }

        $perPage  = $this->getPerPage($request);
        $page     = (int) $request->get('page', 1);
        $tipe     = $request->get('tipe', 'semua'); // pendapatan|pengeluaran|kerugian|semua

        $transaksi = $this->getListTransaksi($outletId, $dari, $sampai);

        // Filter by tipe (server-side so pagination works correctly)
        if ($tipe !== 'semua') {
            $transaksi = array_values(array_filter($transaksi, fn($t) => $t['tipe'] === $tipe));
        }

        $total = count($transaksi);
        $items = array_values(array_slice($transaksi, ($page - 1) * $perPage, $perPage));

        return response()->json([
            'message' => 'Laporan keuangan outlet',
            'filter'  => ['range' => $range, 'dari' => $dari, 'sampai' => $sampai, 'tipe' => $tipe],
            'data'    => [
                'cards' => [
                    'total_pendapatan'  => $totalPendapatan,
                    'total_pengeluaran' => $totalPengeluaran,
                    'total_kerugian'    => $totalKerugian,
                    'total_keuntungan'  => $totalKeuntungan,
                    'status'            => $statusKeuangan,
                    'selisih'           => $selisih,
                    'pesan'             => $statusKeuangan === 'untung'
                        ? "Outlet untung Rp " . number_format($selisih) . " dalam periode ini."
                        : "⚠️ Outlet rugi Rp " . number_format($selisih) . ". Pertimbangkan untuk menaikkan harga menu.",
                ],
                'grafik' => $laporan->map(fn($l) => [
                    'tanggal'           => $l->periode,
                    'total_pendapatan'  => (float) $l->total_pendapatan,
                    'total_pengeluaran' => (float) $l->total_pengeluaran,
                    'total_kerugian'    => (float) $l->total_kerugian,
                    'total_keuntungan'  => (float) $l->total_keuntungan,
                    'status'            => $l->total_keuntungan >= 0 ? 'untung' : 'rugi',
                ]),
                'transaksi' => [
                    'data' => $items,
                    'meta' => [
                        'current_page' => $page,
                        'per_page'     => $perPage,
                        'total'        => $total,
                        'last_page'    => (int) ceil($total / $perPage) ?: 1,
                    ],
                ],
            ],
        ]);
    }

    private function getListTransaksi(string $outletId, string $dari, string $sampai): array
    {
        $pendapatan = Pesanan::select('id', 'meja_id', 'nama_pelanggan', 'total_harga', 'updated_at')
            ->where('outlet_id', $outletId)
            ->where('status', 'paid')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$dari, $sampai])
            ->with('meja:id,nomor_meja')
            ->get()
            ->map(fn($p) => [
                'tipe'       => 'pendapatan',
                'id'         => $p->id,
                'keterangan' => "Pesanan - {$p->nama_pelanggan}" . ($p->meja ? " (Meja {$p->meja->nomor_meja})" : ''),
                'nominal'    => (float) $p->total_harga,
                'tanggal'    => $p->updated_at->toDateString(),
                'waktu'      => $p->updated_at->format('H:i'),
            ]);

        $pengeluaran = Pengeluaran::select('id', 'sumber', 'total', 'tanggal', 'updated_at')
            ->where('outlet_id', $outletId)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->get()
            ->map(fn($p) => [
                'tipe'       => 'pengeluaran',
                'id'         => $p->id,
                'keterangan' => $p->sumber ?? 'Pengeluaran',
                'nominal'    => (float) $p->total,
                'tanggal'    => $p->tanggal,
                'waktu'      => $p->updated_at ? $p->updated_at->format('H:i') : '-',
            ]);

        $kerugian = Kerugian::select('id', 'total_rugi', 'tanggal', 'updated_at')
            ->where('outlet_id', $outletId)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->get()
            ->map(fn($k) => [
                'tipe'       => 'kerugian',
                'id'         => $k->id,
                'keterangan' => 'Kerugian operasional',
                'nominal'    => (float) $k->total_rugi,
                'tanggal'    => $k->tanggal,
                'waktu'      => $k->updated_at ? $k->updated_at->format('H:i') : '-',
            ]);

        return collect($pendapatan)
            ->concat($pengeluaran)
            ->concat($kerugian)
            ->sortByDesc(function ($item) {
                $waktu = $item['waktu'] !== '-' ? $item['waktu'] : '00:00';
                return $item['tanggal'] . ' ' . $waktu;
            })
            ->values()
            ->toArray();
    }
}
