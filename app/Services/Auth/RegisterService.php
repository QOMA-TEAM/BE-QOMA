<?php
namespace App\Services\Auth;

use App\Models\{Plan, Subscription, Usaha, User};
use App\Services\{ActivityLogService, NotificationService};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class RegisterService
{
    /**
     * Flow registrasi owner baru:
     * 1. Buat user (role owner, is_active = false dulu)
     * 2. Buat usaha (status = pending)
     * 3. Buat subscription sesuai plan yang dipilih
     * 4. Kirim notifikasi ke super admin
     * 5. Return data + instruksi pembayaran (kalau plan pro)
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {

            $plan = Plan::findOrFail($data['plan_id']);

            // 1. Buat user owner (belum aktif, tunggu approval)
            $user = User::create([
                'id'           => Str::uuid(),
                'role_id'      => 'role_owner',
                'username'     => $data['username'],
                'nama_lengkap' => $data['nama_owner'],
                'email'        => $data['email'],
                'password'     => Hash::make($data['password']),
                'is_active'    => false, // aktif setelah super admin approve
            ]);

            // 2. Buat usaha (status pending)
            $usaha = Usaha::create([
                'id'         => Str::uuid(),
                'nama_usaha' => $data['nama_usaha'],
                'alamat'     => $data['alamat'] ?? null,
                'email'      => $data['email'],
                'owner_id'   => $user->id,
                'status'     => 'pending',
            ]);

            // Update user dengan usaha_id
            $user->update(['usaha_id' => $usaha->id]);

            // 3. Buat subscription (status pending kalau pro, active kalau free)
            $subStatus = $plan->harga > 0 ? 'pending' : 'active';

            $subscription = Subscription::create([
                'id'         => Str::uuid(),
                'usaha_id'   => $usaha->id,
                'plan_id'    => $plan->id,
                'start_date' => now()->toDateString(),
                'end_date' => $plan->is_lifetime
                                ? null  // Free = tidak ada end date
                                : now()->addDays($plan->durasi_hari)->toDateString(),
                'status'     => $subStatus,
            ]);

            // 4. Notifikasi ke semua super admin
            NotificationService::notifySuperAdmins(
                'Owner Baru Mendaftar',
                "Owner baru '{$data['nama_owner']}' mendaftar dengan usaha '{$data['nama_usaha']}' (Plan: {$plan->nama_plan}). Menunggu approval.",
                'new_owner_registration',
                [
                    'usaha_id'  => $usaha->id,
                    'plan_id'   => $plan->id,
                    'plan_name' => $plan->nama_plan,
                ]
            );

            // 5. Activity log
            ActivityLogService::log(
                'owner_register',
                "Owner baru '{$data['nama_owner']}' mendaftar dengan plan '{$plan->nama_plan}'",
                ['usaha_id' => $usaha->id, 'plan' => $plan->nama_plan],
                $usaha->id,
            );

            // 6. Siapkan response
            $response = [
                'message'      => 'Pendaftaran berhasil! Akun Anda sedang menunggu persetujuan admin.',
                'user'         => [
                    'id'           => $user->id,
                    'username'     => $user->username,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email'        => $user->email,
                ],
                'usaha'        => [
                    'id'         => $usaha->id,
                    'nama_usaha' => $usaha->nama_usaha,
                    'status'     => $usaha->status,
                ],
                'subscription' => [
                    'plan'       => $plan->nama_plan,
                    'harga'      => $plan->harga,
                    'start_date' => $subscription->start_date,
                    'end_date'   => $subscription->end_date,
                    'status'     => $subscription->status,
                ],
            ];

            // Kalau plan pro, tambahkan instruksi pembayaran
            if ($plan->harga > 0) {
                $response['pembayaran'] = [
                    'metode'      => $data['metode_pembayaran'] ?? null,
                    'total'       => $plan->harga,
                    'instruksi'   => $data['metode_pembayaran'] === 'transfer'
                        ? 'Transfer ke BCA 1234567890 a/n PT QOMA INDONESIA. Upload bukti ke admin.'
                        : 'Scan QRIS di bawah ini untuk melakukan pembayaran.',
                    'catatan'     => 'Akun akan aktif setelah pembayaran dikonfirmasi oleh admin.',
                ];
            }

            return $response;
        });
    }
}