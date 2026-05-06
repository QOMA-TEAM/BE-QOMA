<?php

namespace App\Services\Owner;

use App\Models\{Plan, Subscription};
use App\Services\{ActivityLogService, NotificationService};
use Illuminate\Support\Str;

class OwnerSubscriptionService
{
    /**
     * Ambil subscription aktif milik owner ini
     */
    public function getAktif(string $usahaId): array
    {
        $sub = Subscription::where('usaha_id', $usahaId)
            ->whereIn('status', ['active', 'pending'])
            ->with('plan:id,nama_plan,harga,batas_outlet,deskripsi')
            ->latest()
            ->first();

        if (!$sub) {
            return ['message' => 'Tidak ada subscription aktif'];
        }

        $jumlahOutlet = \App\Models\Outlet::where('usaha_id', $usahaId)->count();

        return [
            'subscription_id' => $sub->id,
            'status'          => $sub->status,
            'start_date'      => $sub->start_date,
            'end_date'        => $sub->end_date,
            'plan'            => [
                'id'           => $sub->plan->id,
                'nama_plan'    => $sub->plan->nama_plan,
                'harga'        => $sub->plan->harga,
                'batas_outlet' => $sub->plan->batas_outlet === -1
                                    ? 'Unlimited'
                                    : $sub->plan->batas_outlet,
                'deskripsi'    => $sub->plan->deskripsi,
            ],
            'penggunaan_outlet' => [
                'dipakai'    => $jumlahOutlet,
                'maksimal'   => $sub->plan->batas_outlet === -1
                                    ? 'Unlimited'
                                    : $sub->plan->batas_outlet,
                'sisa'       => $sub->plan->batas_outlet === -1
                                    ? 'Unlimited'
                                    : max(0, $sub->plan->batas_outlet - $jumlahOutlet),
            ],
        ];
    }

    /**
     * Upgrade plan (free → pro)
     * Flow: buat subscription baru status pending → super admin konfirmasi
     */
    public function upgrade(string $usahaId, string $planId, string $metodePembayaran): array
    {
        $planBaru = Plan::findOrFail($planId);

        // Cek apakah plan baru lebih tinggi dari yang sekarang
        $subSekarang = Subscription::where('usaha_id', $usahaId)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();

        if (!$subSekarang) {
            throw new \Exception('Tidak ada subscription aktif untuk di-upgrade.');
        }

        if ($planBaru->harga <= $subSekarang->plan->harga) {
            throw new \Exception('Plan yang dipilih tidak lebih tinggi dari plan saat ini.');
        }

        // Buat subscription baru dengan status pending
        $subBaru = Subscription::create([
            'id'         => Str::uuid(),
            'usaha_id'   => $usahaId,
            'plan_id'    => $planId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays($plan->durasi_hari)->toDateString(),
            'status'     => 'pending', // tunggu konfirmasi super admin
        ]);

        // Notif ke super admin
        $usaha = \App\Models\Usaha::find($usahaId);
        NotificationService::notifySuperAdmins(
            'Request Upgrade Plan',
            "Owner '{$usaha->owner->nama_lengkap}' dari usaha '{$usaha->nama_usaha}' request upgrade ke plan '{$planBaru->nama_plan}'.",
            'upgrade_plan',
            [
                'usaha_id'           => $usahaId,
                'subscription_id'    => $subBaru->id,
                'plan_baru'          => $planBaru->nama_plan,
                'metode_pembayaran'  => $metodePembayaran,
            ]
        );

        ActivityLogService::log(
            'request_upgrade_plan',
            "Request upgrade ke plan '{$planBaru->nama_plan}' dengan metode '{$metodePembayaran}'",
            ['plan_id' => $planId, 'metode' => $metodePembayaran],
            $usahaId,
        );

        return [
            'message'           => 'Request upgrade berhasil dikirim. Menunggu konfirmasi pembayaran dari admin.',
            'subscription_baru' => [
                'id'                => $subBaru->id,
                'plan'              => $planBaru->nama_plan,
                'harga'             => $planBaru->harga,
                'status'            => 'pending',
                'metode_pembayaran' => $metodePembayaran,
            ],
            'instruksi' => $metodePembayaran === 'transfer'
                ? 'Transfer ke BCA 1234567890 a/n PT QOMA INDONESIA sebesar Rp ' . number_format($planBaru->harga) . '. Konfirmasi ke admin.'
                : 'Scan QRIS yang dikirimkan admin untuk menyelesaikan pembayaran.',
        ];
    }

    /**
     * Ambil semua plan yang tersedia (untuk halaman upgrade)
     */
    public function getAvailablePlans(string $usahaId): array
    {
        $subSekarang = Subscription::where('usaha_id', $usahaId)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();

        $hargaSekarang = $subSekarang?->plan->harga ?? 0;

        // Tampilkan plan yang lebih tinggi dari sekarang
        return Plan::where('harga', '>', $hargaSekarang)
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'nama_plan'    => $p->nama_plan,
                'harga'        => $p->harga,
                'batas_outlet' => $p->batas_outlet === -1 ? 'Unlimited' : $p->batas_outlet,
                'deskripsi'    => $p->deskripsi,
            ])
            ->toArray();
    }
}