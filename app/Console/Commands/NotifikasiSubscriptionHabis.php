<?php
namespace App\Console\Commands;

use App\Models\{Outlet, Subscription};
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifikasiSubscriptionHabis extends Command
{
    protected $signature   = 'subscription:notifikasi-habis';
    protected $description = 'Kirim notifikasi ke owner & outlet jika subscription mau habis (H-7 sampai H-1)';

    public function handle(): void
    {
        $today = now()->toDateString();

        // Cari subscription yang akan expired dalam 7 hari ke depan
        $subscriptions = Subscription::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween(
                DB::raw("DATE(end_date)"),
                [
                    now()->addDay()->toDateString(),
                    now()->addDays(7)->toDateString()
                ]
            )
            ->with(['usaha.owner', 'plan'])
            ->get();

        foreach ($subscriptions as $sub) {
            $sisaHari = $sub->sisaHari();
            $usaha    = $sub->usaha;

            if (!$usaha) continue;

            // Notif ke owner
            if ($usaha->owner_id) {
                NotificationService::notify(
                    $usaha->owner_id,
                    '⚠️ Subscription Akan Berakhir',
                    "Subscription {$sub->plan->nama_plan} usaha '{$usaha->nama_usaha}' akan berakhir dalam {$sisaHari} hari ({$sub->end_date->format('d M Y')}). Segera perpanjang untuk tetap bisa menggunakan semua outlet.",
                    'subscription_akan_habis',
                    ['usaha_id' => $usaha->id, 'sisa_hari' => $sisaHari, 'end_date' => $sub->end_date],
                );
            }

            // Notif ke SEMUA outlet milik usaha ini
            $outletUsers = \App\Models\User::whereHas('role', fn($q) => $q->where('name', 'outlet'))
                ->where('usaha_id', $usaha->id)
                ->where('is_active', 'true')
                ->pluck('id');

            foreach ($outletUsers as $userId) {
                NotificationService::notify(
                    $userId,
                    '⚠️ Subscription Usaha Akan Berakhir',
                    "Subscription usaha '{$usaha->nama_usaha}' akan berakhir dalam {$sisaHari} hari. Hubungi owner untuk perpanjangan.",
                    'subscription_akan_habis',
                    ['usaha_id' => $usaha->id, 'sisa_hari' => $sisaHari],
                );
            }

            $this->info("Notifikasi dikirim: {$usaha->nama_usaha} - sisa {$sisaHari} hari");
        }

        $this->info('✅ Selesai: ' . $subscriptions->count() . ' subscription diproses');
    }
}
