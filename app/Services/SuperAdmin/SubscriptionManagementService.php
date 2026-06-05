<?php
namespace App\Services\SuperAdmin;

use App\Models\{Subscription, Usaha};
use App\Services\{ActivityLogService, NotificationService};
use Illuminate\Support\Facades\DB;

class SubscriptionManagementService
{
    /**
     * List semua subscription dengan join lengkap
     * (usaha, owner, plan)
     */
    public function list(array $filters = [], int $perPage = 15)
    {
        $query = Subscription::with([
            'usaha:id,nama_usaha,email,alamat,owner_id',
            'usaha.owner:id,username,nama_lengkap,email',
            'plan:id,nama_plan,harga,batas_outlet',
        ]);

        if (!empty($filters['status']))  $query->where('status', $filters['status']);
        if (!empty($filters['plan_id'])) $query->where('plan_id', $filters['plan_id']);
        if (!empty($filters['tipe']))    $query->where('tipe', $filters['tipe']); // ← BARU
        if (!empty($filters['dari']))    $query->whereDate('start_date', '>=', $filters['dari']);
        if (!empty($filters['sampai']))  $query->whereDate('start_date', '<=', $filters['sampai']);
        if (!empty($filters['search'])) {
            $query->whereHas('usaha', fn($q) =>
                $q->where('nama_usaha', 'like', "%{$filters['search']}%")
            );
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Detail 1 subscription — ini yang paling lengkap
     */
    public function detail(string $id): array
    {
        $sub = Subscription::with([
            'plan:id,nama_plan,harga,batas_outlet,deskripsi',
            'usaha:id,nama_usaha,email,alamat,owner_id',
            'usaha.owner:id,username,nama_lengkap,email,is_active',
        ])->findOrFail($id);

        $totalOutlet = \App\Models\Outlet::where('usaha_id', $sub->usaha_id)->count();

        return [
            'detail_subscription' => [
                'subscription_id' => $sub->id,
                'plan_id'         => $sub->plan_id,
                'start_date'      => $sub->start_date,
                'status'          => $sub->status,
                'created_at'      => $sub->created_at,
                'updated_at'      => $sub->updated_at,
                'plan'            => $sub->plan,
            ],
            'detail_usaha' => [
                'nama_perusahaan' => $sub->usaha->nama_usaha,
                'email'           => $sub->usaha->email,
                'alamat'          => $sub->usaha->alamat,
                'total_outlet'    => $totalOutlet,
                'owner'           => [
                    'nama'     => $sub->usaha->owner->nama_lengkap ?? '-',
                    'username' => $sub->usaha->owner->username,
                    'email'    => $sub->usaha->owner->email,
                ],
            ],
        ];
    }

    /**
     * Konfirmasi pembayaran subscription (pending → active)
     * Dipanggil super admin setelah owner bayar
     */
    public function konfirmasiPembayaran(Subscription $sub): Subscription
    {
        if ($sub->status !== 'pending') {
            throw new \Exception('Subscription ini tidak dalam status pending.');
        }

        DB::transaction(function () use ($sub) {
            $sub->update(['status' => 'active']);

            // Approve usaha sekalian
            $usaha = Usaha::find($sub->usaha_id);
            if ($usaha && $usaha->status === 'pending') {
                $usaha->update(['status' => 'active', 'approved_at' => now()]);

                // Aktifkan owner
                if ($usaha->owner_id) {
                    \App\Models\User::where('id', $usaha->owner_id)->update(['is_active' => true]);
                }

                // Notif ke owner
                NotificationService::notify(
                    $usaha->owner_id,
                    'Pembayaran Dikonfirmasi',
                    "Pembayaran subscription Plan {$sub->plan->nama_plan} telah dikonfirmasi. Akun Anda sekarang aktif!",
                    'payment_confirmed',
                    ['subscription_id' => $sub->id],
                );
            }

            ActivityLogService::log(
                'konfirmasi_pembayaran',
                "Pembayaran subscription '{$sub->id}' dikonfirmasi",
                ['subscription_id' => $sub->id, 'usaha_id' => $sub->usaha_id],
                $sub->usaha_id,
            );
        });

        return $sub->fresh(['plan', 'usaha']);
    }

    /**
     * Cancel subscription
     */
    public function cancel(Subscription $sub, string $alasan): Subscription
    {
        $sub->update(['status' => 'cancelled']);

        ActivityLogService::log(
            'cancel_subscription',
            "Subscription '{$sub->id}' dibatalkan. Alasan: {$alasan}",
            ['subscription_id' => $sub->id, 'alasan' => $alasan],
            $sub->usaha_id,
        );

        return $sub->fresh();
    }
}