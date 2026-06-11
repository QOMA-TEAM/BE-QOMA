<?php
namespace App\Services\Owner;

use App\Models\{Outlet, OutletDeactivationQueue, Plan, Subscription};
use App\Services\{ActivityLogService, NotificationService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OwnerSubscriptionService
{
    public function getAktif(string $usahaId): array
    {
        $sub = Subscription::where('usaha_id', $usahaId)
            ->whereIn('status', ['active', 'pending'])
            ->with('plan:id,nama_plan,harga,batas_outlet,durasi_hari,is_lifetime,deskripsi')
            ->latest()
            ->first();

        if (!$sub) {
            return ['message' => 'Tidak ada subscription aktif'];
        }

        $jumlahOutlet = Outlet::where('usaha_id', $usahaId)->count();
        $sisaHari     = $sub->plan->is_lifetime ? null : $sub->sisaHari();

        // Cek apakah ada deactivation queue yang belum diproses
        $deactivationQueue = OutletDeactivationQueue::where('usaha_id', $usahaId)
            ->where('is_processed', false)
            ->first();

        return [
            'subscription_id'  => $sub->id,
            'status'           => $sub->status,
            'tipe'             => $sub->tipe,
            'start_date'       => $sub->start_date,
            'end_date'         => $sub->end_date,
            'grace_period_end' => $sub->grace_period_end,
            'sisa_hari'        => $sisaHari,
            'is_in_grace_period' => $sub->isInGracePeriod(),
            'plan' => [
                'id'           => $sub->plan->id,
                'nama_plan'    => $sub->plan->nama_plan,
                'harga'        => (float) $sub->plan->harga,
                'batas_outlet' => $sub->plan->batas_outlet === -1 ? 'Unlimited' : $sub->plan->batas_outlet,
                'is_lifetime'  => $sub->plan->is_lifetime,
                'durasi_hari'  => $sub->plan->durasi_hari,
                'deskripsi'    => $sub->plan->deskripsi,
            ],
            'penggunaan_outlet' => [
                'dipakai'  => $jumlahOutlet,
                'maksimal' => $sub->plan->batas_outlet === -1 ? 'Unlimited' : $sub->plan->batas_outlet,
                'sisa'     => $sub->plan->batas_outlet === -1
                                ? 'Unlimited'
                                : max(0, $sub->plan->batas_outlet - $jumlahOutlet),
            ],
            // Info kalau perlu pilih outlet
            'perlu_pilih_outlet' => $deactivationQueue ? [
                'jumlah_harus_nonaktif' => $deactivationQueue->jumlah_harus_nonaktif,
                'deadline'              => $deactivationQueue->deadline,
                'pesan'                 => "Pilih {$deactivationQueue->jumlah_harus_nonaktif} outlet yang ingin dinonaktifkan sebelum " . $deactivationQueue->deadline->format('d M Y H:i'),
            ] : null,
        ];
    }

    public function upgrade(string $usahaId, string $planId, string $metodePembayaran): array
    {
        $planBaru = Plan::findOrFail($planId);

        $subSekarang = Subscription::where('usaha_id', $usahaId)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();

        if (!$subSekarang) {
            throw new \Exception('Tidak ada subscription aktif.');
        }

        // ← FIX: tidak boleh upgrade ke plan yang sama
        if ($planBaru->id === $subSekarang->plan_id) {
            throw new \Exception('Anda sudah menggunakan plan ini.');
        }

        // Tidak boleh upgrade ke Free plan
        if ($planBaru->is_lifetime) {
            throw new \Exception('Tidak bisa upgrade ke Free plan. Gunakan plan berbayar.');
        }

        // Buat subscription baru tipe upgrade — status pending tunggu konfirmasi
        $subBaru = Subscription::create([
            'id'         => Str::uuid(),
            'usaha_id'   => $usahaId,
            'plan_id'    => $planId,
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addDays($planBaru->durasi_hari)->toDateString(),
            'status'     => 'pending',
            'tipe'       => 'upgrade',
        ]);

        $usaha = \App\Models\Usaha::with('owner')->find($usahaId);

        NotificationService::notifySuperAdmins(
            'Request Upgrade Plan',
            "Owner '{$usaha->owner->nama_lengkap}' dari '{$usaha->nama_usaha}' request upgrade ke '{$planBaru->nama_plan}'.",
            'upgrade_plan',
            [
                'usaha_id'          => $usahaId,
                'subscription_id'   => $subBaru->id,
                'plan_baru'         => $planBaru->nama_plan,
                'metode_pembayaran' => $metodePembayaran,
                'tipe'              => 'upgrade',
            ]
        );

        ActivityLogService::log(
            'request_upgrade_plan',
            "Request upgrade ke plan '{$planBaru->nama_plan}' via '{$metodePembayaran}'",
            ['plan_id' => $planId, 'metode' => $metodePembayaran],
            $usahaId,
        );

        return [
            'message' => 'Request upgrade berhasil dikirim. Menunggu konfirmasi pembayaran dari admin.',
            'subscription_baru' => [
                'id'                => $subBaru->id,
                'plan'              => $planBaru->nama_plan,
                'harga'             => (float) $planBaru->harga,
                'durasi_hari'       => $planBaru->durasi_hari,
                'batas_outlet'      => $planBaru->batas_outlet === -1 ? 'Unlimited' : $planBaru->batas_outlet,
                'status'            => 'pending',
                'tipe'              => 'upgrade',
                'metode_pembayaran' => $metodePembayaran,
            ],
            'instruksi' => $metodePembayaran === 'transfer'
                ? 'Transfer ke BCA 1234567890 a/n PT QOMA INDONESIA sebesar Rp ' . number_format($planBaru->harga) . '.'
                : 'Scan QRIS yang dikirimkan admin untuk menyelesaikan pembayaran.',
        ];
    }

    /**
     * Ambil info deactivation queue yang pending (untuk ditampilkan ke owner)
     */
    public function getDeactivationQueue(string $usahaId): array
    {
        $queue = OutletDeactivationQueue::where('usaha_id', $usahaId)
            ->where('is_processed', false)
            ->first();

        if (!$queue) {
            return ['ada_queue' => false];
        }

        // Daftar outlet yang aktif (bisa dipilih untuk dinonaktifkan)
        $outlets = Outlet::where('usaha_id', $usahaId)
            ->where('status_buka', true)
            ->orderByDesc('created_at')
            ->get(['id', 'nama_outlet', 'alamat', 'created_at']);

        return [
            'ada_queue'             => true,
            'jumlah_harus_nonaktif' => $queue->jumlah_harus_nonaktif,
            'deadline'              => $queue->deadline,
            'pesan'                 => "Pilih {$queue->jumlah_harus_nonaktif} outlet yang ingin dinonaktifkan sebelum " . $queue->deadline?->format('d M Y H:i') . ". Jika tidak memilih, sistem akan otomatis menonaktifkan outlet yang paling baru ditambahkan.",
            'outlets_aktif'         => $outlets,
        ];
    }

    /**
     * Owner pilih outlet yang mau dinonaktifkan setelah grace period habis
     */
    public function pilihOutletNonaktif(string $usahaId, array $outletIds): array
    {
        $queue = OutletDeactivationQueue::where('usaha_id', $usahaId)
            ->where('is_processed', false)
            ->firstOrFail();

        if (count($outletIds) !== $queue->jumlah_harus_nonaktif) {
            throw new \Exception(
                "Harus memilih tepat {$queue->jumlah_harus_nonaktif} outlet. Kamu memilih " . count($outletIds) . " outlet."
            );
        }

        // Validasi outlet milik usaha ini
        $outletValid = Outlet::whereIn('id', $outletIds)
            ->where('usaha_id', $usahaId)
            ->count();

        if ($outletValid !== count($outletIds)) {
            throw new \Exception('Satu atau lebih outlet tidak valid atau bukan milik usaha ini.');
        }

        // Cari free plan sebelum transaction
        $freePlan = Plan::where('is_lifetime', true)
            ->orderBy('harga')
            ->first();

        if (!$freePlan) {
            throw new \Exception('Free plan tidak ditemukan. Hubungi administrator.');
        }

        return DB::transaction(function () use ($usahaId, $outletIds, $queue, $freePlan) {

            foreach ($outletIds as $outletId) {
                $outlet = Outlet::find($outletId);
                $outlet->update(['status_buka' => false]);

                // Nonaktifkan user outlet
                \App\Models\User::where('outlet_id', $outletId)->update(['is_active' => false]);

                // Notif outlet
                $outletUsers = \App\Models\User::where('outlet_id', $outletId)->pluck('id');
                foreach ($outletUsers as $userId) {
                    NotificationService::notify(
                        $userId,
                        '🔴 Outlet Dinonaktifkan',
                        "Outlet '{$outlet->nama_outlet}' dinonaktifkan karena subscription telah berakhir.",
                        'outlet_deactivated',
                        ['outlet_id' => $outletId],
                    );
                }

                ActivityLogService::log(
                    'owner_nonaktifkan_outlet',
                    "Owner menonaktifkan outlet '{$outlet->nama_outlet}' setelah subscription expired",
                    ['outlet_id' => $outletId],
                    $usahaId,
                    $outletId,
                );
            }

            $queue->update(['is_processed' => true]);

            // Downgrade ke Free
            $sub = Subscription::find($queue->subscription_id);
            if ($sub) {
                $sub->update(['status' => 'expired']);
                Subscription::create([
                    'id'         => \Illuminate\Support\Str::uuid(),
                    'usaha_id'   => $usahaId,
                    'plan_id'    => $freePlan->id,
                    'start_date' => now()->toDateString(),
                    'end_date'   => null,
                    'status'     => 'active',
                    'tipe'       => 'downgrade',
                ]);
            }

            // Notif owner: berhasil downgrade
            $usaha = \App\Models\Usaha::find($usahaId);
            if ($usaha?->owner_id) {
                NotificationService::notify(
                    $usaha->owner_id,
                    'Downgrade ke Free Plan',
                    'Outlet yang dipilih telah dinonaktifkan. Usaha Anda kembali ke Free plan.',
                    'downgrade_free',
                    ['usaha_id' => $usahaId],
                );
            }

            return [
                'message'              => 'Outlet berhasil dipilih dan dinonaktifkan. Usaha kembali ke Free plan.',
                'outlet_dinonaktifkan' => count($outletIds),
            ];
        });
    }

    public function getAvailablePlans(string $usahaId): array
    {
        $subSekarang = Subscription::where('usaha_id', $usahaId)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();

        // Tampilkan semua plan berbayar kecuali yang sedang dipakai
        return Plan::where('is_lifetime', false)
                ->where('id', '!=', $subSekarang?->plan_id)
                ->orderBy('harga', 'asc')
                ->get()
                ->map(fn($p) => [
                    'id'           => $p->id,
                    'nama_plan'    => $p->nama_plan,
                    'harga'        => (float) $p->harga,
                    'batas_outlet' => $p->batas_outlet === -1 ? 'Unlimited' : $p->batas_outlet,
                    'durasi_hari'  => $p->durasi_hari,
                    'is_lifetime'  => $p->is_lifetime,
                    'deskripsi'    => $p->deskripsi,
                ])
                ->toArray();
    }

    public function processExpiredSubscriptions(): void
    {
        $expiredSubs = Subscription::with('plan')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now())
            ->get();

        foreach ($expiredSubs as $sub) {

            // sudah masuk grace?
            if (!$sub->grace_period_end) {

                // kasih grace 3 hari
                $sub->update([
                    'status' => 'grace_period',
                    'grace_period_end' => now()->addDays(3)
                ]);

                continue;
            }

            // grace masih jalan
            if (now()->lt($sub->grace_period_end)) {
                continue;
            }

            // cari plan free
            $freePlan = Plan::where('nama_plan','Free')->first();

            if (!$freePlan) continue;

            $jumlahOutlet = Outlet::where(
                'usaha_id',
                $sub->usaha_id
            )->count();

            $kelebihanOutlet =
                $jumlahOutlet - $freePlan->batas_outlet;

            if ($kelebihanOutlet <= 0) {

                // langsung downgrade
                $sub->update([
                    'status'=>'expired'
                ]);

                Subscription::create([
                    'id'=>Str::uuid(),
                    'usaha_id'=>$sub->usaha_id,
                    'plan_id'=>$freePlan->id,
                    'status'=>'active',
                    'tipe'=>'downgrade'
                ]);

                continue;
            }

            // buat queue jika belum ada
            OutletDeactivationQueue::firstOrCreate([
                'usaha_id'=>$sub->usaha_id,
                'is_processed'=>false
            ],[
                'id'=>Str::uuid(),
                'subscription_id'=>$sub->id,
                'jumlah_harus_nonaktif'=>$kelebihanOutlet,
                'deadline'=>now()->addDays(3)
            ]);
        }
    }
}