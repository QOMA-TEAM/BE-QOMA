<?php
namespace App\Console\Commands;

use App\Models\{Outlet, OutletDeactivationQueue, Subscription, User};
use App\Services\{ActivityLogService, NotificationService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProsesGracePeriodHabis extends Command
{
    protected $signature   = 'subscription:proses-grace-period';
    protected $description = 'Proses subscription yang sudah melewati grace period';

    public function handle(): void
    {
        $today = now()->toDateString();

        // 1. Subscription yang baru expired hari ini → masuk grace period
        $baruExpired = Subscription::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->whereNull('grace_period_end')
            ->with(['usaha.owner', 'plan'])
            ->get();

        foreach ($baruExpired as $sub) {
            $gracePeriodEnd = now()->addDays(3)->toDateString();

            $sub->update([
                'grace_period_end' => $gracePeriodEnd,
            ]);

            $usaha = $sub->usaha;
            if (!$usaha) continue;

            // Notif owner — masuk grace period
            if ($usaha->owner_id) {
                NotificationService::notify(
                    $usaha->owner_id,
                    '🔴 Subscription Expired — Grace Period 3 Hari',
                    "Subscription {$sub->plan->nama_plan} usaha '{$usaha->nama_usaha}' telah berakhir. Anda masih memiliki akses penuh selama 3 hari (sampai " . now()->addDays(3)->format('d M Y') . "). Setelah itu, pilih outlet yang ingin dinonaktifkan atau sistem akan otomatis menonaktifkan outlet terbaru.",
                    'grace_period_dimulai',
                    ['usaha_id' => $usaha->id, 'grace_period_end' => $gracePeriodEnd],
                );
            }

            // Notif ke semua outlet
            $outletUsers = User::whereHas('role', fn($q) => $q->where('name', 'outlet'))
                ->where('usaha_id', $usaha->id)
                ->where('is_active', true)
                ->pluck('id');

            foreach ($outletUsers as $userId) {
                NotificationService::notify(
                    $userId,
                    '⚠️ Masa Grace Period Aktif',
                    "Subscription usaha '{$usaha->nama_usaha}' telah expired. Outlet masih bisa beroperasi selama 3 hari grace period.",
                    'grace_period_dimulai',
                    ['usaha_id' => $usaha->id],
                );
            }

            $this->info("Grace period dimulai: {$usaha->nama_usaha}");
        }

        // 2. Grace period yang sudah habis → buat deactivation queue
        $graceHabis = Subscription::where('status', 'active')
            ->whereNotNull('grace_period_end')
            ->where('grace_period_end', '<', $today)
            ->whereNotIn('usaha_id', OutletDeactivationQueue::where('is_processed', false)->pluck('usaha_id'))
            ->with(['usaha', 'plan'])
            ->get();

        foreach ($graceHabis as $sub) {
            $usaha = $sub->usaha;
            if (!$usaha) continue;

            // Hitung berapa outlet yang harus dinonaktifkan
            $freePlan      = \App\Models\Plan::where('is_lifetime', true)->first();
            $batasFreePlan = $freePlan ? $freePlan->batas_outlet : 2;
            $jumlahOutlet  = Outlet::where('usaha_id', $usaha->id)->where('status_buka', true)->count();
            $harusNonaktif = max(0, $jumlahOutlet - $batasFreePlan);

            if ($harusNonaktif > 0) {
                // Buat deactivation queue — deadline 24 jam
                OutletDeactivationQueue::create([
                    'id'                     => Str::uuid(),
                    'usaha_id'               => $usaha->id,
                    'subscription_id'        => $sub->id,
                    'jumlah_harus_nonaktif'  => $harusNonaktif,
                    'deadline'               => now()->addHours(24),
                    'is_processed'           => false,
                ]);

                // Notif owner — harus pilih outlet
                if ($usaha->owner_id) {
                    NotificationService::notify(
                        $usaha->owner_id,
                        '🚨 Pilih Outlet yang Dinonaktifkan',
                        "Grace period berakhir. Anda harus memilih {$harusNonaktif} outlet yang akan dinonaktifkan dalam 24 jam. Jika tidak memilih, sistem akan otomatis menonaktifkan {$harusNonaktif} outlet yang paling baru ditambahkan.",
                        'harus_pilih_outlet',
                        [
                            'usaha_id'              => $usaha->id,
                            'jumlah_harus_nonaktif' => $harusNonaktif,
                            'deadline'              => now()->addHours(24),
                        ],
                    );
                }

                $this->info("Deactivation queue dibuat: {$usaha->nama_usaha} - {$harusNonaktif} outlet harus nonaktif");
            } else {
                // Tidak perlu nonaktifkan outlet, langsung downgrade
                $this->downgradeKeFree($sub);
            }
        }

        // 3. Deactivation queue yang sudah lewat deadline → auto nonaktifkan outlet terbaru
        $autoDeactivate = OutletDeactivationQueue::where('is_processed', false)
            ->where('deadline', '<', now())
            ->with('usaha')
            ->get();

        foreach ($autoDeactivate as $queue) {
            $this->autoNonaktifkanOutletTerbaru($queue);
        }

        $this->info('✅ Proses grace period selesai');
    }

    private function autoNonaktifkanOutletTerbaru(OutletDeactivationQueue $queue): void
    {
        DB::transaction(function () use ($queue) {
            $usaha = $queue->usaha;

            // Ambil outlet terbaru yang aktif
            $outletTerbaru = Outlet::where('usaha_id', $queue->usaha_id)
                ->where('status_buka', true)
                ->orderByDesc('created_at')
                ->limit($queue->jumlah_harus_nonaktif)
                ->get();

            foreach ($outletTerbaru as $outlet) {
                $outlet->update(['status_buka' => false]);

                // Nonaktifkan user outlet ini
                User::where('outlet_id', $outlet->id)->update(['is_active' => false]);

                // Notif outlet
                $outletUsers = User::where('outlet_id', $outlet->id)->pluck('id');
                foreach ($outletUsers as $userId) {
                    NotificationService::notify(
                        $userId,
                        '🔴 Outlet Dinonaktifkan Otomatis',
                        "Outlet '{$outlet->nama_outlet}' dinonaktifkan otomatis karena subscription usaha '{$usaha->nama_usaha}' telah berakhir dan owner tidak memilih outlet dalam batas waktu.",
                        'outlet_auto_deactivated',
                        ['outlet_id' => $outlet->id],
                    );
                }

                ActivityLogService::log(
                    'auto_deactivate_outlet',
                    "Outlet '{$outlet->nama_outlet}' dinonaktifkan otomatis karena subscription expired",
                    ['outlet_id' => $outlet->id, 'usaha_id' => $queue->usaha_id],
                    $queue->usaha_id,
                    $outlet->id,
                );
            }

            // Tandai queue selesai
            $queue->update(['is_processed' => true]);

            // Downgrade ke Free
            $this->downgradeKeFree($queue->subscription);

            // Notif owner
            if ($usaha->owner_id) {
                NotificationService::notify(
                    $usaha->owner_id,
                    'Downgrade ke Free Plan',
                    "Usaha '{$usaha->nama_usaha}' telah downgrade ke Free plan. " . $outletTerbaru->count() . " outlet terbaru telah dinonaktifkan otomatis.",
                    'auto_downgrade_free',
                    ['usaha_id' => $queue->usaha_id],
                );
            }
        });

        $this->info("Auto deactivate: {$queue->usaha->nama_usaha}");
    }

    private function downgradeKeFree(Subscription $sub): void
    {
        // Cari free plan (lifetime / harga 0)
        $freePlan = \App\Models\Plan::where('is_lifetime', true)
            ->orderBy('harga')
            ->first();

        if (!$freePlan) {
            $this->error("Free plan tidak ditemukan! Tidak bisa downgrade usaha {$sub->usaha_id}");
            return;
        }

        $sub->update(['status' => 'expired']);

        Subscription::create([
            'id'         => Str::uuid(),
            'usaha_id'   => $sub->usaha_id,
            'plan_id'    => $freePlan->id,
            'start_date' => now()->toDateString(),
            'end_date'   => null,
            'status'     => 'active',
            'tipe'       => 'downgrade',
        ]);

        ActivityLogService::log(
            'auto_downgrade_free',
            'Subscription expired. Downgrade ke Free plan.',
            ['usaha_id' => $sub->usaha_id],
            $sub->usaha_id,
        );
    }
}