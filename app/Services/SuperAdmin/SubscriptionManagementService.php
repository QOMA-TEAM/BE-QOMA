<?php
namespace App\Services\SuperAdmin;

use App\Models\{Subscription, Usaha, User};
use App\Services\{ActivityLogService, NotificationService};
use Illuminate\Support\Str;

class SubscriptionManagementService
{
    public function list(array $filters = [], int $perPage = 15)
    {
        $query = Subscription::with([
            'usaha:id,nama_usaha,email,owner_id',
            'usaha.owner:id,username,nama_lengkap,email',
            'plan:id,nama_plan,harga,batas_outlet',
        ]);

        if (!empty($filters['status']))  $query->where('status', $filters['status']);
        if (!empty($filters['plan_id'])) $query->where('plan_id', $filters['plan_id']);
        if (!empty($filters['tipe']))    $query->where('tipe', $filters['tipe']);
        if (!empty($filters['dari']))    $query->whereDate('start_date', '>=', $filters['dari']);
        if (!empty($filters['sampai']))  $query->whereDate('start_date', '<=', $filters['sampai']);

        if (!empty($filters['search'])) {
            $query->whereHas('usaha', fn($q) =>
                $q->where('nama_usaha', 'like', "%{$filters['search']}%")
            );
        }

        return $query->latest()->paginate($perPage);
    }

    public function detail(string $id): Subscription
    {
        return Subscription::with([
            'usaha:id,nama_usaha,email,alamat,owner_id',
            'usaha.owner:id,id,username,nama_lengkap,email',
            'plan:id,nama_plan,harga,batas_outlet,durasi_hari',
        ])->findOrFail($id);
    }

    /**
     * Konfirmasi pembayaran subscription
     * Berlaku untuk: new owner (tipe=new) dan upgrade plan (tipe=upgrade)
     * ID yang dipakai = ID subscription yang status-nya pending
     */
    public function konfirmasiPembayaran(Subscription $sub): Subscription
    {
        if ($sub->status !== 'pending') {
            throw new \Exception('Hanya subscription berstatus pending yang bisa dikonfirmasi.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($sub) {

            // Aktifkan subscription ini
            $sub->update(['status' => 'active']);

            $usaha = Usaha::find($sub->usaha_id);

            if ($sub->tipe === 'new') {
                // New owner: aktifkan usaha dan owner
                $usaha?->update(['status' => 'active', 'approved_at' => now()]);

                if ($usaha?->owner_id) {
                    User::where('id', $usaha->owner_id)->update(['is_active' => true]);

                    NotificationService::notify(
                        $usaha->owner_id,
                        'Akun Aktif!',
                        "Pembayaran dikonfirmasi. Usaha '{$usaha->nama_usaha}' sekarang aktif dengan plan {$sub->plan->nama_plan}.",
                        'subscription_aktif',
                        ['usaha_id' => $usaha->id],
                    );
                }
            } elseif ($sub->tipe === 'upgrade') {
                // Upgrade: nonaktifkan subscription lama yang active
                Subscription::where('usaha_id', $sub->usaha_id)
                             ->where('status', 'active')
                             ->where('id', '!=', $sub->id)
                             ->update(['status' => 'expired']);

                if ($usaha?->owner_id) {
                    NotificationService::notify(
                        $usaha->owner_id,
                        'Upgrade Plan Berhasil!',
                        "Upgrade ke plan {$sub->plan->nama_plan} berhasil. Selamat menikmati fitur baru!",
                        'upgrade_aktif',
                        ['usaha_id' => $usaha->id],
                    );
                }
            }

            ActivityLogService::log(
                'konfirmasi_pembayaran',
                "Pembayaran subscription [{$sub->tipe}] usaha '{$usaha?->nama_usaha}' dikonfirmasi. Plan: {$sub->plan->nama_plan}",
                ['subscription_id' => $sub->id, 'tipe' => $sub->tipe],
                $sub->usaha_id,
            );
        });

        return $sub->fresh(['usaha.owner', 'plan']);
    }

    public function cancel(Subscription $sub, string $alasan): Subscription
    {
        if ($sub->status === 'cancelled') {
            throw new \Exception('Subscription sudah dibatalkan sebelumnya.');
        }

        $sub->update(['status' => 'cancelled']);

        $usaha = Usaha::find($sub->usaha_id);
        if ($usaha?->owner_id) {
            NotificationService::notify(
                $usaha->owner_id,
                'Subscription Dibatalkan',
                "Subscription plan {$sub->plan->nama_plan} dibatalkan. Alasan: {$alasan}",
                'subscription_cancelled',
                ['usaha_id' => $usaha->id],
            );
        }

        ActivityLogService::log(
            'cancel_subscription',
            "Subscription usaha '{$usaha?->nama_usaha}' dibatalkan. Alasan: {$alasan}",
            ['subscription_id' => $sub->id, 'alasan' => $alasan],
            $sub->usaha_id,
        );

        return $sub->fresh(['usaha.owner', 'plan']);
    }

    public function tolakPengajuan(Subscription $sub, string $alasan): Subscription
    {
        if ($sub->status !== 'pending') {
            throw new \Exception('Hanya subscription berstatus pending yang bisa ditolak.');
        }

        $sub->update(['status' => 'rejected']);

        $usaha = Usaha::find($sub->usaha_id);

        if ($sub->tipe === 'new') {
            // Tolak usaha juga
            $usaha?->update(['status' => 'rejected', 'catatan_admin' => $alasan]);
        }

        if ($usaha?->owner_id) {
            NotificationService::notify(
                $usaha->owner_id,
                'Pengajuan Ditolak',
                $sub->tipe === 'new'
                    ? "Pendaftaran usaha '{$usaha->nama_usaha}' ditolak. Alasan: {$alasan}"
                    : "Request upgrade plan ditolak. Alasan: {$alasan}",
                'pengajuan_ditolak',
                ['usaha_id' => $usaha->id, 'alasan' => $alasan],
            );
        }

        ActivityLogService::log(
            'tolak_pengajuan',
            "Pengajuan [{$sub->tipe}] usaha '{$usaha?->nama_usaha}' ditolak. Alasan: {$alasan}",
            ['subscription_id' => $sub->id, 'alasan' => $alasan],
            $sub->usaha_id,
        );

        return $sub->fresh(['usaha.owner', 'plan']);
    }
}