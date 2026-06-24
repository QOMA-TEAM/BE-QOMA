<?php
namespace App\Services\SuperAdmin;
use App\Models\{Usaha, UsahaRejection, User};
use App\Services\{ActivityLogService, NotificationService};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsahaManagementService
{
    public function listUsaha(array $filters = [], int $perPage = 15)
    {
        $query = Usaha::select('id', 'nama_usaha', 'email', 'alamat', 'status', 'owner_id', 'approved_at', 'created_at')
                    ->with([
                        'owner:id,username,nama_lengkap,email,is_active',
                        'subscription:id,usaha_id,plan_id,status,end_date',
                        'subscription.plan:id,nama_plan,harga',
                    ])
                    ->withCount('outlets');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['exclude_status'])) {
            $query->where('status', '!=', $filters['exclude_status']);
        }

        if (!empty($filters['search'])) {
            $query->where(fn($q) =>
                $q->where('nama_usaha', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%")
            );
        }

        return $query->latest()->paginate($perPage);
    }

    public function detailUsaha(string $id): Usaha
    {
        return Usaha::select('id', 'nama_usaha', 'email', 'alamat', 'status', 'owner_id', 'catatan_admin', 'approved_at', 'rejected_at', 'created_at')
                    ->with([
                        'owner:id,username,nama_lengkap,email,is_active',
                        'outlets:id,usaha_id,nama_outlet,alamat,status_buka',
                        'subscription:id,usaha_id,plan_id,status,start_date,end_date',
                        'subscription.plan:id,nama_plan,harga,durasi_hari',
                        'rejections:id,usaha_id,rejected_by,alasan,created_at',
                        'rejections.rejectedBy:id,username',
                    ])
                    ->findOrFail($id);
    }

    public function approve(Usaha $usaha): Usaha
    {
        if ($usaha->status !== 'pending') {
            throw new \Exception("Hanya usaha berstatus 'pending' yang bisa di-approve.");
        }

        $usaha->update(['status' => 'active', 'approved_at' => now()]);

        // Aktifkan owner
        if ($usaha->owner_id) {
            User::where('id', $usaha->owner_id)->update(['is_active' => true]);
        }

        // Kirim notifikasi ke owner
        if ($usaha->owner_id) {
            NotificationService::notify(
                $usaha->owner_id,
                'Usaha Disetujui',
                "Selamat! Usaha '{$usaha->nama_usaha}' Anda telah disetujui.",
                'usaha_approved',
                ['usaha_id' => $usaha->id],
            );
        }

        ActivityLogService::log('approve_usaha', "Usaha '{$usaha->nama_usaha}' di-approve", ['usaha_id' => $usaha->id], $usaha->id);

        return $usaha->fresh('owner');
    }

    public function reject(Usaha $usaha, string $alasan): Usaha
    {
        if ($usaha->status !== 'pending') {
            throw new \Exception("Hanya usaha berstatus 'pending' yang bisa di-reject.");
        }

        $usaha->update(['status' => 'rejected', 'rejected_at' => now(), 'catatan_admin' => $alasan]);

        UsahaRejection::create([
            'id'          => Str::uuid(),
            'usaha_id'    => $usaha->id,
            'rejected_by' => auth()->id(),
            'alasan'      => $alasan,
        ]);

        if ($usaha->owner_id) {
            User::where('id', $usaha->owner_id)->update(['is_active' => false]);

            NotificationService::notify(
                $usaha->owner_id,
                'Usaha Ditolak',
                "Maaf, usaha '{$usaha->nama_usaha}' ditolak. Alasan: {$alasan}",
                'usaha_rejected',
                ['usaha_id' => $usaha->id, 'alasan' => $alasan],
            );
        }

        ActivityLogService::log('reject_usaha', "Usaha '{$usaha->nama_usaha}' di-reject. Alasan: {$alasan}", ['usaha_id' => $usaha->id], $usaha->id);

        return $usaha->fresh('owner', 'rejections');
    }

    public function suspend(Usaha $usaha, ?string $catatan = null): Usaha
    {
        if ($usaha->status === 'suspended') throw new \Exception('Usaha sudah suspended.');

        $usaha->update(['status' => 'suspended', 'catatan_admin' => $catatan]);
        $this->toggleUsahaUsers($usaha, false);

        ActivityLogService::log('suspend_usaha', "Usaha '{$usaha->nama_usaha}' disuspend", ['usaha_id' => $usaha->id], $usaha->id);

        return $usaha->fresh();
    }

    public function unsuspend(Usaha $usaha): Usaha
    {
        if ($usaha->status !== 'suspended') throw new \Exception('Usaha tidak dalam status suspended.');

        $usaha->update(['status' => 'active', 'catatan_admin' => null]);
        $this->toggleUsahaUsers($usaha, true);

        ActivityLogService::log('unsuspend_usaha', "Usaha '{$usaha->nama_usaha}' diaktifkan", ['usaha_id' => $usaha->id], $usaha->id);

        return $usaha->fresh();
    }

    public function listOwner(array $filters = [], int $perPage = 15)
    {
        $query = User::select('id', 'username', 'nama_lengkap', 'email', 'is_active', 'usaha_id', 'created_at')
                    ->whereHas('role', fn($q) => $q->where('name', 'owner'))
                    ->with(['usaha:id,owner_id,nama_usaha,status']);

        if (!empty($filters['search'])) {
            $query->where(fn($q) =>
                $q->where('username', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%")
                ->orWhere('nama_lengkap', 'like', "%{$filters['search']}%")
            );
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->latest()->paginate($perPage);
    }

    public function resetPasswordOwner(User $owner): string
    {
        $newPassword = Str::random(12);
        $owner->update(['password' => Hash::make($newPassword)]);

        ActivityLogService::log('reset_password_owner', "Password owner '{$owner->username}' direset", ['owner_id' => $owner->id]);

        return $newPassword;
    }

    public function toggleOwnerStatus(User $owner): User
    {
        $owner->update(['is_active' => !$owner->is_active]);
        $status = $owner->is_active ? 'diaktifkan' : 'dinonaktifkan';
        ActivityLogService::log('toggle_owner_status', "Owner '{$owner->username}' {$status}", ['owner_id' => $owner->id]);

        return $owner->fresh();
    }

    // Manage Plans (CRUD untuk super admin)
    public function listPlans()
    {
        return \App\Models\Plan::withCount('subscriptions')->get();
    }

    public function createPlan(array $data): \App\Models\Plan
    {
        $plan = \App\Models\Plan::create([
            'id'           => Str::uuid(),
            'nama_plan'    => $data['nama_plan'],
            'harga'        => $data['harga'],
            'batas_outlet' => $data['batas_outlet'],
            'durasi_hari'  => $data['durasi_hari'],
            'status'       => $data['status'] ?? 'aktif',
            'deskripsi'    => $data['deskripsi'] ?? null,
        ]);

        ActivityLogService::log('create_plan', "Plan '{$plan->nama_plan}' dibuat");
        return $plan;
    }

    public function updatePlan(\App\Models\Plan $plan, array $data): \App\Models\Plan
    {
        $plan->update($data);
        ActivityLogService::log('update_plan', "Plan '{$plan->nama_plan}' diupdate");
        return $plan->fresh();
    }

    public function deletePlan(\App\Models\Plan $plan): void
    {
        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            throw new \Exception('Plan tidak bisa dihapus karena masih ada subscriber aktif.');
        }
        $plan->delete();
        ActivityLogService::log('delete_plan', "Plan '{$plan->nama_plan}' dihapus");
    }

    public function ownerByUsaha(string $usahaId)
    {
        $usaha = Usaha::with('owner')->findOrFail($usahaId);

        return $usaha->owner;
    }

    private function toggleUsahaUsers(Usaha $usaha, bool $isActive): void
    {
        User::where('usaha_id', $usaha->id)->update(['is_active' => $isActive]);
    }
}
