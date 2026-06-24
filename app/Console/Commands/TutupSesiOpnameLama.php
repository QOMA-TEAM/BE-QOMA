<?php
namespace App\Console\Commands;

use App\Models\StockOpnameSession;
use App\Services\Outlet\BahanOutletService;
use Illuminate\Console\Command;

class TutupSesiOpnameLama extends Command
{
    protected $signature   = 'stock-opname:tutup-sesi-lama';
    protected $description = 'Auto-tutup sesi stock opname yang masih open dari hari-hari sebelumnya';

    public function handle(BahanOutletService $service): void
    {
        $sesiLama = StockOpnameSession::where('status', 'open')
                                       ->where('tanggal', '<', now()->toDateString())
                                       ->get();

        if ($sesiLama->isEmpty()) {
            $this->info('Tidak ada sesi lama yang perlu ditutup.');
            return;
        }

        foreach ($sesiLama->groupBy('outlet_id') as $outletId => $sesis) {
            // Panggil getSesiHariIni untuk trigger auto-close lewat service
            // (memastikan logic konsisten dengan on-the-fly check)
            $service->getSesiHariIni($outletId);

            $this->info("Outlet {$outletId}: {$sesis->count()} sesi lama ditutup.");
        }

        $this->info('✅ Selesai: ' . $sesiLama->count() . ' sesi ditutup.');
    }
}
